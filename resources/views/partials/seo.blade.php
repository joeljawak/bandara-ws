@php
    $seo = array_merge([
        'description' => 'BANDARA ASEAN: cari bandara, maskapai, dan rute penerbangan di Asia Tenggara. Data RDF, peta interaktif, dan halaman detail tiap bandara.',
        'canonical' => url()->current(),
        'robots' => 'index,follow',
        'type' => 'website',
        'image' => null,
        'jsonld' => null,
    ], $seo ?? []);

    $judulOg = trim($__env->yieldContent('title')) ?: 'BANDARA ASEAN';
@endphp
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:site_name" content="BANDARA ASEAN">
<meta property="og:locale" content="id_ID">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $judulOg }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@if ($seo['image'])
<meta property="og:image" content="{{ $seo['image'] }}">
@endif
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $judulOg }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
@if ($seo['image'])
<meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
@if ($seo['jsonld'])
<script type="application/ld+json">{!! json_encode($seo['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
