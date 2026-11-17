@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Navigasi halaman">
        <div class="pager-info">
            @if ($paginator->firstItem())
                Menampilkan {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
            @else
                {{ $paginator->count() }} dari {{ $paginator->total() }}
            @endif
        </div>

        <ul class="pager-list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="pager-link is-disabled" aria-disabled="true">Sebelumnya</span>
                @else
                    <a class="pager-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pager-gap">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="pager-link is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pager-link" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a class="pager-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
                @else
                    <span class="pager-link is-disabled" aria-disabled="true">Berikutnya</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
