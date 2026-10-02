{{-- Ciclo di invio condiviso (pagina campagna e pagina report).
     Chiama process-batch in sequenza; il server concede un solo lotto alla volta per campagna,
     quindi più pagine aperte non inviano due volte. --}}
<script>
function sendingDriver(cfg) {
    const sleep = (ms) => new Promise(r => setTimeout(r, ms));

    return {
        status:  cfg.status,
        total:   cfg.total   || 0,
        sent:    cfg.sent    || 0,
        failed:  cfg.failed  || 0,
        pending: cfg.pending || 0,
        percent: cfg.percent || 0,
        running: false,
        busy:    false,
        error:   null,
        attempts: 0,
        kpiTimer: null,

        init() {
            if (this.status === 'sending') {
                this.startLoop();
            }
            if (cfg.kpiSelector && this.status !== 'sent') {
                // aggiorna le statistiche (aperture, click, consegne…) ogni 30 secondi
                this.kpiTimer = setInterval(() => {
                    if (this.status === 'sending' || this.status === 'paused') this.refreshKpi();
                }, 30000);
            }
        },

        async startLoop() {
            if (this.running) return;
            this.running = true;
            this.error = null;
            this.attempts = 0;

            while (this.running) {
                const outcome = await this.batch();
                if (outcome === 'stop') break;
                if (outcome === 'busy')  await sleep(5000);                     // un'altra pagina sta inviando: aggiorna ogni 5 s
                else if (outcome === 'retry') await sleep(Math.min(30000, 2000 * 2 ** (this.attempts - 1)));
                else await sleep(300);                                          // pausa breve tra un lotto e l'altro
            }
            this.running = false;
        },

        async batch() {
            try {
                const r = await fetch(cfg.batchUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
                });
                if (r.status === 419) {
                    this.error = 'Sessione scaduta: ricarica la pagina per continuare l\'invio.';
                    return 'stop';
                }
                if (!r.ok) throw new Error('HTTP ' + r.status);

                const d = await r.json();
                this.apply(d);
                this.attempts = 0;
                this.error = null;
                this.busy = !!d.busy;

                if (d.status !== 'sending') {
                    this.finished();
                    return 'stop';
                }
                return d.busy ? 'busy' : 'ok';
            } catch (e) {
                this.attempts++;
                if (this.attempts >= 5) {
                    this.error = 'Invio interrotto dopo 5 tentativi falliti (' + e.message + '). Premi «Riprendi» per riprovare.';
                    return 'stop';
                }
                this.error = 'Problema di connessione (' + e.message + '): nuovo tentativo ' + this.attempts + ' di 5…';
                return 'retry';
            }
        },

        apply(d) {
            this.status  = d.status;
            this.total   = d.total;
            this.sent    = d.sent;
            this.failed  = d.failed;
            this.pending = d.pending;
            this.percent = d.percent;
        },

        retry() {
            this.startLoop();
        },

        finished() {
            if (this.status === 'sent') {
                if (this.kpiTimer) clearInterval(this.kpiTimer);
                if (cfg.kpiSelector) this.refreshKpi();
            }
        },

        async post(url) {
            return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' } });
        },

        async pause() {
            this.running = false;
            const r = await this.post(cfg.pauseUrl);
            if (r.ok) this.status = 'paused';
        },

        async resume() {
            const r = await this.post(cfg.resumeUrl);
            if (r.ok) {
                this.status = 'sending';
                this.startLoop();
            }
        },

        // Ricarica la pagina in background e sostituisce solo il blocco delle statistiche
        async refreshKpi() {
            try {
                const html = await (await fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })).text();
                const doc  = new DOMParser().parseFromString(html, 'text/html');
                const next = doc.querySelector(cfg.kpiSelector);
                const cur  = document.querySelector(cfg.kpiSelector);
                if (next && cur) cur.innerHTML = next.innerHTML;
            } catch (e) { /* riproverà al prossimo giro */ }
        },
    };
}
</script>
