<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Vecchi indirizzi delle immagini: /storage/campaign-images/x.png e /storage/branding/x.png.
 *
 * Fino alla 1.9.0 le immagini stavano in storage/app/public ed erano raggiunte tramite il link
 * simbolico public/storage. Dove il link manca (o è una copia ferma) quegli indirizzi finivano
 * in 404, ma sono già scritti nelle newsletter. Se il file esiste lo serve da qui: prima dalla
 * posizione attuale (public/uploads), poi da quella vecchia. Dove public/storage funziona il
 * web server risponde da solo e la richiesta non arriva a Laravel.
 */
class LegacyStorageController extends Controller
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __invoke(string $folder, string $file): BinaryFileResponse
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, self::EXTENSIONS, true), 404);

        foreach ([public_path("uploads/{$folder}/{$file}"), storage_path("app/public/{$folder}/{$file}")] as $path) {
            if (is_file($path)) {
                return response()->file($path, ['Cache-Control' => 'public, max-age=604800']);
            }
        }

        abort(404);
    }
}
