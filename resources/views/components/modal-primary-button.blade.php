<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-slate-900 border border-slate-700 rounded-md font-semibold text-xs text-slate-100 uppercase tracking-widest shadow-sm hover:bg-slate-800 focus:bg-slate-800 active:bg-black focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-900 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
