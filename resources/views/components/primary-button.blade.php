<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-amber-500 border border-amber-600 rounded-md font-semibold text-xs text-slate-900 uppercase tracking-widest shadow-sm hover:bg-amber-400 hover:border-amber-500 focus:bg-amber-400 active:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
