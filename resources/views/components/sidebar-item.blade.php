@props(['href', 'label', 'active' => false, 'icon' => '', 'id' => null])

<a href="{{ $href }}"
    @if($id) id="{{ $id }}" @endif
    class="flex items-center gap-2.5 px-4 py-2 text-[13.5px] transition-colors
        border-l-[3px]
        {{ $active
            ? 'border-horno bg-horno/10 text-harina font-medium'
            : 'border-transparent text-harina/65 hover:bg-harina/5 hover:text-harina font-normal' }}">
    <svg class="w-[17px] h-[17px] shrink-0 {{ $active ? 'opacity-100' : 'opacity-60' }}"
        fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        {!! $icon !!}
    </svg>
    {{ $label }}
</a>
