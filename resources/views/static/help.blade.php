<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-100">Help</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="space-y-5 rounded-xl border border-slate-200 bg-slate-50/95 p-5 text-sm leading-6 text-slate-700 shadow-sm sm:p-6">
                <div class="rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <p class="text-base leading-7 text-slate-800">
                        Backstage is a jam organisation platform for planning sessions, building sets, and keeping track of who is playing each part. It gives players and organisers one shared place to arrange the music, using controls like <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-plus class="h-3.5 w-3.5" aria-hidden="true" /> Add Song</span>, <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-hand-raised class="h-3.5 w-3.5" aria-hidden="true" /> Request slot</span>, and <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-share class="h-3.5 w-3.5" aria-hidden="true" /> Copy Share link</span> instead of a long message thread.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Viewing Sessions</h3>
                    <p>
                        Start in <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">Jam Sessions</span> to see what is coming up. Open a session to browse the sets people are bringing, the songs in each set, and the parts that are already covered or still available.
                    </p>
                    <p>
                        Sessions and sets can have a few small icons beside the name. You only need to know the ones you can see:
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <x-heroicon-m-star class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
                            <p><span class="font-semibold text-slate-900">Feature set.</span> A larger set, usually with a theme, run by the house band.</p>
                        </div>
                        <div class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <x-heroicon-m-arrow-right-on-rectangle class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />
                            <p><span class="font-semibold text-slate-900">Check-ins are open.</span> An organiser is tracking who has arrived for the session.</p>
                        </div>
                        <div class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <x-heroicon-m-lock-closed class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
                            <p><span class="font-semibold text-slate-900">Closed to new sets.</span> You can still look around, but new sets cannot be added.</p>
                        </div>
                        <div class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <x-heroicon-m-archive-box class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" aria-hidden="true" />
                            <p><span class="font-semibold text-slate-900">Archived.</span> This is an older session kept for reference.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Creating Sets</h3>
                    <p>
                        If a session is open, use <span class="inline-flex rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-900 shadow-sm">Create Set</span> to bring your own mini set to the jam. Give it a clear name so other players can recognise it quickly, and add a short note if there is a style, theme, or special plan people should know about.
                    </p>
                    <p>
                        When you create a set, you become the person looking after it. Open the <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-bars-3 class="h-3.5 w-3.5" aria-hidden="true" /> Set actions</span> menu to add songs, tidy the running order, choose who plays each part, and respond when other players ask to join in.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Adding Songs</h3>
                    <p>
                        From the <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-bars-3 class="h-3.5 w-3.5" aria-hidden="true" /> Set actions</span> menu, choose <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-plus class="h-3.5 w-3.5" aria-hidden="true" /> Add Song</span>. Enter the song title and artist, then choose a band template before saving. The band template gives the song a sensible starting lineup, such as vocals, lead guitar, rhythm guitar, bass, drums, keys, or whatever setup your group uses.
                    </p>
                    <p>
                        Choose the template that feels closest to the version you want to play. You can still use <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-pencil-square class="h-3.5 w-3.5" aria-hidden="true" /> Edit slot</span> afterwards if your arrangement needs extra vocals, fewer guitars, a keys part, or something more unusual.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Claiming and Managing Slots</h3>
                    <p>
                        Each song is split into playable slots. Use the <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-bars-3 class="h-3.5 w-3.5" aria-hidden="true" /> Slot actions</span> menu beside a part to choose what you want to do. If you see an open slot you want, use <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-arrow-down-on-square class="h-3.5 w-3.5" aria-hidden="true" /> Take this slot</span> or <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-hand-raised class="h-3.5 w-3.5" aria-hidden="true" /> Request slot</span>, depending on how the set owner is managing that set.
                    </p>
                    <p>
                        If you need to step away from a part you already claimed, use <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-arrow-left-on-rectangle class="h-3.5 w-3.5" aria-hidden="true" /> Release slot</span>. Backstage also helps avoid impossible double-booking within the same set. If you are already down for a part that clashes with another one, it will tell you clearly instead of quietly changing the plan.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Set Requests and Approvals</h3>
                    <p>
                        When you use <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-hand-raised class="h-3.5 w-3.5" aria-hidden="true" /> Request slot</span>, the set owner gets to decide whether to approve it. Until they say yes, the slot is not yours yet, so everyone can see that it is still being sorted out.
                    </p>
                    <p>
                        If you own a set, <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">My Sets</span> is your main place to keep on top of requests. Use <span class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 shadow-sm"><x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" /> Approve</span> when the part is theirs, or <span class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-800 shadow-sm"><x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" /> Reject</span> the request if the song needs a different player or arrangement.
                    </p>
                    <p>
                        If approving a request would give someone two clashing parts in the same set, Backstage will stop the approval and explain what needs fixing first.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Recommendations</h3>
                    <p>
                        If you own a set and know exactly who would suit a part, use <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-user-plus class="h-3.5 w-3.5" aria-hidden="true" /> Recommend someone else</span> to send that slot their way. It is a nudge, not a booking: they still get to choose <span class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 shadow-sm"><x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" /> Accept</span> or <span class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-800 shadow-sm"><x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" /> Reject</span>.
                    </p>
                    <p>
                        Recommendations are useful when you want to invite a singer for a particular song, ask a guitarist to take a lead part, or suggest someone who knows the tune well. If they accept, the same clash checks apply before the slot is confirmed.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Sharing and Check-Ins</h3>
                    <p>
                        Use the <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-share class="h-3.5 w-3.5" aria-hidden="true" /> Copy share link</span> control for sessions and sets when you want someone to see the plan without signing in. Shared pages are read-only, so they are handy for previews, reminders, and posting the running order elsewhere.
                    </p>
                    <p>
                        If organisers are using check-ins, admins can open <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">Who's Here</span> to mark people in and out on the night. As a player, the main thing to know is that the session page will show the <span class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 shadow-sm"><x-heroicon-m-arrow-right-on-rectangle class="h-3.5 w-3.5" aria-hidden="true" /> Check-ins are open</span> icon when check-ins are open.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>