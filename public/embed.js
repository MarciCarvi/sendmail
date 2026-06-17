(function () {
    var script  = document.currentScript;
    var token   = script.getAttribute('data-token');
    var targetId = script.getAttribute('data-target') || 'sendmail-widget';
    var baseUrl = script.getAttribute('data-url') || script.src.replace('/embed.js', '');

    if (!token) { console.error('SendMail embed: data-token mancante.'); return; }

    var target = document.getElementById(targetId);
    if (!target) { console.error('SendMail embed: elemento #' + targetId + ' non trovato.'); return; }

    var style = [
        '.sm-widget *{box-sizing:border-box;font-family:inherit}',
        '.sm-widget form{display:flex;flex-direction:column;gap:8px}',
        '.sm-widget input{width:100%;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:14px}',
        '.sm-widget input:focus{outline:none;border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.15)}',
        '.sm-widget .sm-row{display:flex;gap:8px}',
        '.sm-widget .sm-row input{flex:1}',
        '.sm-widget button{padding:10px;background:#0d6efd;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;font-weight:600}',
        '.sm-widget button:hover{background:#0b5ed7}',
        '.sm-widget button:disabled{opacity:.6;cursor:not-allowed}',
        '.sm-widget .sm-msg{padding:10px;border-radius:6px;font-size:14px;text-align:center}',
        '.sm-widget .sm-msg.ok{background:#d1e7dd;color:#0a3622}',
        '.sm-widget .sm-msg.err{background:#f8d7da;color:#58151c}',
    ].join('');

    var styleEl = document.createElement('style');
    styleEl.textContent = style;
    document.head.appendChild(styleEl);

    target.innerHTML = [
        '<div class="sm-widget">',
        '  <form id="sm-form">',
        '    <input type="email" name="email" placeholder="Email *" required>',
        '    <div class="sm-row">',
        '      <input type="text" name="first_name" placeholder="Nome">',
        '      <input type="text" name="last_name" placeholder="Cognome">',
        '    </div>',
        '    <input type="text" name="company" placeholder="Azienda">',
        '    <button type="submit" id="sm-btn">Iscriviti</button>',
        '  </form>',
        '  <div id="sm-msg" class="sm-msg" style="display:none"></div>',
        '</div>',
    ].join('');

    document.getElementById('sm-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('sm-btn');
        var msg = document.getElementById('sm-msg');
        var data = new FormData(this);

        btn.disabled = true;
        btn.textContent = '...';
        msg.style.display = 'none';

        fetch(baseUrl + '/subscribe/' + token, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data,
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            msg.textContent = d.message;
            msg.className   = 'sm-msg ' + (d.outcome === 'error' ? 'err' : 'ok');
            msg.style.display = 'block';
            if (d.outcome !== 'error') {
                document.getElementById('sm-form').style.display = 'none';
            } else {
                btn.disabled = false;
                btn.textContent = 'Iscriviti';
            }
        })
        .catch(function () {
            msg.textContent = 'Errore di rete. Riprova.';
            msg.className = 'sm-msg err';
            msg.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Iscriviti';
        });
    });
})();
