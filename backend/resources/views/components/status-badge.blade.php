@props(['status'])

@php
$classes = match ($status) {
    'paid', 'verified', 'active', 'completed' => 'badge-success',
    'pending', 'partial', 'pending_verification', 'approved' => 'badge-pending',
    'overdue', 'rejected' => 'badge-danger',
    default => 'badge-neutral',
};
$label = \Illuminate\Support\Str::of($status)->replace('_', ' ')->headline();
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $label }}</span>
