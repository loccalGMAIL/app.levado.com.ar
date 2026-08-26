@props(['title', 'subtitle' => null])

<div class="flex items-center justify-between gap-3">
    <div>
        <h2 class="text-base font-semibold text-corteza">{{ $title }}</h2>
        @if($subtitle)
            <p class="text-sm text-masa-madre mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
