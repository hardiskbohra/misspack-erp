@props(['user' => null])
<span {{ $attributes->class(['user-avatar']) }} data-user-avatar aria-hidden="true">
    <span data-avatar-initials>{{ $user?->initials ?: 'U' }}</span>
    @if ($user?->avatar_url)
        <img data-user-avatar-image src="{{ $user->avatar_url }}" alt="" decoding="async">
    @endif
</span>
