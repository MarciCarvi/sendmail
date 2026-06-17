# SendMail — Newsletter Platform (Sendy Clone)

## Cos'è SendMail
App PHP/Laravel self-hosted per l'invio di newsletter via Amazon SES. Alternativa moderna a Sendy.co. Architettura single-tenant: ogni cliente riceve una propria installazione in cartella separata, con prefisso tabelle dedicato nel DB condiviso.

---

## Stack
- **Laravel 12** — framework principale
- **Blade** — template engine
- **Bootstrap 5** — CSS framework
- **Alpine.js** — interazioni UI leggere (modal, toggle, dropdown) + chiamate AJAX via `fetch()`
- **MySQL** — database (DBngin in locale)
- **Amazon SES** — unico provider email supportato (AWS SDK for PHP)
- **Invio bulk browser-driven** — nessun queue worker: il browser chiama `/process-batch` in loop via AJAX, funziona su qualsiasi hosting PHP
- **Laravel Breeze** — auth starter kit (Blade + Alpine)

## Ambiente locale
- **Herd** — server PHP/Nginx (`sendmail.test`)
- **DBngin** — MySQL locale
- **TablePlus** — GUI database
- **VS Code** — editor

## Setup progetto (da eseguire una volta)
```bash
cd ~/Herd/sendmail
composer create-project laravel/laravel .
composer require laravel/breeze aws/aws-sdk-php
php artisan breeze:install blade
npm install && npm run build
php artisan migrate
```

---

## Struttura cartelle target
```
/sendmail
├── app/
│   ├── Http/Controllers/
│   │   ├── ListController.php
│   │   ├── SubscriberController.php
│   │   ├── CampaignController.php
│   │   ├── ReportController.php
│   │   ├── SettingsController.php
│   │   └── Public/
│   │       ├── UnsubscribeController.php
│   │       ├── ConfirmController.php
│   │       ├── TrackOpenController.php
│   │       ├── TrackClickController.php
│   │       ├── SubscribeController.php  — form embed + iscrizione pubblica
│   │       └── SesWebhookController.php
│   ├── Models/
│   │   ├── MailList.php
│   │   ├── Subscriber.php
│   │   ├── Campaign.php
│   │   ├── CampaignSend.php
│   │   ├── CampaignOpen.php
│   │   ├── CampaignClick.php
│   │   └── Setting.php
│   ├── Services/
│   │   ├── SesService.php          — invio email + gestione credenziali SES
│   │   ├── CampaignSender.php      — logica invio bulk + throttling
│   │   └── TrackingService.php     — generazione pixel/link tracciati
│   └── Jobs/
│       └── SendCampaignJob.php     — job asincrono per ogni email
├── resources/views/
│   ├── layouts/app.blade.php
│   ├── lists/
│   ├── subscribers/
│   ├── campaigns/
│   ├── reports/
│   ├── settings/
│   └── public/                     — pagine pubbliche (unsub, confirm, redirect)
├── routes/
│   ├── web.php                     — rotte autenticate
│   └── public.php                  — rotte pubbliche (tracking, unsub)
└── config/
    └── sendmail.php                — config app (prefisso tabelle, ecc.)
```

---

## Schema DB

Prefisso tabelle configurabile in `config/sendmail.php` (default: `sm_`).
Ogni installazione cliente usa il proprio prefisso.

```sql
-- Liste
sm_lists (
  id, api_token VARCHAR(64) UNIQUE,   -- token pubblico per form embed e subscribe API
  name, from_name, from_email, reply_to,
  double_optin TINYINT(1) DEFAULT 0,
  created_at, updated_at
)

-- Iscritti
sm_subscribers (
  id, list_id, email, first_name, last_name, company,
  status ENUM('subscribed','unsubscribed','bounced','complained','unconfirmed') DEFAULT 'subscribed',
  token VARCHAR(64),          -- usato per unsubscribe link + conferma double optin
  subscribed_at, unsubscribed_at, created_at, updated_at,
  INDEX idx_list_id (list_id),
  INDEX idx_email (email),
  INDEX idx_token (token)
)

-- Pivot campagne ↔ liste (many-to-many)
sm_campaign_lists (
  campaign_id, list_id,
  PRIMARY KEY (campaign_id, list_id)
)

-- Campagne
sm_campaigns (
  id, subject, from_name, from_email, reply_to,
  html_content LONGTEXT,
  design_json LONGTEXT NULL,           -- stato interno Unlayer (per ripristino editor)
  text_content TEXT,
  status ENUM('draft','scheduled','sending','sent','paused') DEFAULT 'draft',
  scheduled_at TIMESTAMP NULL,
  sent_at TIMESTAMP NULL,
  total_recipients INT DEFAULT 0,
  created_at, updated_at
)

-- Invii (una riga per ogni email della campagna)
sm_campaign_sends (
  id, campaign_id, subscriber_id,
  status ENUM('pending','sent','failed','bounced','complained') DEFAULT 'pending',
  sent_at TIMESTAMP NULL,
  message_id VARCHAR(255) NULL,        -- MessageId restituito da SES, usato per match delivery webhook
  delivered_at TIMESTAMP NULL,         -- valorizzato da webhook SNS Delivery
  INDEX idx_campaign_id (campaign_id),
  INDEX idx_subscriber_id (subscriber_id),
  INDEX idx_message_id (message_id)
)

-- Aperture
sm_campaign_opens (
  id, campaign_id, subscriber_id,
  opened_at TIMESTAMP,
  ip VARCHAR(45), user_agent TEXT,
  INDEX idx_campaign_id (campaign_id)
)

-- Click
sm_campaign_clicks (
  id, campaign_id, subscriber_id,
  original_url TEXT,
  clicked_at TIMESTAMP,
  ip VARCHAR(45),
  INDEX idx_campaign_id (campaign_id)
)

-- Impostazioni (key-value)
sm_settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT
)
-- Chiavi usate: ses_key, ses_secret, ses_region, ses_sending_rate,
--              app_name, app_url, default_from_name, default_from_email
```

---

## Routing

### Rotte autenticate (`routes/web.php`)
```
GET  /                          → dashboard (statistiche globali)
GET  /lists                     → ListController@index
POST /lists                     → ListController@store
GET  /lists/{id}                → ListController@show
PUT  /lists/{id}                → ListController@update
DELETE /lists/{id}              → ListController@destroy

GET  /lists/{id}/subscribers    → SubscriberController@index
POST /lists/{id}/subscribers    → SubscriberController@store
POST /lists/{id}/subscribers/import → SubscriberController@import (CSV)
GET  /lists/{id}/subscribers/export → SubscriberController@export (CSV)
DELETE /lists/{id}/subscribers/{sub} → SubscriberController@destroy

GET  /campaigns                 → CampaignController@index
GET  /campaigns/create          → CampaignController@create
POST /campaigns                 → CampaignController@store
GET  /campaigns/{id}/edit       → CampaignController@edit
PUT  /campaigns/{id}            → CampaignController@update
POST /campaigns/{id}/send-test  → CampaignController@sendTest
POST /campaigns/{id}/schedule   → CampaignController@schedule
POST /campaigns/{id}/send-now      → CampaignController@sendNow
POST /campaigns/{id}/process-batch → CampaignController@processBatch (AJAX batch loop)
POST /campaigns/{id}/pause         → CampaignController@pause
POST /campaigns/{id}/resume        → CampaignController@resume
GET  /campaigns/{id}/progress      → CampaignController@progress (JSON)
POST /campaigns/{id}/duplicate     → CampaignController@duplicate

GET  /reports/{campaign_id}     → ReportController@show

GET  /settings                  → SettingsController@index
PUT  /settings                  → SettingsController@update
POST /settings/test-ses         → SettingsController@testSes
```

### Rotte pubbliche (`routes/public.php` — no auth, no CSRF)
```
GET  /u/{token}                 → UnsubscribeController@show   (pagina conferma)
POST /u/{token}                 → UnsubscribeController@confirm (esegue unsub)
GET  /c/{token}                 → ConfirmController@confirm    (double optin)
GET  /t/o/{campaign_id}/{token} → TrackOpenController   (pixel 1x1 GIF)
GET  /t/c/{campaign_id}/{token}?url={base64_url} → TrackClickController (redirect + log)
POST /webhook/ses               → SesWebhookController (bounce/complaint/delivery da SNS)

GET  /embed/{token}             → SubscribeController@form  (pagina form hosted/iframe)
POST /subscribe/{token}         → SubscribeController@subscribe (accetta iscrizioni)
```

CSRF escluso per: `/t/*`, `/u/*`, `/c/*`, `/webhook/ses`, `/subscribe/*`

---

## Servizio SES (`app/Services/SesService.php`)

```php
use Aws\Ses\SesClient;

class SesService {
    public function send(...): string|false   // restituisce MessageId SES o false
    public function verifyCredentials(): bool
    public function getSendingQuota(): array   // Max24HourSend, SentLast24Hours, MaxSendRate
}
```

Credenziali lette da `sm_settings` (non da `.env`) → modificabili via UI.

## Invio bulk (`app/Services/CampaignSender.php`)

- **`prepare(Campaign)`** — crea record `sm_campaign_sends` pending + imposta campagna su `sending`
- **`processBatch(Campaign, SesService)`** — invia fino a `min(rate, 10)` email in modo sincrono, restituisce JSON con progresso
- Nessun queue worker richiesto: il browser chiama `/process-batch` in loop AJAX
- Funziona su qualsiasi hosting PHP (incluso hosting condiviso senza SSH)

## Webhook SES (`POST /webhook/ses` → `SesWebhookController`)

Gestisce notifiche SNS per:
- **Bounce** (permanente) → `sm_subscribers.status = 'bounced'`
- **Complaint** → `sm_subscribers.status = 'complained'`
- **Delivery** → aggiorna `sm_campaign_sends.delivered_at` via match su `message_id`

La prima chiamata SNS è `SubscriptionConfirmation` → il controller la conferma automaticamente via HTTP GET al `SubscribeURL`.

---

## Tracking

### Open (pixel 1x1)
- Nell'HTML campagna: `<img src="{APP_URL}/t/o/{campaign_id}/{subscriber_token}" width="1" height="1">`
- Controller: registra apertura in `sm_campaign_opens`, restituisce GIF 1x1 trasparente

### Click (redirect)
- Tutti i link HTML sostituiti prima dell'invio con: `{APP_URL}/t/c/{campaign_id}/{subscriber_token}?url={base64_url}`
- Controller: registra click in `sm_campaign_clicks`, redirect 302 all'URL originale

### Unsubscribe link
- In ogni email: `{APP_URL}/u/{subscriber_token}`
- Token univoco per iscritto (generato all'inserimento, non cambia)

---

## Fasi di sviluppo

### Fase 1 — Setup (4h)
- Laravel 12 + Breeze + dipendenze
- Config DB con prefisso tabelle
- Migrations per tutte le tabelle
- Layout Blade base (Bootstrap 5, navbar, sidebar)
- Pagina Settings con form credenziali SES + bottone "Test connessione"

### Fase 2 — Liste e iscritti (8h)
- CRUD liste
- Tabella iscritti con ricerca/filtro per status
- Import CSV (email, name, campi custom) con preview e validazione
- Export CSV
- Aggiunta/modifica/eliminazione singolo iscritto
- Unsub manuale da UI

### Fase 3 — Campagne + editor (10h)
- CRUD campagne
- Editor HTML: integrazione **Unlayer** (gratuito, embed JS) oppure textarea con preview live
- Variabili personalizzazione: `{{name}}`, `{{email}}`
- Tab HTML / Testo semplice
- Anteprima email in iframe
- Invio email di test a indirizzo specifico

### Fase 4 — Invio bulk + scheduling (10h)
- `SendCampaignJob` con throttling SES
- `CampaignSender` service: popola `sm_campaign_sends`, dispatcha job
- Invio immediato (`send-now`)
- Invio schedulato (cron via Laravel Scheduler + `php artisan schedule:run`)
- Pulsante pausa/riprendi
- Progress bar invio (polling AJAX su `sm_campaign_sends`)

### Fase 5 — Tracking + webhook (10h)
- Sostituzione link nell'HTML prima dell'invio
- Inserimento pixel open
- Controller open (GIF 1x1)
- Controller click (redirect + log)
- Controller unsubscribe (pagina + conferma)
- Endpoint webhook SES + verifica firma SNS
- Update status subscriber su bounce/complaint

### Fase 6 — Report (6h)
- Dashboard campagna: sent, open rate, click rate, unsub, bounce
- Tabella iscritti che hanno aperto / cliccato
- Grafico aperture per ora (Alpine.js + Chart.js)

### Fase 7 — Pagine pubbliche (4h) ✅
- Pagina unsubscribe con messaggio di conferma
- Pagina double optin (se attivato sulla lista)
- Gestione token scaduto/non valido

### Fase 8 — Form embed (iscrizione da sito esterno) ✅

Ogni lista espone un `api_token` univoco (generato automaticamente, non espone ID numerico).

**Endpoint pubblici:**
- `GET  /embed/{token}` — pagina form hosted (usabile come iframe)
- `POST /subscribe/{token}` — accetta iscrizioni (JSON o form submit)

**Auto-creazione lista:** se il token non corrisponde a nessuna lista esistente, la lista viene creata automaticamente con il token come nome (es. `website` → lista "Website"). Utile per embed zero-config.

**Tre modalità di embed** (accessibili dal bottone "Form" nella pagina Liste):
1. **JS Snippet** — `<div id="sm-X"></div> + <script src="/embed.js" data-token="..." data-target="sm-X">` — form con CSS integrato, submit AJAX, zero dipendenze esterne
2. **Iframe** — `<iframe src="/embed/{token}">` — massimo isolamento CSS
3. **HTML puro** — `<form action="/subscribe/{token}">` — personalizzabile al 100%

**File statico:** `public/embed.js` — servito direttamente dal web server.

**Double opt-in:** se la lista ha `double_optin = 1`, l'iscritto viene creato con `status = unconfirmed` e riceve email di conferma via SES con link `/c/{token}`.

**CORS:** l'endpoint `/subscribe/{token}` risponde con `Access-Control-Allow-Origin: *` per supportare fetch da domini esterni.

---

## Installer (da sviluppare dopo la Fase 7)

Web installer distribuibile alla WordPress — consente il deploy su hosting condiviso senza SSH né CLI.

### Struttura pacchetto
```
sendmail.zip
├── public/
│   └── index.php        ← se non installato, reindirizza a /install/
├── install/             ← wizard standalone PHP puro (nessuna dipendenza Laravel)
│   ├── index.php        ← step 1: verifica requisiti (PHP version, estensioni, permessi)
│   ├── step2.php        ← step 2: configurazione DB (host, port, nome, user, password, prefisso)
│   ├── step3.php        ← step 3: account admin (email + password)
│   ├── step4.php        ← esecuzione: scrive .env, crea tabelle via PDO, genera APP_KEY, crea admin
│   └── done.php         ← conferma installazione + link al login
├── app/
├── vendor/              ← incluso nel pacchetto (no composer richiesto sull'hosting)
└── ...
```

### Comportamento
- Step 1: controlla PHP ≥ 8.2, estensioni richieste (pdo_mysql, mbstring, openssl, ecc.), permessi su `storage/` e `bootstrap/cache/`
- Step 2: testa connessione DB prima di procedere
- Step 4: migrazioni eseguite via **PDO puro** (no artisan, no shell_exec) — SQL raw per ogni tabella
- Al termine: crea `install/.installed` → l'installer si blocca e reindirizza all'app
- Supporto subfolder: `APP_URL` configurato dinamicamente (es. `https://miodominio.it/newsletter`)

---

## Sicurezza
- Webhook SES: validare firma SNS (libreria `aws/aws-sdk-php`)
- Import CSV: validare email con `filter_var`, skip righe malformate
- Token iscritti: `Str::random(64)` — non reversibili, non sequenziali
- Credenziali SES: salvate in DB criptate con `Crypt::encrypt/decrypt`
- Rate limiting su endpoint pubblici tracking (evitare abuse)
- CSRF escluso solo per `/webhook/ses` e `/t/*` e `/u/*`

---

## Multi-installazione (per cliente)

Ogni cliente = directory separata + prefisso tabelle diverso:

```
~/Herd/sendmail-cliente1/    → sendmail-cliente1.test
~/Herd/sendmail-cliente2/    → sendmail-cliente2.test
```

In `config/sendmail.php`:
```php
'table_prefix' => env('SM_TABLE_PREFIX', 'sm_'),
```

In `.env` del cliente:
```
SM_TABLE_PREFIX=c1_
DB_DATABASE=sendmail
```

I modelli Eloquent usano `$this->getTable()` con prefisso dinamico, oppure si definisce `protected $table` da config.

---

## Note tecniche

- **Throttling SES**: rate letto da `sm_settings.ses_sending_rate`. Batch size = `min(rate, 10)` per richiesta AJAX.
- **Invio senza queue worker**: `CampaignSender::processBatch()` invia in modo sincrono, guidato dal browser via fetch loop. Per produzione su server dedicato si può ripristinare il job-based approach con `SendCampaignJob`.
- **Scheduler campagne programmate**: `routes/console.php` → `Schedule::call()` ogni minuto. In locale: `php artisan schedule:work`. Su hosting condiviso: cron `* * * * * php artisan schedule:run`.
- **Unlayer editor**: CDN gratuito, embed via `<script>` + `unlayer.init()`. Design salvato come JSON in `sm_campaigns.design_json` per ripristino editor. Nessuna dipendenza npm.
- **GIF pixel**: `base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')` — GIF 1x1 trasparente hardcoded.
- **api_token liste**: generato con `Str::random(32)` nel booted hook. Usato per identificare la lista negli endpoint pubblici senza esporre ID numerico. Le liste esistenti senza token vanno aggiornate con `DB::table('sm_lists')->where(...)->update()`.

---

## Dipendenze composer
```json
{
  "require": {
    "laravel/framework": "^12.0",
    "laravel/breeze": "^2.0",
    "aws/aws-sdk-php": "^3.0"
  }
}
```

## Dipendenze npm
```json
{
  "devDependencies": {
    "vite": "^6.0",
    "@vitejs/plugin-laravel": "^1.0"
  },
  "dependencies": {
    "bootstrap": "^5.3",
    "alpinejs": "^3.0",
    "chart.js": "^4.0"
  }
}
```
