@if ($paginator->hasPages())
    <nav style="display:flex;justify-content:center;margin-top:24px;">
        <ul style="
            display:flex;
            gap:6px;
            list-style:none;
            padding:0;
            margin:0;
            flex-wrap:wrap;
        ">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li>
                    <span style="opacity:.4;padding:8px 12px;">‹</span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}"
                       style="padding:8px 12px;border-radius:8px;background:#f1f5f9;">
                        ‹
                    </a>
                </li>
            @endif

            {{-- Page Numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span style="padding:8px 12px;">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span style="
                                    padding:8px 14px;
                                    border-radius:8px;
                                    background:#6366f1;
                                    color:white;
                                    font-weight:600;
                                ">
                                    {{ $page }}
                                </span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $url }}"
                                   style="
                                       padding:8px 14px;
                                       border-radius:8px;
                                       background:#f1f5f9;
                                   ">
                                    {{ $page }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}"
                       style="padding:8px 12px;border-radius:8px;background:#f1f5f9;">
                        ›
                    </a>
                </li>
            @else
                <li>
                    <span style="opacity:.4;padding:8px 12px;">›</span>
                </li>
            @endif

        </ul>
    </nav>
@endif
