<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-100">
                Application Settings
            </h2>
            <p class="mt-1 text-sm text-slate-400">Adjust application-wide options.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 shadow-sm">
                                <div class="border-b border-slate-200 px-6 py-4">
                                    <h3 class="text-lg font-semibold text-slate-900">Slot Types</h3>
                                    <p class="mt-1 text-sm text-slate-600">Define the positions available when building songs. Deactivated types remain visible on existing slots but cannot be added to new ones.</p>
                                </div>
                                <div
                                    class="space-y-4 px-6 py-5"
                                    x-data="{
                                        slotTypes: @js($slotTypes->map(fn ($slotType) => ['id' => $slotType->id, 'key' => $slotType->key, 'name' => $slotType->name, 'sort_order' => $slotType->sort_order, 'active' => $slotType->active])->values()),
                                        newName: '',
                                        busyId: null,
                                        error: '',
                                        async request(url, method, payload) {
                                            const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify(payload) });
                                            const data = await response.json();
                                            if (!response.ok) throw new Error(data.message || 'Could not save the slot type.');
                                            return data;
                                        },
                                        async add() {
                                            const name = this.newName.trim();
                                            if (!name) return;
                                            this.busyId = 'new'; this.error = '';
                                            try {
                                                const data = await this.request(@js(route('admin.slot-types.store')), 'POST', { name });
                                                this.slotTypes.push(data.slot_type);
                                                this.newName = '';
                                            } catch (error) { this.error = error.message; } finally { this.busyId = null; }
                                        },
                                        async save(slotType) {
                                            this.busyId = slotType.id; this.error = '';
                                            try {
                                                const data = await this.request(@js(route('admin.slot-types.update', ['slotType' => '__slot_type__'])).replace('__slot_type__', slotType.id), 'PATCH', slotType);
                                                Object.assign(slotType, data.slot_type);
                                            } catch (error) { this.error = error.message; } finally { this.busyId = null; }
                                        },
                                    }"
                                >
                                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                                        <x-text-input x-model="newName" @keydown.enter.prevent="add()" placeholder="Add a slot type, e.g. Trumpet" class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                                        <x-modal-primary-button type="button" @click="add()" x-bind:disabled="busyId === 'new'">Add slot type</x-modal-primary-button>
                                    </div>
                                    <p x-show="error" x-cloak x-text="error" class="text-sm text-rose-700"></p>
                                    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                                            <thead class="bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2 font-semibold">Name</th><th class="px-3 py-2 font-semibold">Key</th><th class="px-3 py-2 font-semibold">Order</th><th class="px-3 py-2 font-semibold">Active</th><th class="px-3 py-2"></th></tr></thead>
                                            <tbody class="divide-y divide-slate-200">
                                                <template x-for="slotType in slotTypes" :key="slotType.id">
                                                    <tr>
                                                        <td class="px-3 py-2"><input type="text" x-model="slotType.name" class="w-full rounded border-slate-300 text-slate-900 focus:border-amber-500 focus:ring-amber-200"></td>
                                                        <td class="px-3 py-2 font-mono text-xs text-slate-500" x-text="slotType.key"></td>
                                                        <td class="px-3 py-2"><input type="number" min="0" max="99999" x-model.number="slotType.sort_order" class="w-20 rounded border-slate-300 text-slate-900 focus:border-amber-500 focus:ring-amber-200"></td>
                                                        <td class="px-3 py-2"><input type="checkbox" x-model="slotType.active" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500"></td>
                                                        <td class="px-3 py-2 text-right"><button type="button" @click="save(slotType)" :disabled="busyId === slotType.id" class="inline-flex items-center rounded-md border border-amber-600 bg-amber-500 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-950 shadow-sm transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"><span x-text="busyId === slotType.id ? 'Saving...' : 'Save'"></span></button></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <section class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                                    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between" x-data="{ busy: false, message: '', error: '', timer: null, async sendTestPush() { this.busy = true; this.message = ''; this.error = ''; clearTimeout(this.timer); try { const response = await fetch(@js(route('admin.settings.push-test')), { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': @js(csrf_token()) } }); const payload = await response.json(); if (!response.ok) { this.error = payload.message || 'Could not send test push.'; return; } this.message = payload.message || 'Test push sent.'; this.timer = setTimeout(() => this.message = '', 4000); } catch (e) { this.error = 'Could not send test push.'; } finally { this.busy = false; } } }">
                                        <div>
                                            <h3 class="text-lg font-semibold text-slate-900">Notifications</h3>
                                            <p class="mt-1 text-sm text-slate-600">Admin notification controls always override individual user preferences.</p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="sendTestPush()"
                                            :disabled="busy"
                                            class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <span x-show="!busy">Send Test Push Notification</span>
                                            <span x-show="busy" x-cloak>Sending...</span>
                                        </button>
                                    </div>
                                    <p x-show="message" x-text="message" x-cloak class="mt-2 text-sm text-emerald-700"></p>
                                    <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm text-rose-700"></p>
                </div>

                <div class="space-y-6 px-6 py-6">
                    @forelse ($notificationSettings as $group)
                        <div
                            class="space-y-3"
                            x-data="{
                                applyToColumn(channel, value) {
                                    const selector = 'input[type=checkbox][data-notification-target=true][data-notification-channel=' + channel + ']';

                                    this.$refs.rows
                                        .querySelectorAll(selector)
                                        .forEach((input) => {
                                            if (input.disabled || input.checked === value) {
                                                return;
                                            }

                                            input.checked = value;
                                            input.dispatchEvent(new Event('change', { bubbles: true }));
                                        });
                                },
                            }"
                        >
                            <div>
                                <h4 class="font-semibold text-slate-900">{{ $group['label'] }}</h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-6 py-3 text-left font-semibold">Notification type</th>
                                            <th class="px-4 py-3 text-center font-semibold">Enabled</th>
                                            <th class="px-4 py-3 text-center font-semibold">Popups</th>
                                            <th class="px-4 py-3 text-center font-semibold">Email</th>
                                            <th class="px-4 py-3 text-center font-semibold">Push</th>
                                            <th class="px-4 py-3 text-center font-semibold">Text</th>
                                        </tr>
                                    </thead>
                                    <tbody x-ref="rows" class="divide-y divide-slate-200 bg-white">
                                        <tr class="bg-slate-50/80">
                                            <td class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Apply to all</td>
                                            @foreach (['enabled', 'popup', 'email', 'push', 'text'] as $channel)
                                                <td class="px-4 py-3 text-center">
                                                    <input
                                                        type="checkbox"
                                                        @change="applyToColumn(@js($channel), $event.target.checked)"
                                                        class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                        aria-label="Apply {{ $channel }} to all {{ strtolower($group['label']) }} notifications"
                                                    >
                                                </td>
                                            @endforeach
                                        </tr>
                                        @foreach ($group['options'] as $notificationSetting)
                                            <tr>
                                                <td class="px-6 py-4 align-top">
                                                    <p class="font-semibold text-slate-900">{{ $notificationSetting['label'] }}</p>
                                                    <p class="mt-1 text-sm text-slate-500">{{ $notificationSetting['description'] }}</p>
                                                </td>
                                                @foreach (['enabled', 'popup', 'email', 'push', 'text'] as $channel)
                                                    @php $setting = $notificationSetting['settings'][$channel]; @endphp
                                                    <td class="px-4 py-4 align-top">
                                                        <div
                                                            class="flex justify-center"
                                                            x-data="{
                                                                value: @js($setting->isEnabled()),
                                                                busy: false,
                                                                async save() {
                                                                    this.busy = true;

                                                                    try {
                                                                        const response = await fetch(@js(route('admin.settings.update', $setting)), {
                                                                            method: 'PATCH',
                                                                            headers: {
                                                                                'Content-Type': 'application/json',
                                                                                'Accept': 'application/json',
                                                                                'X-Requested-With': 'XMLHttpRequest',
                                                                                'X-CSRF-TOKEN': @js(csrf_token()),
                                                                            },
                                                                            body: JSON.stringify({ value: this.value }),
                                                                        });

                                                                        if (!response.ok) {
                                                                            this.value = ! this.value;
                                                                        }
                                                                    } catch (e) {
                                                                        this.value = ! this.value;
                                                                    } finally {
                                                                        this.busy = false;
                                                                    }
                                                                },
                                                            }"
                                                        >
                                                            <label class="inline-flex items-center">
                                                                <input type="checkbox" x-model="value" @change="save()" :disabled="busy" data-notification-target="true" data-notification-channel="{{ $channel }}" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
                                                            </label>
                                                        </div>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-600">
                            No notification types are currently available.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">App Settings</h3>
                    <p class="mt-1 text-sm text-slate-600">Configuration settings which apply to the app itself. Don't change these unless you know what you are doing!</p>
                </div>

                @if ($settings->isEmpty())
                    <div class="px-6 py-8 text-sm text-slate-600">
                        No settings have been configured yet.
                    </div>
                @else
                    <div class="divide-y divide-slate-200">
                        @foreach ($settings as $setting)
                            @php
                                $inputId = 'setting_'.$setting->id;
                                $isCheckbox = $setting->input_type === 'checkbox';
                                $initialValue = $isCheckbox ? $setting->isEnabled() : $setting->value;
                            @endphp
                            <div
                                class="grid gap-4 px-6 py-5 md:grid-cols-[minmax(0,1fr)_minmax(18rem,26rem)] md:items-start"
                                x-data="{
                                    value: @js($initialValue),
                                    inputType: @js($setting->input_type),
                                    busy: false,
                                    message: '',
                                    error: '',
                                    messageTimer: null,
                                    async save() {
                                        this.busy = true;
                                        this.message = '';
                                        this.error = '';
                                        clearTimeout(this.messageTimer);

                                        try {
                                            const response = await fetch(@js(route('admin.settings.update', $setting)), {
                                                method: 'PATCH',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': @js(csrf_token()),
                                                },
                                                body: JSON.stringify({ value: this.value }),
                                            });

                                            const payload = await response.json();

                                            if (!response.ok) {
                                                this.error = payload.message || 'Could not update this setting.';
                                                return;
                                            }

                                            this.value = this.inputType === 'checkbox'
                                                ? payload.setting.value === '1'
                                                : payload.setting.value;
                                            this.message = payload.message || 'Setting updated.';
                                            this.messageTimer = setTimeout(() => this.message = '', 2500);
                                        } catch (e) {
                                            this.error = 'Could not update this setting.';
                                        } finally {
                                            this.busy = false;
                                        }
                                    },
                                }"
                            >
                                <div>
                                    <label for="{{ $inputId }}" class="text-sm font-semibold text-slate-900">{{ $setting->name }}</label>
                                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $setting->key }}</p>
                                    <p class="mt-2 inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $setting->input_type }}</p>
                                </div>

                                <div class="space-y-2">
                                    @switch($setting->input_type)
                                        @case('textarea')
                                            <x-textarea-input
                                                id="{{ $inputId }}"
                                                x-model="value"
                                                rows="4"
                                                class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                            />
                                            @break

                                        @case('checkbox')
                                            <label for="{{ $inputId }}" class="inline-flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm">
                                                <input
                                                    id="{{ $inputId }}"
                                                    type="checkbox"
                                                    x-model="value"
                                                    @change="save()"
                                                    :disabled="busy"
                                                    class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                <span x-text="value ? 'Enabled' : 'Disabled'"></span>
                                            </label>
                                            @break

                                        @case('select')
                                            <select
                                                id="{{ $inputId }}"
                                                x-model="value"
                                                class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                            >
                                                <option value="">No value</option>
                                                @if ($setting->value !== null && $setting->value !== '')
                                                    <option value="{{ $setting->value }}">{{ $setting->value }}</option>
                                                @endif
                                            </select>
                                            @break

                                        @default
                                            <input
                                                id="{{ $inputId }}"
                                                type="{{ $setting->input_type }}"
                                                x-model="value"
                                                class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                            >
                                    @endswitch

                                    <div class="flex min-h-8 items-center justify-between gap-3">
                                        <p x-show="message" x-transition.opacity.duration.200ms x-text="message" class="text-sm text-emerald-700" x-cloak></p>
                                        <p x-show="error" x-text="error" class="text-sm text-rose-700" x-cloak></p>

                                        @if (! $isCheckbox)
                                            <button
                                                type="button"
                                                @click="save()"
                                                :disabled="busy"
                                                class="ms-auto inline-flex cursor-pointer items-center rounded-md border border-amber-600 bg-amber-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 shadow-sm transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <span x-show="!busy">Save</span>
                                                <span x-show="busy" x-cloak>Saving...</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
