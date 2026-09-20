@if ($items->hasPages())
    <div class="custom-pagination-wrapper">
        <ul class="custom-pagination">

            {{-- Previous --}}
            @if ($items->onFirstPage())
                <li class="disabled">
                    <span><i class="fas fa-chevron-left"></i></span>
                </li>
            @else
                <li>
                    <a href="{{ $items->previousPageUrl() }}">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            @endif

            {{-- Page Numbers --}}
            @foreach ($items->getUrlRange(1, $items->lastPage()) as $page => $url)
                <li class="{{ $page == $items->currentPage() ? 'active' : '' }}">
                    <a href="{{ $url }}">{{ $page }}</a>
                </li>
            @endforeach

            {{-- Next --}}
            @if ($items->hasMorePages())
                <li>
                    <a href="{{ $items->nextPageUrl() }}">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            @else
                <li class="disabled">
                    <span><i class="fas fa-chevron-right"></i></span>
                </li>
            @endif

        </ul>
    </div>
@endif