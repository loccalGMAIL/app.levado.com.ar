<x-app-layout>
    <x-slot name="title">Alertas</x-slot>

    <div class="py-6 px-6 lg:px-8 space-y-5 max-w-3xl">

        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-corteza">Alertas</h1>
                <p class="text-sm text-masa-madre mt-1">
                    {{ $unreadCount }} sin leer.
                    @can('edit-settings')
                        <a href="{{ route('alerts.edit') }}" class="text-horno hover:underline">Configurar alertas →</a>
                    @endcan
                </p>
            </div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                        class="text-[13px] font-medium text-masa-madre border border-miga rounded-lg px-3 py-2 bg-white hover:bg-miga hover:text-corteza transition-colors">
                        Marcar todas como leídas
                    </button>
                </form>
            @endif
        </div>

        {{-- Filtro --}}
        <div class="flex items-center gap-2">
            @foreach(['unread' => 'Sin leer', 'all' => 'Todas'] as $key => $label)
                <a href="{{ route('notifications.index', ['filter' => $key]) }}"
                    class="px-3 py-1.5 rounded-full text-[12.5px] font-medium border transition-colors
                        {{ $filter === $key ? 'bg-corteza text-white border-corteza' : 'bg-harina text-masa-madre border-miga hover:border-brown-300' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @php
            $pill = fn (string $s) => match ($s) {
                'red'   => 'bg-red-50 text-red-700 border-red-200',
                'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                'blue'  => 'bg-blue-50 text-blue-700 border-blue-200',
                default => 'bg-miga text-masa-madre border-miga',
            };
        @endphp

        @if($notifications->total() === 0)
            <div class="bg-white border border-miga rounded-xl py-16 text-center text-masa-madre text-sm">
                {{ $filter === 'unread' ? 'No tenés alertas sin leer.' : 'No hay alertas.' }}
            </div>
        @else
            <div class="bg-white border border-miga rounded-xl divide-y divide-miga/60 overflow-hidden">
                @foreach($notifications as $notification)
                    <div class="flex items-start gap-3 p-4 {{ $notification->isRead() ? 'opacity-60' : '' }}">
                        <span class="text-xl leading-none mt-0.5 shrink-0">{{ $notification->type->icon() }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold border {{ $pill($notification->severity) }}">
                                    {{ $notification->type->label() }}
                                </span>
                                @unless($notification->isRead())
                                    <span class="w-1.5 h-1.5 rounded-full bg-horno shrink-0" title="Sin leer"></span>
                                @endunless
                                <span class="text-[11px] text-masa-madre/60">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-[13.5px] text-corteza font-medium mt-1">
                                @if($notification->action_url)
                                    <a href="{{ $notification->action_url }}" class="hover:text-horno transition-colors">{{ $notification->title }}</a>
                                @else
                                    {{ $notification->title }}
                                @endif
                            </p>
                            @if($notification->body)
                                <p class="text-[12.5px] text-masa-madre mt-0.5">{{ $notification->body }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @unless($notification->isRead())
                                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" title="Marcar como leída"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('notifications.dismiss', $notification) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Descartar"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-masa-madre hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($notifications->hasPages())
                <div>{{ $notifications->links() }}</div>
            @endif
        @endif

    </div>
</x-app-layout>
