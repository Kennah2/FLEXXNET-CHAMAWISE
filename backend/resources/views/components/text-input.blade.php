@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-navy-100 focus:border-brand-pink focus:ring-brand-pink rounded-md shadow-sm']) }}>
