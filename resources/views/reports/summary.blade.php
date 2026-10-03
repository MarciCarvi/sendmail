<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapporto — {{ $campaign->subject }}</title>
    <style>
        :root { --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --accent:#6d28d9; --accent-soft:#ede9fe; --orange:#FFA400; --violet:#8B5CF6; --ok:#059669; --ok-soft:#d1fae5; --bad:#b45309; --bad-soft:#fef3c7; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin:0; background:#f3f4f6; color:var(--ink); font:14px/1.5 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .toolbar { max-width:820px; margin:16px auto 0; padding:0 16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .toolbar a, .toolbar button { font:inherit; padding:7px 14px; border-radius:6px; border:1px solid var(--line); background:#fff; color:var(--ink); text-decoration:none; cursor:pointer; }
        .toolbar .primary { background:var(--accent); border-color:var(--accent); color:#fff; }
        .toolbar .hint { color:var(--muted); font-size:12px; }
        .page { max-width:820px; margin:16px auto; background:#fff; padding:36px 40px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        header.top { display:flex; align-items:center; gap:14px; padding-bottom:12px; border-bottom:3px solid var(--orange); }
        header.top img { max-height:58px; max-width:240px; }
        header.top .brand { font-size:20px; font-weight:700; }
        header.top .doc { margin-left:auto; text-align:right; color:var(--muted); font-size:12px; }
        h1 { font-size:24px; line-height:1.25; margin:22px 0 4px; }
        .sub { color:var(--muted); margin-bottom:20px; }
        .banner { background:var(--bad-soft); color:var(--bad); padding:8px 12px; border-radius:6px; margin-bottom:16px; font-size:13px; }
        .kpis { display:grid; grid-template-columns:repeat(6,1fr); gap:8px; margin-bottom:26px; }
        .kpi { border:1px solid var(--line); border-radius:8px; padding:11px 9px; text-align:center; }
        .kpi.hl { border-color:var(--accent); background:var(--accent-soft); }
        .kpi .v { font-size:24px; font-weight:700; line-height:1.1; }
        .kpi .l { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin-top:4px; }
        .kpi .s { font-size:11px; color:var(--muted); line-height:1.35; margin-top:2px; }
        section { margin-bottom:26px; break-inside:avoid; }
        h2 { font-size:16px; margin:0 0 10px; padding-bottom:6px; border-bottom:1px solid var(--line); }
        .stack { display:flex; height:22px; border-radius:6px; overflow:hidden; background:var(--line); }
        .stack .a { background:var(--ok); } .stack .b { background:#f59e0b; }
        .dot-note { margin-left:auto; }
        .legend { display:flex; gap:18px; font-size:12px; color:var(--muted); margin-top:6px; }
        .dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:5px; }
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); border-bottom:1px solid var(--line); padding:6px 8px; }
        td { padding:8px; border-bottom:1px solid var(--line); vertical-align:middle; }
        td.n, th.n { text-align:right; white-space:nowrap; }
        .bar { height:8px; background:var(--line); border-radius:4px; min-width:90px; }
        .bar i { display:block; height:100%; border-radius:4px; background:var(--accent); }
        .bar.warn i { background:#f59e0b; }
        .note { font-size:12px; color:var(--muted); margin-top:8px; }
        .mini { display:flex; gap:28px; margin-bottom:10px; }
        .mini b { font-size:20px; display:block; line-height:1.2; }
        .mini span { font-size:12px; color:var(--muted); }
        footer { border-top:2px solid var(--violet); padding-top:12px; font-size:11px; color:var(--muted); }
        .credit { margin-top:10px; text-align:right; font-size:12px; color:var(--muted); display:flex; align-items:center; justify-content:flex-end; gap:5px; }
        .credit svg { width:16px; height:16px; }
        .credit b { color:var(--violet); font-size:13px; }
        a.lnk { color:var(--ink); text-decoration:none; word-break:break-all; }
        @media (max-width:760px) { .kpis { grid-template-columns:repeat(3,1fr); } .page { padding:20px; } }
        @media print {
            body { background:#fff; }
            .toolbar { display:none; }
            .page { margin:0; padding:0; box-shadow:none; max-width:none; zoom:.76; }
            h1 { font-size:21px; margin-top:14px; }
            .kpis { margin-bottom:18px; grid-template-columns:repeat(6,1fr); } section { margin-bottom:16px; }
            td { padding:5px 8px; }
            @page { size:A4; margin:11mm; }
        }
    </style>
</head>
@php
    $n = fn($v) => number_format((float) $v, 0, ',', '.');
    $p = fn($v) => number_format((float) $v, 1, ',', '.') . '%';
    $s = $summary;
    $sameDay = $s['firstSent'] && $s['lastSent'] && $s['firstSent']->isSameDay($s['lastSent']);
@endphp
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Stampa / Salva come PDF</button>
        <a href="{{ route('reports.show', $campaign) }}">← Torna al report</a>
        <span class="hint">Nella finestra di stampa scegli «Salva come PDF» e attiva «Grafica di sfondo».</span>
    </div>

    <div class="page">
        <header class="top">
            @if($brand['logo'])
                <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
            @else
                <div class="brand">{{ $brand['name'] }}</div>
            @endif
            <div class="doc">Rapporto di campagna<br>{{ $s['generatedAt']->format('d/m/Y') }}</div>
        </header>

        <h1>{{ $campaign->subject }}</h1>
        <div class="sub">
            Mittente: {{ $campaign->from_email }}
            @if($s['firstSent'])
                · Invio:
                @if($sameDay)
                    {{ $s['firstSent']->format('d/m/Y') }}, dalle {{ $s['firstSent']->format('H:i') }} alle {{ $s['lastSent']->format('H:i') }}
                @else
                    dal {{ $s['firstSent']->format('d/m/Y H:i') }} al {{ $s['lastSent']->format('d/m/Y H:i') }}
                @endif
            @endif
        </div>

        @if($s['inProgress'])
            <div class="banner">Invio ancora in corso: i numeri sono parziali.</div>
        @endif

        <div class="kpis">
            <div class="kpi"><div class="v">{{ $n($s['sent']) }}</div><div class="l">Inviati</div></div>
            <div class="kpi"><div class="v">{{ $p($s['deliveryRate']) }}</div><div class="l">Consegnati</div><div class="s">{{ $n($s['delivered']) }} / {{ $n($s['sent']) }}</div></div>
            <div class="kpi"><div class="v">{{ $p($s['openRate']) }}</div><div class="l">Open rate</div><div class="s">{{ $n($s['uniqueOpens']) }} unici / {{ $n($s['totalOpens']) }} tot</div></div>
            <div class="kpi hl"><div class="v">{{ $p($s['clickRate']) }}</div><div class="l">Click rate</div><div class="s">{{ $n($s['uniqueClicks']) }} unici / {{ $n($s['totalClicks']) }} tot</div></div>
            <div class="kpi"><div class="v">{{ $p($s['unsubRate']) }}</div><div class="l">Unsub rate</div><div class="s">{{ $n($s['unsubscribed']) }} disiscritti</div></div>
            <div class="kpi">
                <div class="v">{{ $n($s['bounced'] + $s['failed'] + $s['complaints']) }}</div><div class="l">Problemi</div>
                <div class="s">
                    @if($s['bounced'] > 0){{ $n($s['bounced']) }} bounce<br>@endif
                    @if($s['failed'] > 0){{ $n($s['failed']) }} falliti<br>@endif
                    @if($s['complaints'] > 0){{ $n($s['complaints']) }} complaint<br>@endif
                    @if($s['bounced'] + $s['failed'] + $s['complaints'] === 0)nessuno @endif
                </div>
            </div>
        </div>

        <section>
            <h2>Consegna</h2>
            <div class="stack">
                <div class="a" style="width: {{ $s['deliveredPct'] }}%"></div>
                <div class="b" style="width: {{ $s['notDeliveredPct'] }}%"></div>
            </div>
            <div class="legend">
                <span><span class="dot" style="background:var(--ok)"></span>Consegnate {{ $n($s['delivered']) }} ({{ $p($s['deliveredPct']) }})</span>
                <span><span class="dot" style="background:#f59e0b"></span>Non consegnate {{ $n($s['notDelivered']) }} ({{ $p($s['notDeliveredPct']) }})</span>
                <span class="dot-note">sulle email inviate</span>
            </div>
            <div class="note">Una email è «consegnata» quando il server del destinatario l'ha accettata.</div>
        </section>

        <section>
            <h2>Chi non ha ricevuto la email, e perché</h2>
            @if($s['sent'] === 0)
                <p>Nessun invio registrato per questa campagna.</p>
            @elseif($s['notDelivered'] === 0)
                <p>Tutte le email inviate sono state consegnate.</p>
            @else
                <table>
                    <thead><tr><th>Motivo</th><th class="n">Email</th><th class="n">% sulle inviate</th><th style="width:25%"></th></tr></thead>
                    <tbody>
                        @foreach($s['reasons'] as $r)
                            <tr>
                                <td>{{ $r['label'] }}</td>
                                <td class="n">{{ $n($r['n']) }}</td>
                                <td class="n">{{ $p($r['pct']) }}</td>
                                <td><div class="bar warn"><i style="width: {{ min(100, $s['notDeliveredPct'] > 0 ? $r['n'] / max(1, $s['notDelivered']) * 100 : 0) }}%"></i></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="note">
                    «Nessuna conferma di consegna» indica che il server del destinatario non ha né confermato né rifiutato il messaggio
                    entro {{ $s['graceHours'] }} ore: può trattarsi di un ritardo o di un problema temporaneo dal lato del destinatario.
                    Gli indirizzi non validi o inesistenti vengono esclusi automaticamente dagli invii successivi.
                </div>
            @endif
            @if($s['notSent'] > 0 || $s['inQueue'] > 0)
                <div class="note">
                    @if($s['notSent'] > 0)Inoltre {{ $n($s['notSent']) }} email non sono state inviate (rifiutate al momento dell'invio). @endif
                    @if($s['inQueue'] > 0){{ $n($s['inQueue']) }} email risultano ancora in coda. @endif
                </div>
            @endif
        </section>

        <section>
            <h2>Click</h2>
            <div class="mini">
                <div><b>{{ $n($s['uniqueClicks']) }}</b><span>destinatari che hanno cliccato</span></div>
                <div><b>{{ $p($s['clickRate']) }}</b><span>sulle email inviate</span></div>
                <div><b>{{ $n($s['totalClicks']) }}</b><span>click totali</span></div>
            </div>
            @if($s['topLinks']->isNotEmpty())
                @php $maxClicks = max(1, $s['topLinks']->max('uniq')); @endphp
                <table>
                    <thead><tr><th>Link più cliccati</th><th class="n">Destinatari</th><th class="n">Click</th><th style="width:22%"></th></tr></thead>
                    <tbody>
                        @foreach($s['topLinks'] as $l)
                            <tr>
                                <td><a class="lnk" href="{{ $l->original_url }}" title="{{ $l->original_url }}">{{ \Illuminate\Support\Str::limit($l->original_url, 70) }}</a></td>
                                <td class="n">{{ $n($l->uniq) }}</td>
                                <td class="n">{{ $n($l->total) }}</td>
                                <td><div class="bar"><i style="width: {{ $l->uniq / $maxClicks * 100 }}%"></i></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="note">Nessun click registrato finora.</p>
            @endif
        </section>

        <section>
            <h2>Aperture <span style="font-weight:400;color:var(--muted)">(dato indicativo)</span></h2>
            <div class="mini">
                <div><b>{{ $n($s['uniqueOpens']) }}</b><span>aperture stimate</span></div>
                <div><b>{{ $p($s['openRate']) }}</b><span>sulle email inviate</span></div>
            </div>
            <div class="note">
                Il conteggio delle aperture è approssimato: alcuni programmi di posta le registrano anche senza lettura reale
                (per esempio Apple Mail), altri non le registrano finché le immagini non vengono scaricate. Il dato più affidabile sono i click.
            </div>
        </section>

        <section>
            <h2>Informazioni aggiuntive</h2>
            <div class="mini">
                <div><b>{{ $n($s['unsubscribed']) }}</b><span>disiscrizioni</span></div>
                <div><b>{{ $n($s['complaints']) }}</b><span>segnalazioni come spam</span></div>
            </div>
            <div class="note">Dati riportati per completezza informativa.</div>
        </section>

        <footer>
            Dati aggiornati al {{ $s['generatedAt']->format('d/m/Y H:i') }} (ora di Roma). Click e aperture possono continuare ad aumentare nei giorni successivi all'invio.
            Click unici: destinatari distinti che hanno cliccato almeno un link.
            <div class="credit">
                Rapporto realizzato da
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="#8B5CF6" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="29" cy="31" r="8.5"/><path d="M37.5 31V34a6 6 0 0 0 12 0V31a20 20 0 1 0-7 15.2"/><path d="M48 12 58 10 56 20"/></svg>
                <b>SendMail</b>
            </div>
        </footer>
    </div>
</body>
</html>
