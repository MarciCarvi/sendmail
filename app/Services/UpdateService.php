<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateService
{
    public function getCurrentVersion(): string
    {
        return config('sendmail.version', '1.0.0');
    }

    public function checkForUpdates(bool $forceRefresh = false): array
    {
        $intervalHours  = (int) config('sendmail.updates.check_interval_hours', 24);
        $lastChecked    = Setting::get('update_last_checked');
        $currentVersion = $this->getCurrentVersion();

        if (!$forceRefresh && $lastChecked && now()->diffInHours(Carbon::parse($lastChecked)) < $intervalHours) {
            return [
                'has_update'      => Setting::get('update_available') === '1',
                'latest_version'  => Setting::get('update_latest_version'),
                'current_version' => $currentVersion,
                'release_notes'   => Setting::get('update_release_notes'),
            ];
        }

        return $this->fetchFromGitHub($currentVersion);
    }

    private function fetchFromGitHub(string $currentVersion): array
    {
        $repo = config('sendmail.updates.repo');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'SendMail-Updater/1.0',
                ])
                ->get("https://api.github.com/repos/{$repo}/releases/latest");

            if (!$response->successful()) {
                return [
                    'has_update' => false, 'latest_version' => null,
                    'current_version' => $currentVersion, 'release_notes' => null,
                ];
            }

            $release       = $response->json();
            $latestVersion = ltrim($release['tag_name'] ?? '', 'v');
            $releaseNotes  = $release['body'] ?? null;
            $releaseDate   = isset($release['published_at']) ? substr($release['published_at'], 0, 10) : null;
            $hasUpdate     = version_compare($latestVersion, $currentVersion, '>');

            Setting::set('update_last_checked', now()->toIso8601String());
            Setting::set('update_available', $hasUpdate ? '1' : '0');
            Setting::set('update_latest_version', $latestVersion);
            Setting::set('update_release_notes', $releaseNotes);
            Setting::set('update_release_date', $releaseDate);

            return [
                'has_update'      => $hasUpdate,
                'hasUpdate'       => $hasUpdate,
                'latest_version'  => $latestVersion,
                'latestVersion'   => $latestVersion,
                'current_version' => $currentVersion,
                'currentVersion'  => $currentVersion,
                'release_notes'   => $releaseNotes,
                'releaseNotes'    => $releaseNotes,
            ];

        } catch (\Throwable $e) {
            Log::warning('SendMail update check failed: ' . $e->getMessage());
            return [
                'has_update' => false, 'latest_version' => null,
                'current_version' => $currentVersion, 'release_notes' => null,
            ];
        }
    }

    public function applyUpdate(string $version): array
    {
        $repo   = config('sendmail.updates.repo');
        $zipUrl = "https://github.com/{$repo}/archive/refs/tags/v{$version}.zip";

        $tmpDir  = storage_path('app/updates');
        @mkdir($tmpDir, 0755, true);
        $zipPath = $tmpDir . '/update.zip';

        // Download
        try {
            $zipData = Http::timeout(120)
                ->withHeaders(['User-Agent' => 'SendMail-Updater/1.0'])
                ->get($zipUrl);

            if (!$zipData->successful()) {
                return ['success' => false, 'error' => 'Download fallito (HTTP ' . $zipData->status() . ').'];
            }

            file_put_contents($zipPath, $zipData->body());

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Download fallito: ' . $e->getMessage()];
        }

        // Extract
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);
            return ['success' => false, 'error' => 'File zip non valido o corrotto.'];
        }

        $extractDir = $tmpDir . '/extracted_' . $version;
        @mkdir($extractDir, 0755, true);
        $zip->extractTo($extractDir);
        $zip->close();

        // Find source root (usually sendmail-1.2.0/)
        $dirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        if (empty($dirs)) {
            $this->removeDir($extractDir);
            @unlink($zipPath);
            return ['success' => false, 'error' => 'Struttura zip non valida.'];
        }
        $sourceDir = $dirs[0];

        // Sanity check: the archive must actually look like a SendMail/Laravel
        // tree before we overwrite application files with it. Guards against a
        // malformed or unexpected download clobbering the install.
        if (!file_exists($sourceDir . '/artisan') || !file_exists($sourceDir . '/composer.json')) {
            $this->removeDir($extractDir);
            @unlink($zipPath);
            return ['success' => false, 'error' => 'Archivio non riconosciuto: aggiornamento annullato.'];
        }

        // Paths to never overwrite
        $skip = ['.env', 'storage', 'public/build', 'install/.installed', 'install/done.php'];

        $this->copyRecursive($sourceDir, base_path(), $skip);

        // Update VERSION and RELEASE_DATE
        file_put_contents(base_path('VERSION'), $version . "\n");
        $releaseDate = Setting::get('update_release_date');
        if ($releaseDate) {
            file_put_contents(base_path('RELEASE_DATE'), $releaseDate . "\n");
        }

        // Migrate + clear caches
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        // Force-delete compiled views directly (Artisan view:clear may fail on shared hosting)
        $viewCacheDir = storage_path('framework/views');
        if (is_dir($viewCacheDir)) {
            foreach (glob($viewCacheDir . '/*.php') ?: [] as $f) {
                @unlink($f);
            }
        }

        // Extract release notes for this version from the freshly-copied CHANGELOG.md
        Setting::set('update_release_notes', $this->extractChangelogNotes($version));

        // Reset update cache
        Setting::set('update_available', '0');
        Setting::set('update_last_checked', null);
        Setting::set('update_latest_version', null);

        // Cleanup temp files
        @unlink($zipPath);
        $this->removeDir($extractDir);

        return ['success' => true];
    }

    private function extractChangelogNotes(string $version): ?string
    {
        $changelog = base_path('CHANGELOG.md');
        if (!file_exists($changelog)) {
            return null;
        }

        $content = file_get_contents($changelog);
        // Match the section for this version: from ## [x.y.z] to the next ## heading
        if (preg_match('/^## \[' . preg_quote($version, '/') . '\][^\n]*\n(.*?)(?=^## |\z)/ms', $content, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function copyRecursive(string $src, string $dst, array $skip): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace($src . DIRECTORY_SEPARATOR, '', $item->getRealPath());
            $relative = str_replace('\\', '/', $relative);

            foreach ($skip as $skipPath) {
                if ($relative === $skipPath || str_starts_with($relative, rtrim($skipPath, '/') . '/')) {
                    continue 2;
                }
            }

            $target = $dst . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @copy($item->getRealPath(), $target);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
        }
        @rmdir($dir);
    }
}
