<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-100">
                Application Settings
            </h2>
            <p class="mt-1 text-sm text-slate-400">Adjust application-wide options without leaving the page.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Notifications</h3>
                    <p class="mt-1 text-sm text-slate-600">Admin notification controls always override individual user preferences.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold">Notification type</th>
                                <th class="px-4 py-3 text-center font-semibold">Enabled</th>
                                <th class="px-4 py-3 text-center font-semibold">Popups</th>
                                <th class="px-4 py-3 text-center font-semibold">Email</th>
                                <th class="px-4 py-3 text-center font-semibold">Text</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($notificationSettings as $notificationSetting)
                                <tr>
                                    <td class="px-6 py-4 align-top">
                                        <p class="font-semibold text-slate-900">{{ $notificationSetting['label'] }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $notificationSetting['description'] }}</p>
                                    </td>
                                    @foreach (['enabled', 'popup', 'email', 'text'] as $channel)
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
                                                    <input type="checkbox" x-model="value" @change="save()" :disabled="busy" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
                                                </label>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Settings</h3>
                    <p class="mt-1 text-sm text-slate-600">Each control is generated from the setting input type.</p>
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
                                            <textarea
                                                id="{{ $inputId }}"
                                                x-model="value"
                                                rows="4"
                                                class="block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                            ></textarea>
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
