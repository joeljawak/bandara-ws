{{-- ilustrasi dekoratif radar + landasan, dipakai sebagai pengganti foto kalau foto tidak ada --}}
<svg viewBox="0 0 400 400" class="radar-svg" role="img" aria-label="Ilustrasi radar bandara">
  <circle cx="200" cy="200" r="170" class="radar-ring"/>
  <circle cx="200" cy="200" r="120" class="radar-ring"/>
  <circle cx="200" cy="200" r="70" class="radar-ring"/>
  <line x1="30" y1="200" x2="370" y2="200" class="radar-ring"/>
  <line x1="200" y1="30" x2="200" y2="370" class="radar-ring"/>
  <g class="radar-sweep">
    <path d="M200 200 L200 30 A170 170 0 0 1 347 115 Z" fill="url(#sweepGrad)"/>
  </g>
  <defs>
    <linearGradient id="sweepGrad" x1="200" y1="30" x2="347" y2="115" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="var(--amber)" stop-opacity=".55"/>
      <stop offset="1" stop-color="var(--amber)" stop-opacity="0"/>
    </linearGradient>
  </defs>
  <circle cx="120" cy="150" r="3" class="radar-dot"/>
  <circle cx="260" cy="260" r="3" class="radar-dot"/>
  <circle cx="230" cy="140" r="3" class="radar-dot"/>
</svg>
