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
  --bg:#f4f8fd;
  --soft:#f1f6fc;
  --line:#e2eaf4;
  --blue:#0194f3;
  --blue-dark:#0064d2;
  --blue-deep:#0b2e59;
  --blue-soft:#e7f4ff;
  --accent:#ff5e1f;
  --accent-soft:#fff1ea;
  --red:#ff5e1f;
  --yellow:#ffb703;
  --yellow-soft:#fff6e0;
  --ink:#16233a;
  --muted:#6b7a90;
  --ok:#0f9d6c;
  --warn:#b7791f;
  --radius:16px;
  --shadow:0 2px 10px rgba(22,35,58,.05);
  --shadow-lg:0 16px 36px rgba(11,46,89,.16);
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;display:flex;flex-direction:column;background:var(--bg);color:var(--ink);font:16px/1.65 'Plus Jakarta Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
/* pendar lembut + bayangan pesawat sebagai latar halaman */
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:
  radial-gradient(900px 520px at 100% -8%,rgba(1,148,243,.12),transparent 62%),
  radial-gradient(760px 460px at -8% 12%,rgba(255,94,31,.07),transparent 60%)}
body::after{content:"";position:fixed;right:-70px;top:90px;width:340px;height:340px;z-index:-1;pointer-events:none;opacity:.05;transform:rotate(18deg);background:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230194f3'><path d='M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z'/></svg>") center/contain no-repeat}
a{color:inherit;text-decoration:none}
img,svg{max-width:100%}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px}
.wrap{max-width:1080px;margin:0 auto;padding:0 24px;width:100%}
main{flex:1;padding-bottom:72px}

/* header */
header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.86);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid rgba(226,234,244,.9)}
.headbar{display:flex;align-items:center;justify-content:space-between;height:66px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:15px;letter-spacing:.06em;white-space:nowrap}
.brand .b1{color:var(--blue)}
.brand .b2{color:var(--accent)}
.dots{display:flex;gap:4px}
.dots i{width:9px;height:9px;border-radius:50%;display:block}
.dots i:nth-child(1){background:var(--blue)}
.dots i:nth-child(2){background:var(--accent)}
.dots i:nth-child(3){background:var(--yellow)}
.navlinks{display:flex;gap:4px}
.navlinks a{font-size:14px;font-weight:600;color:var(--muted);padding:7px 14px;border-radius:999px;transition:color .15s,background .15s}
.navlinks a:hover{color:var(--blue);background:var(--blue-soft)}
.navlinks a.active{color:var(--blue);background:var(--blue-soft)}

/* kotak cari */
.search{position:relative;display:flex;align-items:center;width:100%;background:#fff;border:1px solid #dbe6f2;border-radius:999px;box-shadow:0 6px 18px rgba(11,46,89,.08);transition:box-shadow .18s,border-color .18s,transform .18s}
.search:hover{box-shadow:0 10px 26px rgba(11,46,89,.14)}
.search:focus-within{border-color:var(--blue);box-shadow:0 0 0 4px rgba(1,148,243,.16),0 10px 26px rgba(11,46,89,.14)}
.search input{flex:1;min-width:0;height:58px;border:0;background:transparent;padding:0 8px 0 24px;font:inherit;font-size:17px;color:var(--ink);border-radius:999px}
.search input:focus{outline:none}
.search input::placeholder{color:#9aa9bd}
.search button{flex:none;width:46px;height:46px;margin-right:6px;border:0;border-radius:50%;background:linear-gradient(135deg,var(--accent),#ff7a45);color:#fff;cursor:pointer;display:grid;place-items:center;box-shadow:0 6px 14px rgba(255,94,31,.35);transition:transform .15s,box-shadow .15s}
.search button:hover{transform:scale(1.05);box-shadow:0 8px 18px rgba(255,94,31,.45)}
.search svg{display:block}

/* tombol dan form */
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;background:linear-gradient(135deg,var(--blue),var(--blue-dark));color:#fff;border:0;border-radius:999px;padding:0 28px;font:700 15px 'Plus Jakarta Sans',sans-serif;cursor:pointer;box-shadow:0 8px 20px rgba(1,100,210,.24);transition:transform .15s,box-shadow .15s,filter .15s}
.btn:hover{transform:translateY(-1px);box-shadow:0 12px 26px rgba(1,100,210,.3);filter:brightness(1.03)}
.field label{display:block;color:var(--muted);font-size:13px;font-weight:600;margin-bottom:6px}
.field input,.field select{width:100%;min-height:48px;background:#fff;border:1px solid #dbe6f2;border-radius:12px;padding:0 14px;font:inherit;color:var(--ink);transition:border-color .15s,box-shadow .15s}
.field input:focus,.field select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 4px rgba(1,148,243,.15)}

/* hero beranda */
.hero{position:relative;overflow:hidden;isolation:isolate;margin:24px 0 0;min-height:460px;display:flex;align-items:center;justify-content:center;text-align:center;padding:64px 28px 58px;border-radius:28px;color:#fff;box-shadow:var(--shadow-lg);
  background:#0b2e59 url('https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat}
.hero::before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(4,28,58,.78),rgba(4,54,110,.6) 52%,rgba(1,148,243,.32))}
.hero-inner{width:100%;max-width:700px;margin:0 auto}
.hero-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);color:#fff;font-size:13px;font-weight:600;letter-spacing:.02em;padding:5px 15px;border-radius:999px;margin-bottom:18px;backdrop-filter:blur(6px)}
.hero-eyebrow svg{width:15px;height:15px}
.hero h1{margin:0;font-size:clamp(32px,5.6vw,54px);line-height:1.08;font-weight:800;letter-spacing:-.02em;text-shadow:0 4px 24px rgba(4,28,58,.45)}
.hero .logo-dots{display:flex;justify-content:center;gap:7px;margin-bottom:16px}
.hero .logo-dots i{width:13px;height:13px;border-radius:50%;display:block;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.hero .logo-dots i:nth-child(1){background:var(--blue)}
.hero .logo-dots i:nth-child(2){background:var(--accent)}
.hero .logo-dots i:nth-child(3){background:var(--yellow)}
.hero .b1{color:#fff}
.hero .b2{color:#ffd166}
.hero .tagline{color:rgba(255,255,255,.9);margin:14px 0 30px;font-size:clamp(15px,2.2vw,18px)}
.hero .search{max-width:640px;margin:0 auto;border:0;box-shadow:0 18px 38px rgba(4,28,58,.4)}
.hero .chips{color:rgba(255,255,255,.8)}
.hero .chip{border-color:rgba(255,255,255,.42);color:#fff;background:rgba(255,255,255,.1);backdrop-filter:blur(4px)}
.hero .chip:hover{border-color:#fff;color:var(--blue-deep);background:#fff}
.hero-stats{display:flex;flex-wrap:wrap;justify-content:center;gap:10px 26px;margin-top:28px;font-size:13.5px;color:rgba(255,255,255,.85)}
.hero-stats strong{color:#fff;font-weight:700}

/* beranda versi lama tetap aman */
.home{min-height:calc(100vh - 200px);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px 0 40px}
.logo{font-weight:800;font-size:clamp(34px,7vw,56px);letter-spacing:.04em;line-height:1.1;margin:0}
.logo .b1{color:var(--blue)}
.logo .b2{color:var(--accent)}
.logo-dots{display:flex;justify-content:center;gap:7px;margin-bottom:18px}
.logo-dots i{width:13px;height:13px;border-radius:50%;display:block}
.logo-dots i:nth-child(1){background:var(--blue)}
.logo-dots i:nth-child(2){background:var(--accent)}
.logo-dots i:nth-child(3){background:var(--yellow)}
.tagline{color:var(--muted);margin:10px 0 30px}
.home .search{max-width:640px}
.chips{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:8px;margin-top:22px;color:var(--muted);font-size:14px}
.chip{border:1px solid var(--line);border-radius:999px;padding:5px 15px;font-size:14px;font-weight:500;color:var(--ink);background:#fff;transition:border-color .15s,color .15s,background .15s,transform .15s}
.chip:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-soft);transform:translateY(-1px)}

/* judul halaman */
.top-search{margin-top:22px;max-width:640px}
.page-head{margin:34px 0 6px;display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap}
.page-head h1{font-size:26px;line-height:1.25;margin:0;font-weight:800;letter-spacing:-.01em}
.count{color:var(--muted);font-size:14px}
.filter{display:grid;grid-template-columns:1fr 220px auto;gap:14px;align-items:end;margin-top:24px;padding:18px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:var(--shadow)}
.filter.wide{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}

/* daftar hasil */
.list{display:grid;gap:12px;margin-top:18px}
.res{display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:20px;align-items:center;padding:18px 20px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow);transition:transform .16s,box-shadow .16s,border-color .16s}
.res:hover{transform:translateY(-2px);border-color:#bfe3ff;box-shadow:0 14px 30px rgba(1,100,210,.14)}
.res-top{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px;margin-bottom:3px;flex-wrap:wrap}
.code{background:linear-gradient(135deg,var(--blue),var(--blue-dark));color:#fff;font-weight:800;font-size:12px;letter-spacing:.08em;padding:2px 10px;border-radius:8px}
.res-name{font-size:19px;font-weight:700;line-height:1.35;color:var(--blue-deep)}
.res:hover .res-name{color:var(--blue-dark)}
.res-sub{color:var(--muted);font-size:14.5px;margin-top:3px}
.thumb{width:120px;height:86px;border-radius:12px;overflow:hidden;background:var(--soft);border:1px solid var(--line)}
.thumb svg,.thumb img{display:block;width:100%;height:100%}
.thumb img{object-fit:cover}

.state{border:1px solid #d8e9fa;border-radius:var(--radius);padding:22px;color:var(--muted);margin:24px 0;background:linear-gradient(180deg,#fff,var(--blue-soft))}

/* detail */
.back{display:inline-flex;align-items:center;gap:6px;margin:24px 0 10px;padding:6px 14px;border-radius:999px;background:#fff;border:1px solid var(--line);color:var(--muted);font-size:14px;font-weight:600;transition:color .15s,border-color .15s,background .15s}
.back:hover{color:var(--blue);border-color:var(--blue);background:var(--blue-soft)}
.entity{margin:6px 0 20px}
.entity .code{font-size:13px;padding:3px 12px}
.entity h1{margin:10px 0 2px;font-size:clamp(26px,4vw,38px);line-height:1.18;font-weight:800;letter-spacing:-.02em}
.entity p{margin:0;color:var(--muted)}
.visual{position:relative;overflow:hidden;border-radius:20px;border:1px solid var(--line);background:var(--soft);aspect-ratio:1200/420;min-height:200px;box-shadow:var(--shadow)}
.visual svg,.visual img{position:absolute;inset:0;width:100%;height:100%}
.visual img{object-fit:cover}
.credit{position:absolute;right:12px;bottom:12px;font-size:12px;color:#fff;background:rgba(11,46,89,.62);padding:3px 10px;border-radius:999px}
.credit:hover{background:rgba(11,46,89,.88)}
.facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 36px;margin-top:20px;padding:6px 20px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:var(--shadow)}
.fact{padding:14px 0;border-bottom:1px solid var(--line)}
.fact .k{color:var(--muted);font-size:13px}
.fact .v{font-weight:700;font-size:16px;word-break:break-word}
.linkrow{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.link{color:var(--blue-dark);font-weight:700;font-size:15px}
.link:hover{color:var(--accent)}
.linkrow .link{background:#fff;border:1px solid var(--line);border-radius:999px;padding:7px 16px;font-size:14px;transition:border-color .15s,background .15s,color .15s}
.linkrow .link:hover{border-color:var(--blue);background:var(--blue-soft)}
.section{display:flex;align-items:center;gap:10px;font-size:20px;font-weight:800;margin:42px 0 14px;letter-spacing:-.01em}
.section::before{content:"";width:5px;height:22px;border-radius:3px;background:linear-gradient(180deg,var(--blue),var(--accent))}
.mapbox{margin-top:24px;border-radius:20px;overflow:hidden;border:1px solid var(--line);box-shadow:var(--shadow)}
.mapbox iframe{display:block;width:100%;height:320px;border:0}

.scroll{overflow-x:auto;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow)}
table{width:100%;border-collapse:collapse;font-size:15px}
th{text-align:left;padding:13px 18px;color:var(--muted);font-size:12.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-bottom:1px solid var(--line);background:var(--soft);white-space:nowrap}
td{padding:13px 18px;border-bottom:1px solid var(--line);vertical-align:middle}
tr:last-child td{border-bottom:0}
tbody tr{transition:background .12s}
tbody tr:hover{background:#f5faff}
.status{padding:3px 12px;border-radius:999px;font-size:13px;font-weight:700;display:inline-block}
.s-active,.s-landed{background:#e3f6ee;color:var(--ok)}
.s-scheduled{background:var(--blue-soft);color:var(--blue-dark)}
.s-cancelled,.s-diverted{background:#fde8ea;color:#d62839}
.s-delayed,.s-incident{background:var(--yellow-soft);color:var(--warn)}

/* rute */
.panel{border:1px solid var(--line);border-radius:20px;padding:28px;margin-top:28px;background:#fff;box-shadow:var(--shadow)}
.panel h1{font-size:26px;margin:0 0 4px;font-weight:800;letter-spacing:-.01em}
.panel .lead{color:var(--muted);margin:0 0 22px}
.routeform{display:grid;grid-template-columns:1fr 1fr auto;gap:14px;align-items:end}
.facts.compact{margin-top:20px}
#peta-rute{margin-top:16px;height:420px;border:1px solid var(--line);border-radius:18px}

/* peta dan citra satelit */
.visual,.mapbox{isolation:isolate}
#hero-sat{position:absolute;inset:0;background:#cfd8e6}
#peta{height:340px;background:#e6ebf3}
.thumb img.sat{max-width:none}

.about{margin:0 0 6px;max-width:72ch}
.about-src{margin:0;color:var(--muted);font-size:13.5px}

/* data relasional hasil impor RDF */
.pager{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px;margin:28px 0 8px;font-size:14px}
.pager a,.pager .pager-disabled{border:1px solid var(--line);border-radius:999px;padding:7px 16px;font-weight:700;color:var(--blue-dark);background:#fff}
.pager a:hover{border-color:var(--blue);background:var(--blue-soft);color:var(--blue)}
.pager .pager-disabled{color:#9aa9bd;background:var(--soft)}
.pager .pager-info{color:var(--muted);font-weight:500}
.badge{display:inline-block;background:var(--blue-soft);color:var(--blue-dark);font-size:12px;font-weight:700;padding:2px 10px;border-radius:999px;letter-spacing:.03em}
.badge.asean{background:var(--accent-soft);color:var(--accent)}
.mapbig{height:560px;border:1px solid var(--line);border-radius:20px;margin-top:18px;background:#e6ebf3;box-shadow:var(--shadow)}
.statrow{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-top:24px}
.statbox{position:relative;overflow:hidden;border:1px solid var(--line);border-radius:16px;padding:18px 20px;background:#fff;box-shadow:var(--shadow)}
.statbox::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,var(--blue),var(--accent))}
.statbox .n{font-size:26px;font-weight:800;line-height:1.2;color:var(--blue-deep)}
.statbox .k{color:var(--muted);font-size:13px}

/* tim pengembang */
.tim{border-top:1px solid var(--line);padding:56px 0 24px;text-align:center;margin-top:56px}
.tim h2{margin:0;font-size:22px;font-weight:800}
.tim-sub{margin:4px 0 28px;color:var(--muted);font-size:14.5px}
.tim-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;justify-content:center;gap:28px 36px}
.tim-item{display:flex;flex-direction:column;align-items:center;width:132px;gap:2px}
.tim-foto{width:92px;height:92px;border-radius:50%;object-fit:cover;margin-bottom:10px;border:3px solid #fff;box-shadow:0 0 0 2px var(--line),0 6px 16px rgba(11,46,89,.1)}
.tim-inisial{display:grid;place-items:center;font-weight:800;font-size:28px;color:#fff;object-fit:initial}
.tim-inisial.c0{background:linear-gradient(135deg,var(--blue),var(--blue-dark))}
.tim-inisial.c1{background:linear-gradient(135deg,var(--accent),#ff8a5c)}
.tim-inisial.c2{background:linear-gradient(135deg,var(--yellow),#ffcf4d);color:#4a3500}
.tim-item strong{font-size:15px;line-height:1.35}
.tim-nim{color:var(--muted);font-size:13px}
.tim-peran{margin-top:4px;font-size:12px;font-weight:700;color:var(--blue-dark);background:var(--blue-soft);padding:2px 10px;border-radius:999px}

footer{border-top:1px solid var(--line);padding:20px 0;color:var(--muted);font-size:13px;text-align:center;background:#fff}

@media(max-width:720px){
  .wrap{padding:0 18px}
  .filter,.routeform{grid-template-columns:1fr}
  .page-head{flex-direction:column;gap:4px}
  .res{grid-template-columns:minmax(0,1fr) 92px;gap:14px;padding:16px}
  .thumb{width:92px;height:68px}
  .navlinks a{padding:7px 10px;font-size:13px}
  .hero{min-height:420px;border-radius:22px;padding:48px 20px}
  .hero-stats{gap:8px 18px;font-size:12.5px}
  .facts{padding:6px 16px}
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
    <a href="{{ route('data.airports') }}" class="{{ request()->routeIs('data.*') ? 'active' : '' }}">Data</a>
  </nav>
</div></header>
<main class="wrap">@yield('content')</main>
<footer><div class="wrap">BANDARA ASEAN &middot; Tugas besar Web Semantik<br>Foto: Wikipedia dan Wikimedia Commons &middot; Citra satelit: Esri, Maxar, Earthstar Geographics &middot; Peta: OpenStreetMap</div></footer>
@include('partials.chat')
@stack('scripts')
</body>
</html>
