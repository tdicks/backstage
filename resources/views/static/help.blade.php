<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-100">Help</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 flex justify-center">
                <a href="/" class="inline-flex flex-col items-center gap-3">
                    <x-application-logo class="h-20 w-20 text-blue-300" />
                    <span class="text-sm font-semibold tracking-[0.18em] text-slate-300">BACKSTAGE</span>
                </a>
            </div>

            <div class="space-y-5 rounded-xl border border-slate-200 bg-slate-50/95 p-5 text-sm leading-6 text-slate-700 shadow-sm sm:p-6">
                <div class="rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <p class="text-base leading-7 text-slate-800">
                        Backstage is a jam organisation platform for planning sessions, building sets, and keeping track of who is playing each part. It gives players and organisers an easy way to see what is coming up, and helps avoid double-booking or confusion about who is playing what.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Viewing Sessions</h3>
                    <p>
                        Start in <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">Jam Sessions</span> to see what is coming up. Open a session to browse the sets people are bringing, the songs in each set, and the parts that are already covered or still available.
                    </p>
                    <p>
                        Sessions and sets can have a few small icons beside the name.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <x-heroicon-m-star class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
                            <p><span class="font-semibold text-slate-900">Feature set.</span> A larger set, usually with a theme, run by the house band.</p>
                        </div>
                        <div class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <x-live-status-icon size="h-5 w-5" class="mt-0.5 shrink-0" title="Jam session is live" />
                            <p><span class="font-semibold text-slate-900">Live session.</span> The jam is in motion and organisers are tracking the jam using the live dashboard.</p>
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
                    <h3 class="text-lg font-semibold text-slate-950">Attendance</h3>
                    <p>
                        Attendance helps everyone read the room before the jam starts. Use the controls in a session header to mark yourself as <span class="font-semibold text-slate-900">Going</span>, <span class="font-semibold text-slate-900">Maybe</span>, or <span class="font-semibold text-slate-900">Not going</span>.
                    </p>
                    <p>
                        This is separate from sign-in. Sign-in records who is physically there on the day, while attendance is the planning signal people use ahead of time.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Find a Slot</h3>
                    <p>
                        Use the <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">Find a Slot</span> page to browse open parts across upcoming sessions. The list is grouped by session and set. Use the Slot Coverage controls in your User Profile to influence what slots you see in the Slot Finder.
                    </p>
                    <p>
                        Click or tap a slot to take it or request it, depending on how the set owner is managing that set. If you're already playing a part on a song, it will not be shown.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Creating Sets</h3>
                    <p>
                        If a session is open, use <span class="inline-flex rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-900 shadow-sm">Create Set</span> to bring your own mini set to the jam. Give it a clear name so other players can recognise it quickly, and add a short note if there is a style, theme, or special plan people should know about.
                    </p>
                    <p>
                        When you create a set, you become the person looking after it. Open the <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-bars-3 class="h-3.5 w-3.5" aria-hidden="true" /> Set actions</span> menu to add songs or edit the set details. You can also invite other players to help manage the set with you.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Adding Songs</h3>
                    <p>
                        From the <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-bars-3 class="h-3.5 w-3.5" aria-hidden="true" /> Set actions</span> menu, choose <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-plus class="h-3.5 w-3.5" aria-hidden="true" /> Add Song</span>. Enter the song title and artist, then choose a band template before saving. The band template gives the song a sensible starting lineup, such as vocals, lead guitar, rhythm guitar, bass, drums, keys, or whatever setup your group uses.
                    </p>
                    <p>
                        Choose the template that feels closest to the version you want to play. You can add more slots later, including another band template via <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-pencil-square class="h-3.5 w-3.5" aria-hidden="true" /> Edit slot</span>. Any duplicate slots won't get overwritten.
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
                    <p>
                        Assigned slots can also be marked claimable, so someone else can take over without clearing the assignment first. It is useful when you have put your name on a part but are happy for another player to take it if they want it. When that happens, the slot shows a <span class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-violet-700">Claimable</span> badge next to the player name.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Free For All Mode</h3>
                    <p>
                        Sometimes a set organiser wants a "first come, first served" style of set organisation, where players can claim slots immediately without waiting for approval. When a set has <span class="inline-flex items-center gap-1 rounded-md border border-orange-200 bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-800 shadow-sm"><x-heroicon-m-fire class="h-3.5 w-3.5" aria-hidden="true" /> Free for all mode</span> turned on, any unclaimed slot can be claimed instantly by clicking <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-fire class="h-3.5 w-3.5 text-orange-500" aria-hidden="true" /> Take this slot</span>.
                    </p>
                    <p>
                        No approvals needed, no requests pending — just grab a spot and you are in. It is perfect for a quick-fire session or for last minute sets on the fly.
                    </p>
                    <p>
                        Free for all also works with claimable assigned slots. If a part is marked with the purple <span class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-violet-700">Claimable</span> badge, players can take that slot directly in the same way they would take an open slot.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Keeping Track in My Sets</h3>
                    <p>
                        Use <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">My Sets</span> as your personal set list for the jam. It gathers the parts you have coming up, the requests you have made, and any recommendations waiting for your answer, so you can quickly see what needs your attention before the session starts.
                    </p>
                    <p>
                        If you own a set, <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">My Sets</span> also shows the approvals you need to handle. It is the easiest place to check whether someone is waiting on you, confirm who is playing, and keep your set ready for the room.
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
                    <h3 class="text-lg font-semibold text-slate-950">Sharing and Live Sessions</h3>
                    <p>
                        Use the <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm"><x-heroicon-m-share class="h-3.5 w-3.5" aria-hidden="true" /> Copy share link</span> control for sessions and sets when you want someone to see the plan without signing in. Shared pages are read-only, so they are handy for previews, reminders, and posting the running order elsewhere.
                    </p>
                </div>

                <div class="space-y-5 rounded-lg border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Push Notifications</h3>
                    <p>
                        Backstage can send browser push notifications when your browser and device allow them. Support is somewhat patchy however, your mileage may vary!
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="font-semibold text-slate-900">Desktop</p>
                            <p class="mt-1 text-sm text-slate-700">
                                Most desktop browsers support Push Notifications. Go to your User Profile and click the <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">Enable Push Notifications</span> to receive a prompt from your browser. Allow the prompt to receive notifications. If you previously denied them, update the site notification permission in your browser settings.
                            </p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="font-semibold text-slate-900">iOS</p>
                            <p class="mt-1 text-sm text-slate-700">
                                Open Backstage in Safari on iPhone or iPad, choose Share and find the option for Add to Home Screen. Open Backstage from the home screen, then allow notifications using the <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">Enable Push Notifications</span> button in your User Profile.
                            </p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="font-semibold text-slate-900">Android</p>
                            <p class="mt-1 text-sm text-slate-700">
                                Open Backstage in Chrome on Android, navigate to your User Profile, and use the <span class="inline-flex rounded-md border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold text-slate-800 shadow-sm">Enable Push Notifications</span> button.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>