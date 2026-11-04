<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-brand-pink border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-pink-600 focus:bg-pink-600 active:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-brand-pink focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
