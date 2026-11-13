{{-- Navigasi halaman dengan nomor, Sebelumnya/Berikutnya. Parameter: $paginator --}}
@if ($paginator->hasPages())
  @php
      $current = $paginator->currentPage();
      $last = $paginator->lastPage();
      $start = max(1, $current - 2);
      $end = min($last, $current + 2);
  @endphp

  <nav class="pager" aria-label="Navigasi halaman">
    @if ($paginator->onFirstPage())
      <span class="pager-disabled" aria-disabled="true">&laquo; Sebelumnya</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Sebelumnya</a>
    @endif

    @if ($start > 1)
      <a class="pager-num" href="{{ $paginator->url(1) }}" aria-label="Halaman 1">1</a>
      @if ($start > 2)
        <span class="pager-gap" aria-hidden="true">&hellip;</span>
      @endif
    @endif

    @for ($page = $start; $page <= $end; $page++)
      @if ($page === $current)
        <span class="pager-num pager-current" aria-current="page">{{ $page }}</span>
      @else
        <a class="pager-num" href="{{ $paginator->url($page) }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
      @endif
    @endfor

    @if ($end < $last)
      @if ($end < $last - 1)
        <span class="pager-gap" aria-hidden="true">&hellip;</span>
      @endif
      <a class="pager-num" href="{{ $paginator->url($last) }}" aria-label="Halaman {{ $last }}">{{ $last }}</a>
    @endif

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya &raquo;</a>
    @else
      <span class="pager-disabled" aria-disabled="true">Berikutnya &raquo;</span>
    @endif

    <span class="pager-info">
      Halaman {{ $current }} dari {{ $last }}
      &middot; {{ number_format($paginator->total(), 0, ',', '.') }} bandara
    </span>
  </nav>
@endif
