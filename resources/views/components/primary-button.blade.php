<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-corteza border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-horno focus:bg-horno active:bg-corteza focus:outline-none focus:ring-2 focus:ring-corteza focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
