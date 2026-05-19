@props([
    'name',
    'title',
    'maxWidth' => 'lg',
    'show' => false,
])

<x-modal :name="$name" :max-width="$maxWidth" :show="$show" focusable>
    <div class="bg-white rounded-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-miga">
            <h3 class="text-base font-semibold text-corteza">{{ $title }}</h3>
            <button type="button"
                x-on:click="$dispatch('close-modal', '{{ $name }}')"
                class="text-masa-madre hover:text-corteza transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="px-6 py-5">
            {{ $slot }}
        </div>
    </div>
</x-modal>
