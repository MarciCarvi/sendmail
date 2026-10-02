# Changelog

## [1.5.1] - 2026-10-03

### Correzioni
- **Consegne perse dal webhook**: gli eventi SES di consegna potevano arrivare prima che l'invio avesse salvato il proprio `message_id`; l'aggiornamento non trovava la riga, il webhook rispondeva comunque «OK» e l'evento andava perso (nella prima campagna circa il 17% dei destinatari risultava senza `delivered_at`). Ora ogni evento viene prima salvato nel nuovo registro `sm_ses_events` e applicato all'invio appena possibile: l'ordine di arrivo non conta più.
- **Contatore bounce del report sempre a zero**: il bounce veniva registrato solo sull'iscritto, mai sull'invio. Ora i bounce (permanenti e temporanei, con tipo e sottotipo) e i complaint sono salvati sull'invio e il report li mostra. I bounce asincroni, arrivati dopo la consegna, non cancellano più la consegna ma vengono tolti dai «Consegnati».
- **Double opt-in**: la colonna `status` degli iscritti non conteneva il valore `unconfirmed` nelle installazioni create con le migration; ora viene allineata.

### Novità
- Il webhook registra anche gli eventi `DeliveryDelay` e `Reject`, se attivati nel Configuration Set di SES (non modificano gli invii).
- Il registro eventi si svuota da solo dopo 180 giorni (`SM_SES_EVENTS_RETENTION_DAYS`).

### Note
- Gli eventi consumati prima di questa versione non sono recuperabili: valgono per gli invii da ora in poi.

## [1.5.0] - 2026-10-02

### Novità
- **Disiscrizione per cliente**: chi si disiscrive da una lista viene registrato nella nuova tabella dei disiscritti, associato al **dominio dell'email mittente della lista** (che coincide con quello del profilo di invio). Da quel momento non riceve più campagne con un mittente di quel dominio, indipendentemente dalla lista usata, ma può continuare a ricevere quelle di altri clienti. La blacklist resta per i blocchi validi per tutti.
- **Nuova pagina «Disiscritti»** (menu): elenco con email, cliente (dominio e nome del profilo), lista di origine e data; ricerca, filtro per cliente e azione «Riabilita», che toglie l'indirizzo dall'elenco e rimette «iscritto» le sue righe disiscritte per quel cliente.
- Una nuova iscrizione volontaria (form pubblico o conferma double opt-in) toglie l'indirizzo dai disiscritti del cliente.
- Anche la disiscrizione manuale dall'elenco iscritti (singola o di gruppo) viene registrata; il ritorno a «iscritto» la rimuove.
- L'import e l'aggiunta manuale rispettano i disiscritti del cliente: nel log dei saltati compare il motivo «Disiscritto per questo cliente».
- **Profilo di invio obbligatorio** per inviare o programmare una campagna (le bozze si salvano anche senza).

### Note
- Vale per le disiscrizioni da questa versione in poi: non vengono recuperate quelle precedenti.
- Come prima, chi si disiscrive mentre una campagna è già in invio può ricevere le email rimaste in coda.

## [1.4.9] - 2026-10-02

### Correzioni
- **Anteprima mobile**: passando da Desktop a Mobile l'HTML viene ora ricaricato da zero nella nuova larghezza (media query e layout rivalutati), invece di limitarsi a restringere la finestra. Le email a larghezza fissa non responsive vengono rimpicciolite per entrare nello schermo, come fanno Apple Mail e Gmail sul telefono, e non più tagliate a destra.
- Nell'editor «HTML puro» c'è il pulsante ↻ per ricaricare a mano l'anteprima.

## [1.4.8] - 2026-10-01

### Novità
- **Log temporaneo dell'import**: dopo l'import di una lista, il riepilogo mostra quante righe sono state saltate e perché (email non valida, in blacklist, dominio bloccato, duplicata nel file, già presente nella lista), con l'elenco consultabile a schermo (prime 200 righe) e il CSV completo scaricabile (numero di riga, email, motivo). Il CSV resta disponibile 24 ore e viene eliminato automaticamente.

## [1.4.7] - 2026-09-30

### Correzioni
- **Favicon**: `public/favicon.ico` nel repository era un file vuoto (0 byte), quindi le installazioni aggiornate avevano la favicon della radice rotta. Ora è l'icona corretta.
- **Web manifest**: i percorsi delle icone sono relativi, così funzionano anche nelle installazioni in sottocartella (es. `/sendmail`).
- **Pagina di login**: aggiunto il `?v=versione` ai link della favicon (come nel resto dell'app), per far ricaricare l'icona ai browser a ogni release.

## [1.4.6] - 2026-09-30

### Novità
- **Editor «HTML puro»**: da *Campagne → + Nuova campagna* (freccia accanto al pulsante) si può scegliere tra *Editor a blocchi* e *HTML puro*. In HTML puro Unlayer non viene caricato: c'è un editor di codice (CodeMirror, con evidenziazione della sintassi e numeri di riga) con anteprima dal vivo a fianco, desktop e mobile. L'HTML viene salvato esattamente come scritto, senza nessuna rielaborazione. Restano disponibili «Testo semplice» e «Immagini».
- La modalità si sceglie alla creazione e non cambia più (Unlayer non può importare HTML arbitrario). Le campagne esistenti restano a blocchi.
- All'invio restano solo le elaborazioni necessarie: variabili (`{{first_name}}`…), riscrittura dei link per il tracking dei click e pixel di apertura prima di `</body>`.

### Correzioni
- **Changelog mai aggiornato dopo l'aggiornamento web**: `CHANGELOG.md` era escluso dall'archivio di release (`export-ignore` in `.gitattributes`), quindi l'updater non lo scaricava e la finestra «Novità» mostrava sempre il file vecchio. Ora viene incluso.
- Nelle campagne a blocchi la scheda «HTML» ora è in sola lettura: prima, modificarla a mano non serviva a nulla perché al salvataggio veniva sovrascritta con l'output di Unlayer.

## [1.4.5] - 2026-09-30

### Novità
- **Liste di test**: nel form della lista c'è la nuova casella «Lista di test». Una lista di test non può essere scelta come destinataria di una campagna (ed è esclusa dall'invio anche se già associata), non accetta iscrizioni pubbliche e mostra il badge TEST. Se ne possono creare quante servono, ad esempio una per cliente.
- **Invio test alla lista**: nella campagna il nuovo menu «Lista di test» con il pulsante «Invia test alla lista» invia l'email di test a tutti i membri (massimo 10). Ignora lo status di iscrizione (chi si è disiscritto per distrazione riceve ancora i test) ed esclude solo bounce, complaint e blacklist. Il campo «Email di test» singola resta disponibile.
- **Lista di test predefinita nel profilo di invio**: scegliendo il profilo nella campagna, il menu della lista di test si preseleziona.
- **Liste destinatarie con caselle di spunta** al posto della selezione multipla con Ctrl/⌘, con i comandi «Tutte» / «Nessuna».

### Modifiche
- I test non creano più righe in `sm_campaign_sends` e non aggiungono più l'indirizzo di test come iscritto della prima lista (prima poteva ricevere anche la campagna vera). I test restano senza tracking.
- Il messaggio dell'invio test singolo ora segnala correttamente l'errore quando SES rifiuta l'invio.

### Correzioni
- Il link di disiscrizione non viene più riscritto dal click tracking: chi clicca «disiscriviti» non conta più come click.

## [1.4.4] - 2026-09-29

### Correzioni

- **Delivery/Bounce/Complaint con Configuration Set**: il webhook SES leggeva solo `notificationType` (notifiche di identità), mentre gli eventi dei Configuration Set usano `eventType`. Risultato: `delivered_at` non veniva mai valorizzato e bounce/complaint non venivano registrati. Ora sono supportati entrambi i formati.

## [1.4.3] - 2026-09-29

### Correzioni

- **Salvataggio bozza senza oggetto**: salvare una campagna con oggetto, nome o email mittente vuoti dava `Column 'subject' cannot be null` (errore 500). Ora le bozze incomplete si salvano; l'invio resta bloccato finché i campi obbligatori non sono compilati.

## [1.4.2] - 2026-09-29

### Correzioni

- **Updater**: dopo l'aggiornamento ora svuota anche la cache delle rotte (`route:clear` + eliminazione di `bootstrap/cache/routes-*.php`) e resetta OPcache. Prima le nuove rotte non venivano riconosciute (es. `Route [sender-profiles.index] not defined` dopo l'aggiornamento a 1.4.1).

## [1.4.1] - 2026-09-29

### Novità

- Release che include i **profili di invio** introdotti nel tag `v1.4.0` (mai pubblicato come Release): usa questa versione per l'aggiornamento dal pannello.

### Note

- Al primo avvio dopo l'aggiornamento viene eseguita in automatico la migration che crea `sm_sender_profiles` e aggiunge `sender_profile_id` a `sm_campaigns`.
- Dopo l'aggiornamento: crea i profili in _Profili di invio_ e verifica in SES le email mittente di ogni profilo.

## [1.4.0] - 2026-06-20

### Novità

- **Profili di invio ("vesti")**: nuova sezione _Profili di invio_ per gestire più mittenti nella stessa installazione. Ogni profilo raccoglie nome/email mittente, reply-to e Configuration Set SES. Nella campagna un menu a tendina sceglie il profilo e precompila i campi mittente; l'invio (campagna e test) usa il Configuration Set del profilo, con fallback su quello globale di Impostazioni.

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
