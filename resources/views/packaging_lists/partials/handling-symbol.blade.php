<div class="label-handling-symbol">
    @if($type === 'fragile')
        <svg viewBox="0 0 64 64" aria-hidden="true"><path d="M18 8h28l-3 23c-1 8-5 13-11 13s-10-5-11-13L18 8Z"/><path d="m34 10-7 10 8 4-7 10"/><path d="M32 44v11M21 56h22"/></svg>
        <strong>FRAGILE</strong>
    @elseif($type === 'care')
        <svg viewBox="0 0 64 64" aria-hidden="true"><path d="m22 15 10-6 10 6-10 6-10-6Zm0 0v13l10 6 10-6V15"/><path d="M6 34c8-2 13 1 19 8l7 4M58 34c-8-2-13 1-19 8l-7 4M8 48c9 0 15 2 24 8M56 48c-9 0-15 2-24 8"/></svg>
        <strong>HANDLE WITH CARE</strong>
    @elseif($type === 'dry')
        <svg viewBox="0 0 64 64" aria-hidden="true"><path d="M8 31c4-13 13-20 24-20s20 7 24 20c-6-4-12-4-18 0-4-4-8-4-12 0-6-4-12-4-18 0Z"/><path d="M32 11v36c0 7 10 7 10 0"/><path d="M15 8c0 3-4 5-4 9M27 4c0 3-4 5-4 9M43 6c0 3-4 5-4 9M54 11c0 3-4 5-4 9"/></svg>
        <strong>KEEP DRY</strong>
    @elseif($type === 'up')
        <svg viewBox="0 0 64 64" aria-hidden="true"><path d="M9 27 20 12l11 15h-7v22h-8V27H9ZM33 27l11-15 11 15h-7v22h-8V27h-7ZM8 55h48"/></svg>
        <strong>THIS SIDE UP</strong>
    @elseif($type === 'hazard')
        <svg viewBox="0 0 64 64" aria-hidden="true"><path d="M32 7 59 56H5L32 7Z"/><path d="M32 23v17M32 48h.1"/></svg>
        <strong>HAZARDOUS</strong>
    @endif
</div>
