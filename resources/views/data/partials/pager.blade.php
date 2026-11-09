@if ($paginator->hasPages())
  <nav class="pager" aria-label="Navigasi halaman">
    @if ($paginator->onFirstPage())
      <span class="pager-disabled">&laquo; Sebelumnya</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Sebelumnya</a>
    @endif

    <span class="pager-info">
      Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
      &middot; {{ number_format($paginator->total(), 0, ',', '.') }} data
    </span>

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya &raquo;</a>
    @else
      <span class="pager-disabled">Berikutnya &raquo;</span>
    @endif
  </nav>
@endif
