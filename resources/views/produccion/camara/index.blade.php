<x-app-layout>
    <x-slot name="title">Cierre de cámara</x-slot>

    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl space-y-5">

            <x-list-header title="Cierre de cámara" subtitle="Contá lo que quedó en la cámara: lo que falta se horneó y pasa a terminado.">
                @can('manage-costs')
                    <form method="POST" action="{{ route('produccion.camara.store') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">
                            {{ $open !== null ? 'Seguir cierre #'.$open->number : 'Cerrar cámara' }}
                        </button>
                    </form>
                @endcan
            </x-list-header>

            <section class="bg-superficie rounded-lg shadow">
                <header class="px-4 py-3 border-b border-miga">
                    <h3 class="text-sm font-semibold text-corteza">Cierres</h3>
                </header>
                @if($closings->isEmpty())
                    <p class="px-4 py-4 text-sm text-masa-madre">Todavía no se cerró la cámara.</p>
                @else
                    <ul class="divide-y divide-miga">
                        @foreach($closings as $closing)
                            <li>
                                <a href="{{ route('produccion.camara.show', $closing) }}"
                                    class="px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-harina transition-colors">
                                    <span class="text-sm text-corteza">
                                        <span class="font-medium">#{{ $closing->number }}</span>
                                        <span class="text-masa-madre">· {{ ($closing->confirmed_at ?? $closing->opened_at)->format('d/m/Y H:i') }}</span>
                                    </span>
                                    <span class="flex items-center gap-3">
                                        <span class="text-xs text-masa-madre">{{ $closing->lines_count }} artículos</span>
                                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $closing->status->badgeClasses() }}">{{ $closing->status->label() }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

        </div>
    </div>
</x-app-layout>
