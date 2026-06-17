<?php

namespace App\Services;

class TrackingService
{
    // GIF 1x1 trasparente hardcoded
    const PIXEL_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function injectTracking(string $html, int $campaignId, string $subscriberToken): string
    {
        $html = $this->replaceLinks($html, $campaignId, $subscriberToken);
        $html = $this->injectPixel($html, $campaignId, $subscriberToken);
        return $html;
    }

    private function replaceLinks(string $html, int $campaignId, string $token): string
    {
        return preg_replace_callback(
            '/(<a\s[^>]*href=")([^"]+)(")/i',
            function ($matches) use ($campaignId, $token) {
                $url = $matches[2];
                // Non tracciare link già tracciati o mailto
                if (str_starts_with($url, 'mailto:') || str_contains($url, '/t/c/')) {
                    return $matches[0];
                }
                $tracked = url("/t/c/{$campaignId}/{$token}?url=" . base64_encode($url));
                return $matches[1] . $tracked . $matches[3];
            },
            $html
        );
    }

    private function injectPixel(string $html, int $campaignId, string $token): string
    {
        $pixel = '<img src="' . url("/t/o/{$campaignId}/{$token}") . '" width="1" height="1" alt="" style="display:none">';
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $pixel . '</body>', $html);
        }
        return $html . $pixel;
    }
}
