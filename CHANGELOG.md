# Changelog

## [1.1.0] - 2026-06-17

### Novità
- Sistema di licenze con verifica dominio e periodo di grazia (3 giorni)
- Sistema di aggiornamenti automatici via GitHub Releases
- Modale CHANGELOG automatica dopo l'applicazione di un aggiornamento
- Libreria immagini campagna custom (upload, copia URL, elimina)
- Sistema di template Unlayer personalizzato (salva/carica design completi)
- Copia URL con fallback per contesti HTTP

### Correzioni
- Fix: modal salvataggio template appariva al caricamento pagina
- Fix: DataCloneError su loadTemplate (Alpine Proxy incompatibile con postMessage)
- Fix: tab preview/immagini/template visibili su tab errate (conflitto d-flex/x-show)
- Fix: navigator.clipboard non disponibile in HTTP

## [1.0.0] - 2026-01-01

### Novità
- Dashboard con statistiche globali (liste, iscritti, campagne, email/24h)
- CRUD Liste con api_token per embed form
- CRUD Iscritti con import/export CSV
- CRUD Campagne con editor Unlayer
- Invio bulk browser-driven senza queue worker
- Invio schedulato (cron via artisan scheduler)
- Tracking aperture (pixel 1x1) e click (redirect tracciato)
- Unsubscribe link personalizzato per iscritto
- Double opt-in configurabile per lista
- Webhook SES per bounce, complaint e delivery
- Report campagna: sent, open rate, click rate, bounce
- Form embed pubblico (JS snippet, iframe, HTML puro)
- Blacklist domini
- Impostazioni Amazon SES via UI
- Web installer (wizard PHP puro senza dipendenze CLI)
