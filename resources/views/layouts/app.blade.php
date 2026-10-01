<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Bandara Indonesia')</title>
@yield('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f5f8fd;
  --surface:#ffffff;
  --line:#dce5f2;
  --blue:#1f5fd6;
  --blue-dark:#174aab;
  --blue-soft:#e8f0fd;
  --ink:#14213a;
  --muted:#5d6f8a;
  --ok:#0f8a5f;
  --warn:#a35f00;
  --bad:#c2362f;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.65 'Plus Jakarta Sans',system-ui,sans-serif}
a{color:inherit;text-decoration:none}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px}
.wrap{max-width:860px;margin:0 auto;padding:0 24px}

/* header */
header{background:var(--surface);border-bottom:1px solid var(--line)}
.headbar{display:flex;align-items:center;justify-content:space-between;height:64px}
.brand{font-weight:700;font-size:18px;color:var(--blue)}
.navlinks{display:flex;gap:6px}
.navlinks a{font-size:15px;font-weight:500;color:var(--muted);padding:6px 14px;border-radius:6px}
.navlinks a:hover{background:var(--blue-soft);color:var(--blue)}
.navlinks a.active{background:var(--blue-soft);color:var(--blue)}

main{padding-bottom:80px}

/* pencarian */
.intro{padding:72px 0 8px}
.intro h1{font-size:clamp(28px,4.5vw,42px);line-height:1.2;margin:0 0 12px;font-weight:700;max-width:18ch}
.intro .lead{color:var(--muted);margin:0 0 28px;max-width:48ch}
.searchrow{display:flex;gap:10px}
.searchrow input{flex:1;min-width:0;background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:14px 16px;font:inherit;color:var(--ink)}
.searchrow input::placeholder{color:#93a1b8}
.searchrow input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(31,95,214,.15)}
.btn,.searchrow button{background:var(--blue);color:#fff;border:0;border-radius:8px;padding:0 24px;font:600 15px 'Plus Jakarta Sans',sans-serif;cursor:pointer}
.btn:hover,.searchrow button:hover{background:var(--blue-dark)}
.chips{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:16px;color:var(--muted);font-size:14px}
.chip{background:var(--surface);border:1px solid var(--line);border-radius:999px;padding:4px 14px;font-size:14px}
.chip:hover{border-color:var(--blue);color:var(--blue)}
.searchbar{margin-top:32px}

/* hasil */
.count{font-size:14px;color:var(--muted);margin:28px 0 10px}
.list{background:var(--surface);border:1px solid var(--line);border-radius:10px;overflow:hidden}
.board-row{display:flex;align-items:center;gap:18px;padding:16px 20px;border-bottom:1px solid var(--line)}
.board-row:last-child{border-bottom:0}
.board-row:hover{background:var(--blue-soft)}
.code{font-weight:700;font-size:20px;color:var(--blue);min-width:56px}
.row-name{font-weight:600}
.row-sub{color:var(--muted);font-size:14px}

.state{background:var(--surface);border:1px dashed var(--line);border-radius:10px;padding:20px;color:var(--muted);margin:24px 0}

/* detail */
.hero-detail{position:relative;border-radius:12px;overflow:hidden;margin-top:32px;min-height:240px;display:flex;align-items:flex-end;background:var(--blue-soft);border:1px solid var(--line)}
.hero-detail img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.hero-fade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,25,60,.05) 20%,rgba(10,25,60,.75) 100%)}
.hero-text{position:relative;padding:28px 32px;width:100%}
.hero-code{font-weight:700;font-size:44px;line-height:1;color:var(--blue)}
.hero-text h1{margin:8px 0 4px;font-size:clamp(22px,3vw,30px);line-height:1.25}
.hero-text .sub{color:var(--muted);font-size:15px}
.has-foto .hero-code,.has-foto h1{color:#fff}
.has-foto .sub{color:rgba(255,255,255,.85)}

.facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1px;background:var(--line);border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-top:20px}
.fact{background:var(--surface);padding:14px 18px}
.fact .k{color:var(--muted);font-size:13px}
.fact .v{font-weight:600;font-size:17px;margin-top:2px}

.link{color:var(--blue);font-weight:500}
.link:hover{text-decoration:underline}
.linkrow{display:flex;flex-wrap:wrap;gap:20px;margin-top:16px}

h2.section{font-size:19px;font-weight:700;margin:40px 0 12px}
.maskapai-list{display:flex;flex-wrap:wrap;gap:8px}
.tag{background:var(--surface);border:1px solid var(--line);border-radius:6px;padding:6px 12px;font-size:14px}

.mapbox{border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-top:14px}
.mapbox iframe{width:100%;height:300px;display:block;border:0}

.scroll{overflow-x:auto;background:var(--surface);border:1px solid var(--line);border-radius:10px}
table{width:100%;border-collapse:collapse;font-size:14px}
th{text-align:left;padding:12px 16px;color:var(--muted);font-weight:600;border-bottom:1px solid var(--line);background:var(--bg);white-space:nowrap}
td{padding:12px 16px;border-bottom:1px solid var(--line)}
tr:last-child td{border-bottom:0}
.status{padding:2px 10px;border-radius:999px;font-size:13px;display:inline-block}
.s-active,.s-landed{background:#e3f6ee;color:var(--ok)}
.s-scheduled{background:var(--blue-soft);color:var(--blue)}
.s-cancelled,.s-diverted{background:#fdeae9;color:var(--bad)}
.s-delayed,.s-incident{background:#fff1da;color:var(--warn)}

/* rute */
.panel{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:28px;margin-top:32px}
.panel h1{font-size:24px;margin:0 0 6px}
.panel .lead{color:var(--muted);margin:0 0 20px}
.routeform{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end}
.routeform label{display:block;color:var(--muted);font-size:13px;margin-bottom:6px}
.routeform select{width:100%;background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:12px;color:var(--ink);font:inherit;font-size:15px}
.routeform select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(31,95,214,.15)}
.routeform .btn{padding:13px 24px}
#peta-rute{margin-top:16px;height:420px;border:1px solid var(--line);border-radius:10px}

footer{border-top:1px solid var(--line);background:var(--surface);padding:20px 0;color:var(--muted);font-size:14px}

@media(max-width:600px){
  .intro{padding-top:44px}
  .searchrow{flex-direction:column}
  .searchrow button{padding:13px}
  .routeform{grid-template-columns:1fr}
  .hero-text{padding:22px}
  .code{font-size:18px;min-width:48px}
}
</style>
</head>
<body>
<header><div class="wrap headbar">
  <a class="brand" href="{{ route('home') }}">Bandara Indonesia</a>
  <nav class="navlinks">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Cari</a>
    <a href="{{ route('route.index') }}" class="{{ request()->routeIs('route.index') ? 'active' : '' }}">Rute</a>
  </nav>
</div></header>
<main class="wrap">@yield('content')</main>
<footer><div class="wrap">INDONESIA EMAS 2029?</div></footer>
@stack('scripts')
</body>
</html>
