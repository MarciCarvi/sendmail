<?php

namespace App\Services;

/**
 * Tracciamento ANONIMO delle campagne (Garante privacy, provv. n. 284 del 17/4/2026 sui pixel di tracciamento).
 *
 * Il pixel di apertura e i link tracciati sono IDENTICI per tutti i destinatari di una campagna: contengono solo
 * l'id della campagna e una firma (HMAC con APP_KEY) che impedisce di gonfiare le statistiche con id inventati e,
 * per i link, di usare il tracker come redirect verso indirizzi qualunque. Non contengono né l'email né il token
 * dell'iscritto, e il server non salva mai chi ha aperto o cliccato (vedi TrackOpenController / TrackClickController).
 */
class TrackingService
{
    // GIF 1x1 trasparente hardcoded
    const PIXEL_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** Lunghezza della firma nei nuovi indirizzi; i vecchi token degli iscritti sono lunghi 64 (vedi isLegacyToken). */
    private const SIGNATURE_LENGTH = 24;

    /**
     * Inserisce pixel e link tracciati nell'HTML del MODELLO della campagna (prima della sostituzione delle variabili
     * per iscritto): il risultato è lo stesso per tutti i destinatari. I link che contengono variabili ({{email}}…)
     * sono per definizione individuali e non vengono tracciati.
     */
    public function injectTracking(string $html, int $campaignId): string
    {
        $html = $this->replaceLinks($html, $campaignId);

        return $this->injectPixel($html, $campaignId);
    }

    public function openUrl(int $campaignId): string
    {
        return url("/t/o/{$campaignId}/" . $this->openSignature($campaignId));
    }

    public function linkUrl(int $campaignId, string $url): string
    {
        return url("/t/c/{$campaignId}/" . $this->clickSignature($campaignId, $url) . '?url=' . base64_encode($url));
    }

    public function openSignature(int $campaignId): string
    {
        return $this->sign("o|{$campaignId}");
    }

    public function clickSignature(int $campaignId, string $url): string
    {
        return $this->sign("c|{$campaignId}|{$url}");
    }

    public function isValidOpen(int $campaignId, string $token): bool
    {
        return hash_equals($this->openSignature($campaignId), $token);
    }

    public function isValidClick(int $campaignId, string $token, string $url): bool
    {
        return hash_equals($this->clickSignature($campaignId, $url), $token);
    }

    /** Vecchio indirizzo (fino alla 1.13.x): al posto della firma c'era il token dell'iscritto (64 caratteri). */
    public function isLegacyToken(string $token): bool
    {
        return strlen($token) === 64;
    }

    private function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, self::SIGNATURE_LENGTH);
    }

    private function replaceLinks(string $html, int $campaignId): string
    {
        return preg_replace_callback(
            '/(<a\s[^>]*href=")([^"]+)(")/i',
            function ($matches) use ($campaignId) {
                $raw = $matches[2];

                // Solo link http(s) fissi. Restano com'erano: mailto/tel/ancore, link già tracciati, disiscrizione
                // (un click su "disiscriviti" non è un click di interesse) e link con variabili personali.
                if (!preg_match('#^https?://#i', $raw) || str_contains($raw, '/t/c/') || str_contains($raw, '/u/') || str_contains($raw, '{{')) {
                    return $matches[0];
                }

                // Nell'HTML gli indirizzi hanno &amp;: il redirect deve andare all'indirizzo vero
                $url = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);

                return $matches[1] . $this->linkUrl($campaignId, $url) . $matches[3];
            },
            $html
        );
    }

    private function injectPixel(string $html, int $campaignId): string
    {
        $pixel = '<img src="' . $this->openUrl($campaignId) . '" width="1" height="1" alt="" style="display:none">';
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $pixel . '</body>', $html);
        }

        return $html . $pixel;
    }
}
