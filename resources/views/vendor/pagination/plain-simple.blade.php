{{-- Simple pagination (previous/next only), same vocabulary as plain.blade.php. --}}

@if ($paginator->hasPages())
    <nav class="pg" role="navigation" aria-label="Pagination">
        <p class="pg__info">page <b>{{ $paginator->currentPage() }}</b></p>
        <div class="pg__links">
            @if ($paginator->onFirstPage())
                <span class="is-disabled" aria-disabled="true">← previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">next →</a>
            @else
                <span class="is-disabled" aria-disabled="true">next →</span>
            @endif
        </div>
    </nav>
@endif
