<div x-show="openAttachments" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="closeAttachmentsModal()"></div>
<div x-show="openAttachments" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <section class="flex max-h-[calc(100vh-2rem)] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-left text-slate-900 shadow-2xl" role="dialog" aria-modal="true" aria-label="Attachments" @click.stop>
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <h4 class="text-lg font-semibold text-slate-900">Attachments</h4>
                <p class="mt-1 text-sm text-slate-600">Upload a file or add a link for this item.</p>
            </div>
            <button type="button" @click="closeAttachmentsModal()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400" aria-label="Close attachments modal" title="Close">
                <x-heroicon-m-x-mark class="h-5 w-5" aria-hidden="true" />
            </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
            <p x-show="attachmentsLoading && !attachmentsLoaded" x-cloak class="text-sm text-slate-600">Loading attachments...</p>
            <p x-show="attachmentsError" x-text="attachmentsError" class="text-sm text-rose-700" x-cloak></p>

            <template x-if="attachmentsLoaded && attachments.length === 0">
                <p class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-600">No attachments yet.</p>
            </template>

            <div class="space-y-3" x-show="attachments.length > 0" x-cloak>
                <template x-for="attachment in attachments" :key="attachment.id">
                    <article class="rounded-lg border border-slate-200 bg-white p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex min-w-0 items-baseline gap-2 text-sm text-slate-900">
                                    <span class="min-w-0 truncate font-semibold" x-text="attachment.label || attachment.original_filename || attachment.url"></span>
                                    <span
                                        x-show="attachment.type === 'file' && attachment.size_bytes"
                                        x-cloak
                                        class="shrink-0 text-xs font-normal text-slate-500"
                                        x-text="attachmentSizeLabel(attachment.size_bytes)"
                                    ></span>
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span class="inline-flex items-center" x-show="attachment.type === 'link'" title="Link attachment">
                                        <x-heroicon-m-link class="h-3.5 w-3.5" aria-hidden="true" />
                                        <span class="sr-only">Link attachment</span>
                                    </span>
                                    <span class="inline-flex items-center" x-show="attachment.type === 'file'" title="File attachment">
                                        <x-heroicon-m-document class="h-3.5 w-3.5" aria-hidden="true" />
                                        <span class="sr-only">File attachment</span>
                                    </span>
                                    <span x-show="attachment.uploader_name" x-text="`by ${attachment.uploader_name}`"></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <a
                                    x-show="attachment.type === 'file'"
                                    :href="attachment.download_url"
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    <x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Download</span>
                                </a>
                                <a
                                    x-show="attachment.type === 'link'"
                                    :href="attachment.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                >
                                    <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Open</span>
                                </a>
                                <button
                                    type="button"
                                    x-show="canManageAttachments"
                                    x-bind:disabled="deletingAttachmentId === attachment.id"
                                    @click="removeAttachment(attachment.id)"
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <x-heroicon-m-trash class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <form x-show="canManageAttachments" x-cloak @submit.prevent="submitAttachmentForm()" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-white p-4 text-left">
                <h5 class="text-sm font-semibold text-slate-900">Add attachment</h5>

                <div class="flex gap-4 text-sm text-slate-700">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="attachment_type" value="link" x-model="attachmentForm.type" class="border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>Link</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="attachment_type" value="file" x-model="attachmentForm.type" class="border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>File</span>
                    </label>
                </div>

                <div x-show="attachmentForm.type === 'link'" x-cloak>
                    <x-input-label value="URL" class="text-left" />
                    <x-text-input x-model="attachmentForm.url" type="url" placeholder="https://" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                </div>

                <div x-show="attachmentForm.type === 'file'" x-cloak>
                    <x-input-label value="File" class="text-left" />
                    <input x-ref="attachmentFileInput" type="file" @change="onAttachmentFileSelected($event)" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700" />
                    <p class="mt-1 text-xs text-slate-500">Maximum 10MB per file.</p>
                </div>

                <div>
                    <x-input-label value="Label (optional)" class="text-left" />
                    <x-text-input x-model="attachmentForm.label" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                </div>

                <p x-show="attachmentFormError" x-text="attachmentFormError" class="text-sm text-rose-700" x-cloak></p>
                <p x-show="attachmentsFeedback" x-text="attachmentsFeedback" class="text-sm text-emerald-700" x-cloak></p>

                <div class="flex justify-end">
                    <x-modal-primary-button x-bind:disabled="attachmentFormBusy">
                        <span x-text="attachmentFormBusy ? 'Saving...' : 'Add attachment'"></span>
                    </x-modal-primary-button>
                </div>
            </form>
        </div>
    </section>
</div>
