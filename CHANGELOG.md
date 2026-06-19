# Changelog

## [1.3.0] - 2026-06-19

### Sicurezza / Licensing
- **Rimosso il PAT GitHub dal client.** Prima ogni installazione cliente conteneva nel `.env` un Personal Access Token GitHub con permessi di scrittura: chiunque avesse accesso al proprio `.env` poteva estrarlo. La validazione della licenza ora avviene su un license server dedicato (`carvisiglia.com/sendmail-license`) che firma le risposte con RSA. Il client verifica la firma con una chiave **pubblica** embedded (innocua) — nessun segreto risiede più lato cliente.
- Il binding dominio↔licenza è gestito server-side; sparisce la scrittura su GitHub e quindi la necessità di un token con permessi di scrittura.
- Mantenuto il periodo di grace offline di 3 giorni se il license server è irraggiungibile.

> Questa release include anche tutte le correzioni di sicurezza elencate nella 1.2.1 qui sotto.

## [1.2.1] - 2026-06-19

### Sicurezza
- **Critico — Installer ri-eseguibile**: `public/install/_wizard.php` era raggiungibile via HTTP anche dopo l'installazione, permettendo di sovrascrivere `.env`, rigenerare `APP_KEY` e dirottare DB/admin. Aggiunto guard `.installed` in cima al wizard e `public/install/.htaccess` che nega l'accesso diretto.
- **Critico — Registrazione aperta**: chiunque poteva registrarsi e ottenere accesso completo al pannello (app single-tenant, nessun ruolo). La registrazione è ora consentita solo per il primo admin; una volta creato un utente è disabilitata.
- **Critico — Webhook SES senza verifica firma + SSRF**: `/webhook/ses` accettava notifiche non autenticate (bounce/complaint/delivery falsificabili) e seguiva `SubscribeURL` arbitrari. Aggiunta verifica della firma SNS via OpenSSL e allowlist host `sns.*.amazonaws.com` su `SigningCertURL` e `SubscribeURL`. Rimosso il log di debug delivery non limitato.
- **Open redirect**: il tracking dei click ora segue solo URL `http`/`https` (bloccati `javascript:`, `data:`, ecc.).
- **CSV injection**: l'export iscritti neutralizza i campi che iniziano con `= + - @` (esecuzione formule in Excel/Sheets).
- **Rate limiting** sugli endpoint pubblici: tracking 240/min, unsubscribe/opt-in 30/min, form embed 60/min, iscrizione 10/min.
- **Hardening**: `GET /embed/{token}` non crea più righe in DB (solo l'iscrizione reale via POST le persiste); open/click registrati solo per destinatari reali della campagna; l'aggiornamento verifica che l'archivio scaricato sia un'installazione SendMail valida prima di sovrascrivere i file.

## [1.1.12] - 2026-06-19

### Correzioni
- Fix critico: `message_id` e `delivered_at` non venivano mai salvati in `sm_campaign_sends` — campi mancanti da `$fillable` nel modello. Il delivery tracking non ha mai funzionato per questo motivo.
- Fix: "Invia email di test" ora crea un record in `sm_campaign_sends` con il `message_id` SES, permettendo di verificare la catena SNS → webhook → `delivered_at` senza inviare una campagna completa.

## [1.1.11] - 2026-06-17

### Correzioni
- Fix critico: POST da SNS non raggiungeva il webhook — `RewriteRule ^public/ - [L]` su LiteSpeed blocca silenziosamente le richieste POST verso directory. Rimossa la regola: ora tutte le richieste non-asset vanno direttamente a `public/index.php [L,QSA]`.

## [1.1.10] - 2026-06-17

### Debug / Correzioni
- Aggiunto logging dettagliato al webhook SNS (storage/logs/laravel.log) per diagnosticare mancati aggiornamenti delivery

## [1.1.9] - 2026-06-17

### Correzioni
- Fix critico: webhook SNS (`/webhook/ses`) non raggiungibile quando APP_URL contiene `/public` — Laravel riceveva il path `/public/webhook/ses` invece di `/webhook/ses` e restituiva 404 silenzioso. Ora `public/index.php` normalizza il REQUEST_URI strippando il segmento `/public/` ridondante.

## [1.1.8] - 2026-06-17

### Correzioni
- Fix: CSS/JS/immagini non caricano su hosting in sottocartella — .htaccess radice ora mappa i path degli asset statici (build/, img/, favicon/) verso public/ invece di passarli a Laravel

## [1.1.7] - 2026-06-17

### Correzioni
- Fix: public/build/ (CSS/JS compilati) inclusi nel pacchetto di rilascio — il CSS non sparisce più dopo l'aggiornamento
- Fix: public/build protetto da sovrascrittura durante gli aggiornamenti futuri

## [1.1.6] - 2026-06-17

### Correzioni
- Fix: modale changelog non chiudibile dopo aggiornamento (conflitto Bootstrap `d-block !important` con Alpine `x-show`)
- Fix: aggiunto click sul backdrop per chiudere la modale
- Fix: pulizia forzata dei file view compilati dopo aggiornamento (evita cache stale su hosting condiviso)

## [1.1.5] - 2026-06-17

### Correzioni
- Fix: "Disponibile vundefined" in verifica aggiornamenti (chiavi JSON camelCase e snake_case uniformate)

## [1.1.4] - 2026-06-17

### Correzioni
- Fix: modale changelog duplicata dopo aggiornamento (rimossa modale inline, una sola modale post-reload)
- Fix: pulsante chiudi modale changelog non funzionante

## [1.1.3] - 2026-06-17

### Correzioni
- Fix: chiavi array opzionali in settings/index.blade.php (ses_configuration_set, license_key) gestite con fallback ?? ""

## [1.1.2] - 2026-06-17

### Novità
- Aggiunto pulsante "Verifica aggiornamenti" nella pagina Impostazioni con force-check immediato

## [1.1.1] - 2026-06-17

### Correzioni
- Fix: bug `$ok` undefined in CampaignSender causava contatori sent/failed sempre errati
- Fix: `.htaccess` root compatibile con LiteSpeed/Hostinger (POST 405 risolto)
- Fix: `public/index.php` subfolder fix per hosting condiviso (routing Laravel corretto)
- Aggiunto: campo Configuration Set SES nelle impostazioni per tracking delivery
- Aggiunto: versione e data rilascio nel footer dell'interfaccia

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
