<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-rose-900 border border-rose-700 rounded-md font-semibold text-xs text-rose-100 uppercase tracking-widest shadow-sm hover:bg-rose-800 hover:border-rose-600 active:bg-rose-950 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 focus:ring-offset-slate-900 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
