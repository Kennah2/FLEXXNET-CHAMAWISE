@props(['variant' => 'icon'])

@if ($variant === 'full')
    <img src="{{ asset('images/brand/chama-wise-logo-full.webp') }}" alt="CHAMA WISE — Save Together. Grow Together." {{ $attributes }}>
@else
    <img src="{{ asset('images/brand/chama-wise-icon.webp') }}" alt="CHAMA WISE" {{ $attributes }}>
@endif
