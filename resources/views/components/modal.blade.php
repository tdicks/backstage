@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
    '6xl' => 'sm:max-w-6xl',
][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div
    x-data="{
        modalName: @js($name),
        show: @js($show),
        stack() {
            if (!Array.isArray(window.__backstageModalStack)) {
                window.__backstageModalStack = []
            }

            return window.__backstageModalStack
        },
        pushToStack() {
            const modalStack = this.stack()
            const existingIndex = modalStack.indexOf(this.modalName)

            if (existingIndex !== -1) {
                modalStack.splice(existingIndex, 1)
            }

            modalStack.push(this.modalName)
        },
        removeFromStack() {
            const modalStack = this.stack()
            const existingIndex = modalStack.lastIndexOf(this.modalName)

            if (existingIndex !== -1) {
                modalStack.splice(existingIndex, 1)
            }
        },
        isTopModal() {
            const modalStack = this.stack()

            return modalStack.length > 0 && modalStack[modalStack.length - 1] === this.modalName
        },
        openModal() {
            this.show = true
            this.pushToStack()
        },
        closeModal() {
            this.show = false
            this.removeFromStack()
        },
        focusables() {
            // All focusable element types...
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="
        if (show) {
            pushToStack()
        }

        $watch('show', value => {
            if (value) {
                {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
            } else {
                removeFromStack()
                $dispatch('modal-closed', { name: modalName })
            }
        })
    "
    x-on:open-modal.window="$event.detail == modalName ? openModal() : null"
    x-on:close-modal.window="$event.detail == modalName ? closeModal() : null"
    x-on:close.stop="closeModal()"
    x-on:keydown.escape.window="if (show && isTopModal()) { closeModal() }"
    x-on:keydown.tab.prevent="if (show && isTopModal()) { $event.shiftKey || nextFocusable().focus() }"
    x-on:keydown.shift.tab.prevent="if (show && isTopModal()) { prevFocusable().focus() }"
    x-show="show"
    data-modal-overlay
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6 sm:px-0"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="fixed inset-0 z-40 transform transition-all"
        x-on:click="if (isTopModal()) { closeModal() }"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-black/40"></div>
    </div>

    <div
        x-show="show"
        class="relative z-50 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }}"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]"
    >
        {{ $slot }}
    </div>
</div>
