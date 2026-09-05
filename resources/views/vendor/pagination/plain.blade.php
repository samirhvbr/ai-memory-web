{{-- Pagination.

     Replaces Laravel's default view (pagination::tailwind), whose utility
     classes and Tailwind-sized SVGs render wrong in an app that does not load
     Tailwind — the `<svg class="w-5 h-5">` arrow comes out the size of its
     container. Registered in AppServiceProvider::usePlainPagination(). --}}

@if ($paginator->hasPages())
    <nav class="pg" role="navigation" aria-label="Pagination">
        <p class="pg__info">
            @if($paginator->total() > 0)
                <b>{{ $paginator->firstItem() }}</b>–<b>{{ $paginator->lastItem() }}</b> of <b>{{ number_format($paginator->total()) }}</b>
            @else
                no results
            @endif
        </p>

        <div class="pg__links">
            @if ($paginator->onFirstPage())
                <span class="is-disabled" aria-disabled="true">← previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pg__gap pg__num" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="is-current pg__num" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pg__num" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">next →</a>
            @else
                <span class="is-disabled" aria-disabled="true">next →</span>
            @endif
        </div>
    </nav>
@endif
