<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">{{ __('Attachments') }}</h2>
                <p class="mt-1 text-sm text-slate-400">Search and manage set, song, and slot attachments.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                <form method="GET" action="{{ route('admin.attachments.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_180px_auto] md:items-end">
                    <div>
                        <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Search') }}</label>
                        <input id="q" name="q" value="{{ $search }}" placeholder="Label, URL, filename, uploader" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                    </div>

                    <div>
                        <label for="sort" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Sort by') }}</label>
                        <select id="sort" name="sort" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                            <option value="created_at" @selected($sort === 'created_at')>{{ __('Added') }}</option>
                            <option value="type" @selected($sort === 'type')>{{ __('Type') }}</option>
                            <option value="size_bytes" @selected($sort === 'size_bytes')>{{ __('Size') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="direction" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Direction') }}</label>
                        <select id="direction" name="direction" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                            <option value="desc" @selected($direction === 'desc')>{{ __('Descending') }}</option>
                            <option value="asc" @selected($direction === 'asc')>{{ __('Ascending') }}</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button>{{ __('Apply') }}</x-primary-button>
                        <a href="{{ route('admin.attachments.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50/95 shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <p class="text-sm text-slate-600">{{ $attachments->total() }} {{ __('attachments found') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-fixed divide-y divide-slate-200 md:table-auto">
                        <thead class="bg-slate-100/70">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">{{ __('Attachment') }}</th>
                                <th class="px-4 py-3">{{ __('Linked to') }}</th>
                                <th class="px-4 py-3">{{ __('Added') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white/95">
                            @forelse ($attachments as $attachment)
                                @php
                                    $attachable = $attachment->attachable;
                                    $breadcrumbItems = [];
                                    $session = null;

                                    if ($attachable instanceof \App\Models\Set) {
                                        $session = $attachable->session;
                                    } elseif ($attachable instanceof \App\Models\Song) {
                                        $set = $attachable->set;
                                        $session = $set?->session;
                                    } elseif ($attachable instanceof \App\Models\Slot) {
                                        $song = $attachable->song;
                                        $set = $song?->set;
                                        $session = $set?->session;
                                    }

                                    if ($session) {
                                        $breadcrumbItems[] = [
                                            'label' => $session->name,
                                            'href' => route('sessions.show', $session),
                                        ];
                                    }

                                    if ($attachable instanceof \App\Models\Set) {
                                        $breadcrumbItems[] = [
                                            'label' => $attachable->name,
                                            'href' => $session ? route('sessions.show', $session).'#set-'.$attachable->id : null,
                                        ];
                                    } elseif ($attachable instanceof \App\Models\Song) {
                                        $set = $attachable->set;

                                        if ($set) {
                                            $breadcrumbItems[] = [
                                                'label' => $set->name,
                                                'href' => $session ? route('sessions.show', $session).'#set-'.$set->id : null,
                                            ];
                                        }

                                        if ($attachable->set) {
                                            $breadcrumbItems[] = [
                                                'label' => $attachable->artist.' - '.$attachable->title,
                                                'href' => $session ? route('sessions.show', $session).'#song-'.$attachable->id : null,
                                            ];
                                        } else {
                                            $breadcrumbItems[] = [
                                                'label' => $attachable->artist.' - '.$attachable->title,
                                                'href' => null,
                                            ];
                                        }
                                    } elseif ($attachable instanceof \App\Models\Slot) {
                                        $song = $attachable->song;

                                        if ($song) {
                                            $set = $song->set;

                                            if ($set) {
                                                $breadcrumbItems[] = [
                                                    'label' => $set->name,
                                                    'href' => $session ? route('sessions.show', $session).'#set-'.$set->id : null,
                                                ];
                                            }

                                            $breadcrumbItems[] = [
                                                'label' => $song->artist.' - '.$song->title,
                                                'href' => $session ? route('sessions.show', $session).'#song-'.$song->id : null,
                                            ];
                                        }

                                        $breadcrumbItems[] = [
                                            'label' => \App\Models\Slot::options()[$attachable->name] ?? $attachable->name,
                                            'href' => $session ? route('sessions.show', $session).'#slot-'.$attachable->id : null,
                                        ];
                                    }
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <p class="flex min-w-0 items-baseline gap-2 text-sm text-slate-900">
                                            <span class="min-w-0 truncate font-semibold">{{ $attachment->label ?: ($attachment->original_filename ?: $attachment->url) }}</span>
                                            @if ($attachment->type === \App\Models\Attachment::TYPE_FILE && $attachment->size_bytes)
                                                <span class="shrink-0 text-xs font-normal text-slate-500">{{ number_format($attachment->size_bytes / 1024, 1) }} KB</span>
                                            @endif
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span class="inline-flex items-center" @if($attachment->type === \App\Models\Attachment::TYPE_LINK) title="Link attachment" @else title="File attachment" @endif>
                                                @if ($attachment->type === \App\Models\Attachment::TYPE_LINK)
                                                    <x-heroicon-m-link class="h-3.5 w-3.5" aria-hidden="true" />
                                                    <span class="sr-only">Link attachment</span>
                                                @else
                                                    <x-heroicon-m-document class="h-3.5 w-3.5" aria-hidden="true" />
                                                    <span class="sr-only">File attachment</span>
                                                @endif
                                            </span>
                                            <span>{{ __('by') }} {{ $attachment->uploader?->name ?? 'Unknown' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-slate-700">
                                        @if (count($breadcrumbItems) > 0)
                                            <nav aria-label="Attachment target" class="flex flex-wrap items-center gap-1.5">
                                                @foreach ($breadcrumbItems as $item)
                                                    @if (! $loop->first)
                                                        <span class="text-slate-400" aria-hidden="true">/</span>
                                                    @endif

                                                    @if ($item['href'])
                                                        <a href="{{ $item['href'] }}" class="text-slate-700 underline decoration-slate-300 underline-offset-2 transition hover:text-slate-900 hover:decoration-slate-500">{{ $item['label'] }}</a>
                                                    @else
                                                        <span>{{ $item['label'] }}</span>
                                                    @endif
                                                @endforeach
                                            </nav>
                                        @else
                                            <span class="text-slate-500">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-slate-700">{{ $attachment->created_at?->format('M j, Y g:i A') }}</td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <form method="POST" action="{{ route('admin.attachments.destroy', $attachment) }}" onsubmit="return confirm('Delete this attachment? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 p-1.5 text-rose-700 transition hover:bg-rose-100" aria-label="Delete attachment" title="Delete attachment">
                                                <x-heroicon-m-trash class="h-3.5 w-3.5" aria-hidden="true" />
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">{{ __('No attachments matched your search.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $attachments->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
