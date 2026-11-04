<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white border border-navy-100 rounded-md font-semibold text-xs text-navy-700 uppercase tracking-widest shadow-sm hover:bg-surface-light focus:outline-none focus:ring-2 focus:ring-navy-400 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
