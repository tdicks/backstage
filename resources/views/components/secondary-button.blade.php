<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-600 rounded-md font-semibold text-xs text-slate-100 uppercase tracking-widest shadow-sm hover:bg-slate-700 hover:border-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
