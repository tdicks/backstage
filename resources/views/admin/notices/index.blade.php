<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4" x-data>
            <div>
                <h2 class="text-xl font-semibold text-slate-100">App Notices</h2>
                <p class="mt-1 text-sm text-slate-400">Configure notices that can be shown across the app.</p>
            </div>

            <x-primary-button
                type="button"
                @click="window.dispatchEvent(new CustomEvent('notice-create-requested')); window.dispatchEvent(new CustomEvent('open-modal', { detail: 'admin-notices-create' }))"
            >
                <x-heroicon-m-plus class="-ms-0.5 me-2 h-4 w-4" aria-hidden="true" />
                Create Notice
            </x-primary-button>

        </div>
    </x-slot>

    <div class="py-10">
        <div
            class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8"
            x-data="adminNotices({
                listUrl: @js($listUrl),
                storeUrl: @js($storeUrl),
                reorderUrl: @js($reorderUrl),
                previewUrl: @js($previewUrl),
                clearDismissalsUrlTemplate: @js($clearDismissalsUrlTemplate),
                updateUrlTemplate: @js($updateUrlTemplate),
                deleteUrlTemplate: @js($deleteUrlTemplate),
                csrfToken: @js($csrfToken),
                routeOptions: @js($routeOptions),
                locationOptions: @js($locationOptions),
                audienceScopeOptions: @js($audienceScopeOptions),
                levelOptions: @js($levelOptions),
            })"
            x-on:modal-closed.window="handleModalClosed($event.detail.name)"
            x-on:notice-create-requested.window="handleCreateRequested()"
            @resize.window="syncDesktopReorderEnabled()"
            x-init="init()"
        >
            <p x-show="message" x-cloak x-text="message" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"></p>
            <p x-show="error" x-cloak x-text="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></p>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 shadow-sm">

                <div class="space-y-4 px-6 py-5">
                    <div x-show="loading" x-cloak class="space-y-3">
                        <div class="h-16 animate-pulse rounded-lg bg-slate-200"></div>
                        <div class="h-16 animate-pulse rounded-lg bg-slate-200"></div>
                    </div>

                    <template x-if="!loading && notices.length === 0">
                        <p class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">No notices yet.</p>
                    </template>

                    <template x-for="group in groupedNotices()" :key="'notice-group-' + group.location">
                        <section class="space-y-3" @dragover.prevent="onNoticeGroupDragOver($event, group.location)" @drop="onNoticeDrop($event, group.location)">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600" x-text="group.label + ' notices'"></h3>

                            <div class="space-y-3" :data-notice-items-for="group.location">
                                <article :data-notice-drop-placeholder="group.location" class="hidden rounded-lg border-2 border-dashed border-sky-400 bg-sky-50/70 p-4 text-sm font-medium text-sky-700 shadow-sm" @dragover.stop.prevent="onNoticePlaceholderDragOver($event, group.location)" @drop.stop="onNoticeDrop($event, group.location)">
                                    Drop notice here
                                </article>

                                <template x-for="notice in group.notices" :key="'notice-' + notice.id">
                                <article
                                    :data-notice-id="notice.id"
                                    x-bind:draggable="isDesktopReorderEnabled && !isReorderBusy(group.location) ? 'true' : 'false'"
                                    @dragstart="onNoticeDragStart($event, notice.id, group.location)"
                                    @dragend="onNoticeDragEnd()"
                                    @dragover.stop="onNoticeDragOver($event, notice.id, group.location)"
                                    @drop.stop="onNoticeDrop($event, group.location)"
                                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                                    :class="[
                                        draggingNoticeId === Number(notice.id) ? 'opacity-60' : '',
                                        notice.enabled ? 'notice-card-enabled' : 'notice-card-disabled'
                                    ]"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="text-xs text-slate-500">
                                            <h4 class="text-lg font-semibold text-slate-900" x-text="notice.title"></h4>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                <span
                                                    class="rounded-full border px-2 py-1 font-medium"
                                                    :class="noticeLevelStyles(notice.level).container"
                                                    x-text="levelLabel(notice.level)"
                                                ></span>
                                                <span
                                                    class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 font-medium text-slate-700"
                                                    x-text="audienceScopeLabel(notice.audience_scope)"
                                                ></span>
                                                <span
                                                    class="rounded-full border px-2 py-1 font-medium"
                                                    :class="notice.enabled ? 'border-emerald-300 bg-emerald-100 text-emerald-900' : 'border-rose-300 bg-rose-100 text-rose-900'"
                                                    x-text="notice.enabled ? 'Enabled' : 'Disabled'"
                                                ></span>
                                            </div>
                                        </div>

                                        <div class="ml-auto flex items-center justify-start gap-2 sm:justify-end">
                                            <div class="inline-flex w-7 flex-col overflow-hidden rounded-md border border-slate-200 bg-white text-slate-500 md:hidden" data-no-notice-drag>
                                                <button
                                                    type="button"
                                                    @click.prevent="moveNotice(notice.id, group.location, -1)"
                                                    x-bind:disabled="!canMoveNoticeUp(notice.id, group.location) || isReorderBusy(group.location) || notice.busy"
                                                    class="inline-flex h-5 items-center justify-center transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                                                    aria-label="Move notice up"
                                                    title="Move notice up"
                                                >
                                                    <x-heroicon-m-chevron-up class="h-3 w-3" aria-hidden="true" />
                                                </button>
                                                <button
                                                    type="button"
                                                    @click.prevent="moveNotice(notice.id, group.location, 1)"
                                                    x-bind:disabled="!canMoveNoticeDown(notice.id, group.location) || isReorderBusy(group.location) || notice.busy"
                                                    class="inline-flex h-5 items-center justify-center border-t border-slate-200 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                                                    aria-label="Move notice down"
                                                    title="Move notice down"
                                                >
                                                    <x-heroicon-m-chevron-down class="h-3 w-3" aria-hidden="true" />
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                @click="openResetDismissalsModal(notice)"
                                                x-bind:disabled="notice.busy"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 transition hover:border-slate-400 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                title="Clear dismissals"
                                                aria-label="Clear dismissals"
                                            >
                                                <x-heroicon-m-arrow-uturn-left class="h-4 w-4" aria-hidden="true" />
                                            </button>
                                            <button
                                                type="button"
                                                @click="openEditModal(notice)"
                                                x-bind:disabled="notice.busy"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 transition hover:border-slate-400 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                title="Edit notice"
                                                aria-label="Edit notice"
                                            >
                                                <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </div>
                                    </div>

                                    <template x-if="notice.content_html">
                                        <div
                                            class="mt-3 rounded-lg border px-4 py-3 shadow-sm"
                                            :class="noticeLevelStyles(notice.level).container"
                                        >
                                            <div class="flex items-center gap-3">
                                                <div :class="noticeLevelStyles(notice.level).icon" aria-hidden="true">
                                                    <x-heroicon-m-x-circle x-show="notice.level === 'critical'" class="h-5 w-5" />
                                                    <x-heroicon-m-exclamation-triangle x-show="notice.level === 'warning'" class="h-5 w-5" />
                                                    <x-heroicon-m-information-circle x-show="notice.level !== 'critical' && notice.level !== 'warning'" class="h-5 w-5" />
                                                </div>

                                                <div class="min-w-0 flex-1 text-center">
                                                    <div
                                                        class="notice-markdown text-sm"
                                                        :class="noticeLevelStyles(notice.level).body"
                                                        x-html="notice.content_html"
                                                    ></div>
                                                </div>

                                                <template x-if="notice.dismissable">
                                                    <button
                                                        type="button"
                                                        disabled
                                                        class="inline-flex items-center self-center rounded-md p-1 text-xs font-semibold transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                        :class="noticeLevelStyles(notice.level).button"
                                                        title="Dismiss notice preview"
                                                        aria-label="Dismiss notice preview"
                                                    >
                                                        <x-heroicon-m-x-mark class="h-4 w-4" />
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span x-show="notice.show_on_all_pages" x-cloak class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1">All pages</span>
                                        <span
                                            x-show="!notice.show_on_all_pages && (notice.show_on_routes || []).length"
                                            x-cloak
                                            class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1"
                                            x-text="routeLabelsSummary(notice.show_on_routes)"
                                        ></span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1" x-text="notice.dismissable ? 'Dismissable' : 'Not dismissable'"></span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1" x-text="notice.updated_at ? 'Updated ' + new Date(notice.updated_at).toLocaleString() : 'Not saved yet'"></span>
                                    </div>
                                </article>
                                </template>
                            </div>
                        </section>
                    </template>
                </div>
            </section>

            <template x-teleport="body">
                <x-modal name="admin-notices-create" maxWidth="4xl" focusable>
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-slate-900">Create Notice</h3>
                        <p class="mt-1 text-sm text-slate-600">Create notices with placement and route targeting.</p>

                        <div class="mt-4 space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Title (for reference)" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                    <x-text-input x-ref="createTitleInput" x-model="createForm.title" required class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <x-input-label value="Level" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <select x-model="createForm.level" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                            <template x-for="option in levelOptions" :key="'create-level-' + option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <x-input-label value="Location" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <select x-model="createForm.location" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                            <template x-for="option in locationOptions" :key="'create-location-' + option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <x-input-label value="Audience" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <select x-model="createForm.audience_scope" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                            <template x-for="option in audienceScopeOptions" :key="'create-audience-' + option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label value="Content (Supports Markdown)" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                    <x-markdown-help modal-name="notice-create-markdown-help" title="Notice Markdown Help" />
                                </div>
                                <x-textarea-input x-model="createForm.content" @input.debounce.250ms="refreshCreatePreview()" rows="4" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                            </div>

                            <div>
                                <x-input-label value="Live Preview" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />

                                <div class="mt-2 rounded-lg border px-4 py-3 shadow-sm" :class="noticeLevelStyles(createForm.level).container">
                                    <div class="flex items-center gap-3">
                                        <div :class="noticeLevelStyles(createForm.level).icon" aria-hidden="true">
                                            <x-heroicon-m-x-circle x-show="createForm.level === 'critical'" class="h-5 w-5" />
                                            <x-heroicon-m-exclamation-triangle x-show="createForm.level === 'warning'" class="h-5 w-5" />
                                            <x-heroicon-m-information-circle x-show="createForm.level !== 'critical' && createForm.level !== 'warning'" class="h-5 w-5" />
                                        </div>

                                        <div class="min-w-0 flex-1 text-center">
                                            <template x-if="createPreviewHtml">
                                                <div class="notice-markdown text-sm" :class="noticeLevelStyles(createForm.level).body" x-html="createPreviewHtml"></div>
                                            </template>
                                            <template x-if="!createPreviewHtml">
                                                <p class="text-sm" :class="noticeLevelStyles(createForm.level).body">Notice content preview will appear here.</p>
                                            </template>
                                        </div>

                                        <template x-if="createForm.dismissable">
                                            <button
                                                type="button"
                                                disabled
                                                class="inline-flex items-center self-center rounded-md p-1 text-xs font-semibold transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                :class="noticeLevelStyles(createForm.level).button"
                                                title="Dismiss notice preview"
                                                aria-label="Dismiss notice preview"
                                            >
                                                <x-heroicon-m-x-mark class="h-4 w-4" />
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Options" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />

                                <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                    <div class="space-y-4">
                                    <div class="flex flex-wrap items-center gap-6">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="checkbox" x-model="createForm.dismissable" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            Dismissable
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="checkbox" x-model="createForm.enabled" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            Enabled
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-6">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="radio" name="create-show-target" :checked="createForm.show_on_all_pages" @change="createForm.show_on_all_pages = true" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            <span>Show on all pages</span>
                                        </label>

                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="radio" name="create-show-target" :checked="!createForm.show_on_all_pages" @change="createForm.show_on_all_pages = false" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            <span>Show on specific routes</span>
                                        </label>
                                    </div>
                                    </div>

                                    <div x-show="!createForm.show_on_all_pages" x-cloak class="mt-3">
                                    <select x-model="createForm.show_on_routes" multiple class="mt-2 block h-36 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                        <template x-for="option in routeOptions" :key="'create-route-' + option.name">
                                            <option :value="option.name" x-text="option.label + ' (' + option.uri + ')'">
                                            </option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500">Only full-page routes are listed.</p>
                                </div>
                            </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <x-modal-secondary-button type="button" @click="closeCreateModal()">Cancel</x-modal-secondary-button>
                                <x-modal-primary-button type="button" @click="createNotice()" x-bind:disabled="saving">
                                    <span x-show="!saving">Create Notice</span>
                                    <span x-show="saving" x-cloak>Creating...</span>
                                </x-modal-primary-button>
                            </div>
                        </div>
                    </div>
                </x-modal>
            </template>

            <template x-teleport="body">
                <x-modal name="admin-notices-edit" maxWidth="4xl" focusable>
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-slate-900">Edit Notice</h3>
                        <p class="mt-1 text-sm text-slate-600">Update notice details and targeting.</p>

                        <div class="mt-4 space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Title (for reference)" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                    <x-text-input x-ref="editTitleInput" x-model="editForm.title" required class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <x-input-label value="Level" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <select x-model="editForm.level" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                            <template x-for="option in levelOptions" :key="'edit-level-modal-' + option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <x-input-label value="Location" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <select x-model="editForm.location" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                            <template x-for="option in locationOptions" :key="'edit-location-modal-' + option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <x-input-label value="Audience" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                        <select x-model="editForm.audience_scope" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                            <template x-for="option in audienceScopeOptions" :key="'edit-audience-' + option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label value="Content (Supports Markdown)" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />
                                    <x-markdown-help modal-name="notice-edit-markdown-help" title="Notice Markdown Help" />
                                </div>
                                <x-textarea-input x-model="editForm.content" @input.debounce.250ms="refreshEditPreview()" rows="4" class="mt-2 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                                <p x-show="editInitiallyDismissable" x-cloak class="mt-2 text-xs text-slate-500">
                                    Users who have dismissed this notice won't see any changes. Use the Clear Dismissals button to reset any dismissals on this notice.
                                </p>
                            </div>

                            <div>
                                <x-input-label value="Live Preview" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />

                                <div class="mt-2 rounded-lg border px-4 py-3 shadow-sm" :class="noticeLevelStyles(editForm.level).container">
                                    <div class="flex items-center gap-3">
                                        <div :class="noticeLevelStyles(editForm.level).icon" aria-hidden="true">
                                            <x-heroicon-m-x-circle x-show="editForm.level === 'critical'" class="h-5 w-5" />
                                            <x-heroicon-m-exclamation-triangle x-show="editForm.level === 'warning'" class="h-5 w-5" />
                                            <x-heroicon-m-information-circle x-show="editForm.level !== 'critical' && editForm.level !== 'warning'" class="h-5 w-5" />
                                        </div>

                                        <div class="min-w-0 flex-1 text-center">
                                            <template x-if="editPreviewHtml">
                                                <div class="notice-markdown text-sm" :class="noticeLevelStyles(editForm.level).body" x-html="editPreviewHtml"></div>
                                            </template>
                                            <template x-if="!editPreviewHtml">
                                                <p class="text-sm" :class="noticeLevelStyles(editForm.level).body">Notice content preview will appear here.</p>
                                            </template>
                                        </div>

                                        <template x-if="editForm.dismissable">
                                            <button
                                                type="button"
                                                disabled
                                                class="inline-flex items-center self-center rounded-md p-1 text-xs font-semibold transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                :class="noticeLevelStyles(editForm.level).button"
                                                title="Dismiss notice preview"
                                                aria-label="Dismiss notice preview"
                                            >
                                                <x-heroicon-m-x-mark class="h-4 w-4" />
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Options" class="text-xs font-semibold uppercase tracking-wide text-slate-600" />

                                <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                    <div class="space-y-4">
                                    <div class="flex flex-wrap items-center gap-6">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="checkbox" x-model="editForm.dismissable" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            Dismissable
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="checkbox" x-model="editForm.enabled" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            Enabled
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-6">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="radio" name="edit-show-target" :checked="editForm.show_on_all_pages" @change="editForm.show_on_all_pages = true" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            <span>Show on all pages</span>
                                        </label>

                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                            <input type="radio" name="edit-show-target" :checked="!editForm.show_on_all_pages" @change="editForm.show_on_all_pages = false" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            <span>Show on specific routes</span>
                                        </label>
                                    </div>
                                    </div>

                                    <div x-show="!editForm.show_on_all_pages" x-cloak class="mt-3">
                                    <select x-model="editForm.show_on_routes" multiple class="mt-2 block h-36 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                        <template x-for="option in routeOptions" :key="'edit-route-modal-' + option.name">
                                            <option :value="option.name" x-text="option.label + ' (' + option.uri + ')'">
                                            </option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500">Only full-page routes are listed.</p>
                                </div>
                            </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <x-danger-button type="button" @click="deleteEditingNotice()" x-bind:disabled="saving">
                                    Delete Notice
                                </x-danger-button>

                                <div class="flex items-center gap-2">
                                    <x-modal-secondary-button type="button" @click="closeEditModal()">Cancel</x-modal-secondary-button>
                                    <x-modal-primary-button type="button" @click="saveEditNotice()" x-bind:disabled="saving">
                                        <span x-show="!saving">Save Notice</span>
                                        <span x-show="saving" x-cloak>Saving...</span>
                                    </x-modal-primary-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-modal>
            </template>

            <template x-teleport="body">
                <x-modal name="admin-notices-reset-dismissals" maxWidth="md" focusable>
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-slate-900">Clear Dismissals</h3>
                        <p class="mt-2 text-sm text-slate-600">Show this notice again to everyone who dismissed it?</p>
                        <p class="mt-1 text-sm text-slate-600">This clears dismissed status for all users.</p>

                        <div class="mt-5 flex items-center justify-end gap-2">
                            <x-modal-secondary-button type="button" @click="closeResetDismissalsModal()">Cancel</x-modal-secondary-button>
                            <x-modal-primary-button type="button" @click="resetDismissalsForNotice()" x-bind:disabled="resettingDismissals">
                                <span x-show="!resettingDismissals">Clear Dismissals</span>
                                <span x-show="resettingDismissals" x-cloak>Clearing...</span>
                            </x-modal-primary-button>
                        </div>
                    </div>
                </x-modal>
            </template>
        </div>
    </div>
</x-app-layout>
