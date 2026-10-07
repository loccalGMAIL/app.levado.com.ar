@props(['title', 'subtitle' => null])

{{-- Mobile: la descripción ocupa todo el ancho y los botones bajan en una fila de
     celdas de igual ancho. Desde `sm`: título a la izquierda, botones a la derecha.
     Los botones se pasan como hijos directos del slot (sin div envolvente). --}}
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <h2 class="text-base font-semibold text-corteza">{{ $title }}</h2>
        @if(isset($description) && $description->hasActualContent())
            <p class="text-sm text-masa-madre mt-0.5">{{ $description }}</p>
        @elseif($subtitle)
            <p class="text-sm text-masa-madre mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    @if($slot->hasActualContent())
        <div class="grid grid-flow-col auto-cols-fr gap-2 sm:flex sm:items-center sm:shrink-0 [&>*]:inline-flex [&>*]:items-center [&>*]:justify-center [&>*]:text-center">
            {{ $slot }}
        </div>
    @endif
</div>
