<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Le immagini della libreria NL e il logo dei rapporti passano da storage/app/public
     * (raggiunte dal link simbolico public/storage, assente o rotto su molti hosting) a
     * public/uploads, servita direttamente dal web server. Sposta i file già caricati.
     * Non sovrascrive e non fallisce: ciò che non si riesce a spostare resta dov'è
     * (i vecchi indirizzi /storage/... lo servono comunque). Solo file, nessun cambio di schema.
     */
    public function up(): void
    {
        foreach (['campaign-images', 'branding'] as $folder) {
            $from = storage_path("app/public/{$folder}");
            $to   = public_path("uploads/{$folder}");

            if (!is_dir($from)) {
                continue;
            }
            if (!is_dir($to) && !@mkdir($to, 0755, true) && !is_dir($to)) {
                continue;
            }

            foreach (glob($from . '/*') ?: [] as $file) {
                $target = $to . '/' . basename($file);
                if (!is_file($file) || is_file($target)) {
                    continue;
                }
                if (@copy($file, $target)) {
                    @chmod($target, 0644);
                    @unlink($file);
                }
            }
        }
    }

    public function down(): void
    {
        //
    }
};
