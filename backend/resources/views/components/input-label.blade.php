@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-navy-700']) }}>
    {{ $value ?? $slot }}
</label>
