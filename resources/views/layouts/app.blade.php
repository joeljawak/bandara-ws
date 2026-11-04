<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'BANDARA ASEAN')</title>
@yield('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#ffffff;
  --soft:#f6f7fb;
  --line:#e6e9f0;
  --blue:#1d3f9e;
  --blue-dark:#142f78;
  --blue-soft:#eef2fc;
  --red:#d62839;
  --yellow:#f7c600;
  --yellow-soft:#fff6cc;
  --ink:#1f2433;
  --muted:#667085;
  --ok:#0f8a5f;
  --warn:#a35f00;
  --radius:14px;
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;display:flex;flex-direction:column;background:var(--bg);color:var(--ink);font:16px/1.65 'Plus Jakarta Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
body::before{content:"";display:block;height:4px;background:linear-gradient(90deg,var(--blue) 0 34%,var(--red) 34% 67%,var(--yellow) 67% 100%)}
a{color:inherit;text-decoration:none}
img,svg{max-width:100%}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px}
.wrap{max-width:900px;margin:0 auto;padding:0 24px;width:100%}
main{flex:1;padding-bottom:72px}

/* header */
.headbar{display:flex;align-items:center;justify-content:space-between;height:60px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:15px;letter-spacing:.06em;white-space:nowrap}
.brand .b1{color:var(--blue)}
.brand .b2{color:var(--red)}
.dots{display:flex;gap:4px}
.dots i{width:9px;height:9px;border-radius:50%;display:block}
.dots i:nth-child(1){background:var(--blue)}
.dots i:nth-child(2){background:var(--red)}
.dots i:nth-child(3){background:var(--yellow)}
.navlinks{display:flex;gap:2px}
.navlinks a{font-size:14px;font-weight:500;color:var(--muted);padding:6px 12px;border-radius:8px}
.navlinks a:hover{color:var(--blue);background:var(--blue-soft)}
.navlinks a.active{color:var(--blue);font-weight:600}
.is-home .brand{visibility:hidden}

/* kotak cari */
.search{position:relative;display:flex;align-items:center;width:100%;background:#fff;border:1px solid #d5dae5;border-radius:999px;box-shadow:0 1px 6px rgba(31,36,51,.08);transition:box-shadow .15s,border-color .15s}
.search:hover{box-shadow:0 2px 10px rgba(31,36,51,.12)}
.search:focus-within{border-color:var(--blue);box-shadow:0 2px 12px rgba(29,63,158,.18)}
.search input{flex:1;min-width:0;height:54px;border:0;background:transparent;padding:0 8px 0 22px;font:inherit;font-size:17px;color:var(--ink)}
.search input:focus{outline:none}
.search input::placeholder{color:#98a2b3}
.search button{flex:none;width:42px;height:42px;margin-right:6px;border:0;border-radius:50%;background:var(--blue);color:#fff;cursor:pointer;display:grid;place-items:center;transition:background .15s}
.search button:hover{background:var(--blue-dark)}
.search svg{display:block}

/* tombol dan form */
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;background:var(--blue);color:#fff;border:0;border-radius:10px;padding:0 26px;font:600 15px 'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:background .15s}
.btn:hover{background:var(--blue-dark)}
.field label{display:block;color:var(--muted);font-size:13px;font-weight:500;margin-bottom:6px}
.field input,.field select{width:100%;min-height:46px;background:#fff;border:1px solid #d5dae5;border-radius:10px;padding:0 14px;font:inherit;color:var(--ink)}
.field input:focus,.field select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(29,63,158,.14)}

/* beranda */
.home{min-height:calc(100vh - 200px);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px 0 40px}
.logo{font-weight:800;font-size:clamp(34px,7vw,56px);letter-spacing:.04em;line-height:1.1;margin:0}
.logo .b1{color:var(--blue)}
.logo .b2{color:var(--red)}
.logo-dots{display:flex;justify-content:center;gap:7px;margin-bottom:18px}
.logo-dots i{width:13px;height:13px;border-radius:50%;display:block}
.logo-dots i:nth-child(1){background:var(--blue)}
.logo-dots i:nth-child(2){background:var(--red)}
.logo-dots i:nth-child(3){background:var(--yellow)}
.tagline{color:var(--muted);margin:10px 0 30px}
.home .search{max-width:640px}
.chips{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:8px;margin-top:22px;color:var(--muted);font-size:14px}
.chip{border:1px solid var(--line);border-radius:999px;padding:3px 14px;font-size:14px;color:var(--ink);transition:border-color .15s,color .15s,background .15s}
.chip:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-soft)}

/* judul halaman */
.top-search{margin-top:20px;max-width:640px}
.page-head{margin:30px 0 4px;display:flex;align-items:baseline;justify-content:space-between;gap:16px}
.page-head h1{font-size:24px;line-height:1.3;margin:0;font-weight:800}
.count{color:var(--muted);font-size:14px}
.filter{display:grid;grid-template-columns:1fr 220px auto;gap:12px;align-items:end;margin-top:28px;padding-bottom:24px;border-bottom:1px solid var(--line)}

/* daftar hasil seperti mesin pencari */
.list{margin-top:6px}
.res{display:grid;grid-template-columns:minmax(0,1fr) 104px;gap:20px;align-items:start;padding:20px 0;border-bottom:1px solid var(--line)}
.res:last-child{border-bottom:0}
.res-top{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px;margin-bottom:2px}
.code{background:var(--yellow);color:#2b2200;font-weight:800;font-size:12px;letter-spacing:.06em;padding:1px 9px;border-radius:6px}
.res-name{font-size:19px;font-weight:600;line-height:1.35;color:var(--blue)}
.res:hover .res-name{text-decoration:underline}
.res-sub{color:var(--muted);font-size:14.5px;margin-top:3px}
.thumb{width:104px;height:76px;border-radius:10px;overflow:hidden;background:var(--soft);border:1px solid var(--line)}
.thumb svg,.thumb img{display:block;width:100%;height:100%}
.thumb img{object-fit:cover}

.state{border:1px dashed #cdd3e0;border-radius:var(--radius);padding:22px;color:var(--muted);margin:24px 0;background:var(--soft)}

/* detail */
.back{display:inline-block;margin:24px 0 8px;color:var(--muted);font-size:14px;font-weight:500}
.back:hover{color:var(--blue)}
.entity{margin:6px 0 20px}
.entity .code{font-size:13px;padding:2px 11px}
.entity h1{margin:10px 0 2px;font-size:clamp(26px,4vw,36px);line-height:1.2;font-weight:800;letter-spacing:-.01em}
.entity p{margin:0;color:var(--muted)}
.visual{position:relative;overflow:hidden;border-radius:var(--radius);border:1px solid var(--line);background:var(--soft);aspect-ratio:1200/420;min-height:200px}
.visual svg,.visual img{position:absolute;inset:0;width:100%;height:100%}
.visual img{object-fit:cover}
.credit{position:absolute;right:12px;bottom:12px;font-size:12px;color:#fff;background:rgba(20,28,48,.6);padding:3px 10px;border-radius:999px}
.credit:hover{background:rgba(20,28,48,.85)}
.facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 36px;margin-top:20px}
.fact{padding:12px 0;border-bottom:1px solid var(--line)}
.fact .k{color:var(--muted);font-size:13px}
.fact .v{font-weight:600;font-size:16px;word-break:break-word}
.linkrow{display:flex;flex-wrap:wrap;gap:22px;margin-top:18px}
.link{color:var(--blue);font-weight:600;font-size:15px}
.link:hover{text-decoration:underline}
.section{font-size:19px;font-weight:800;margin:40px 0 12px}
.mapbox{margin-top:24px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--line)}
.mapbox iframe{display:block;width:100%;height:320px;border:0}

.scroll{overflow-x:auto;border:1px solid var(--line);border-radius:var(--radius)}
table{width:100%;border-collapse:collapse;font-size:15px}
th{text-align:left;padding:11px 16px;color:var(--muted);font-size:13px;font-weight:600;border-bottom:1px solid var(--line);background:var(--soft);white-space:nowrap}
td{padding:11px 16px;border-bottom:1px solid var(--line)}
tr:last-child td{border-bottom:0}
.status{padding:2px 10px;border-radius:999px;font-size:13px;font-weight:600;display:inline-block}
.s-active,.s-landed{background:#e3f6ee;color:var(--ok)}
.s-scheduled{background:var(--blue-soft);color:var(--blue)}
.s-cancelled,.s-diverted{background:#fde8ea;color:var(--red)}
.s-delayed,.s-incident{background:var(--yellow-soft);color:var(--warn)}

/* rute */
.panel{border:1px solid var(--line);border-radius:var(--radius);padding:26px;margin-top:28px;background:#fff}
.panel h1{font-size:24px;margin:0 0 4px;font-weight:800}
.panel .lead{color:var(--muted);margin:0 0 20px}
.routeform{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end}
.facts.compact{margin-top:20px}
#peta-rute{margin-top:16px;height:420px;border:1px solid var(--line);border-radius:var(--radius)}

footer{border-top:1px solid var(--line);padding:18px 0;color:var(--muted);font-size:13px;text-align:center}

@media(max-width:720px){
  .wrap{padding:0 18px}
  .filter,.routeform{grid-template-columns:1fr}
  .page-head{flex-direction:column;gap:4px}
  .res{grid-template-columns:minmax(0,1fr) 84px;gap:14px}
  .thumb{width:84px;height:62px}
  .navlinks a{padding:6px 9px}
}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body class="@yield('bodyclass')">
<header><div class="wrap headbar">
  <a class="brand" href="{{ route('home') }}" aria-label="BANDARA ASEAN - beranda">
    <span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>
    <span><span class="b1">BANDARA</span> <span class="b2">ASEAN</span></span>
  </a>
  <nav class="navlinks" aria-label="Menu utama">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home', 'search') ? 'active' : '' }}">Cari</a>
    <a href="{{ route('bandara.index') }}" class="{{ request()->routeIs('bandara.*') ? 'active' : '' }}">Jelajahi</a>
    <a href="{{ route('route.index') }}" class="{{ request()->routeIs('route.index') ? 'active' : '' }}">Rute</a>
  </nav>
</div></header>
<main class="wrap">@yield('content')</main>
<footer><div class="wrap">BANDARA ASEAN &middot; Tugas besar Web Semantik</div></footer>
@stack('scripts')
</body>
</html>
