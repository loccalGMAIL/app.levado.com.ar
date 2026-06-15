<x-admin-layout>
    <x-slot name="title">Emails</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-corteza leading-tight">Emails</h2>
    </x-slot>

    <div class="py-10" x-data="{
        modal: null,
        tab: 'preview',
        openModal(type, label, previewUrl) {
            this.modal = { type, label, previewUrl };
            this.tab = 'preview';
        },
        closeModal() { this.modal = null; }
    }">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-masa-madre">
                Previsualizá y editá los emails que Levado envía automáticamente.
            </p>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Cuándo se envía</th>
                            <th class="px-4 py-3 font-medium">Destinatario</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        <tr>
                            <td class="px-4 py-4">
                                <p class="font-medium text-corteza">Invitación al equipo</p>
                                <p class="text-xs text-masa-madre mt-0.5">TeamInvitation</p>
                            </td>
                            <td class="px-4 py-4 text-masa-madre">
                                Al crear un tenant o al invitar un miembro del equipo
                            </td>
                            <td class="px-4 py-4 text-masa-madre">
                                El usuario invitado
                            </td>
                            <td class="px-4 py-4 text-right">
                                <button
                                    @click="openModal('team-invitation', 'Invitación al equipo', '{{ route('admin.mails.preview.team-invitation') }}')"
                                    class="text-sm text-corteza hover:text-horno font-medium transition-colors">
                                    Ver / Editar →
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-4 py-4">
                                <p class="font-medium text-corteza">Bienvenida</p>
                                <p class="text-xs text-masa-madre mt-0.5">WelcomeMail</p>
                            </td>
                            <td class="px-4 py-4 text-masa-madre">
                                Cuando un usuario nuevo acepta su invitación y crea la cuenta
                            </td>
                            <td class="px-4 py-4 text-masa-madre">
                                El nuevo usuario
                            </td>
                            <td class="px-4 py-4 text-right">
                                <button
                                    @click="openModal('welcome', 'Bienvenida', '{{ route('admin.mails.preview.welcome') }}')"
                                    class="text-sm text-corteza hover:text-horno font-medium transition-colors">
                                    Ver / Editar →
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        {{-- Modal email --}}
        <div x-show="modal !== null" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="closeModal()">

            <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl flex flex-col"
                style="max-height: 90vh;"
                @click.outside="closeModal()">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-miga flex-shrink-0">
                    <h3 class="text-lg font-semibold text-corteza" x-text="modal?.label"></h3>
                    <button @click="closeModal()" class="text-masa-madre hover:text-corteza text-2xl leading-none">&times;</button>
                </div>

                {{-- Tabs --}}
                <div class="flex border-b border-miga flex-shrink-0">
                    <button @click="tab = 'preview'"
                        :class="tab === 'preview' ? 'border-b-2 border-horno text-corteza font-medium' : 'text-masa-madre hover:text-corteza'"
                        class="px-6 py-3 text-sm transition-colors">
                        Preview
                    </button>
                    <button @click="tab = 'edit'"
                        :class="tab === 'edit' ? 'border-b-2 border-horno text-corteza font-medium' : 'text-masa-madre hover:text-corteza'"
                        class="px-6 py-3 text-sm transition-colors">
                        Editar contenido
                    </button>
                </div>

                {{-- Preview tab --}}
                <div x-show="tab === 'preview'" class="flex-1 overflow-hidden">
                    <iframe
                        x-bind:src="modal?.previewUrl"
                        class="w-full h-full border-0"
                        style="min-height: 500px;"
                        sandbox="allow-same-origin">
                    </iframe>
                </div>

                {{-- Edit tab --}}
                <div x-show="tab === 'edit'" class="flex-1 overflow-y-auto p-6">
                    <template x-if="modal">
                        <form method="POST" :action="`/admin/mails/${modal.type}`" class="space-y-5">
                            @csrf
                            @method('PATCH')

                            @php
                                $teamInvTpl = $templates->get('team-invitation');
                                $welcomeTpl = $templates->get('welcome');
                            @endphp

                            {{-- Los valores del template se inyectan vía Alpine según el tipo activo --}}
                            <div>
                                <label class="block text-sm font-medium text-corteza mb-1">
                                    Asunto del email
                                    <span class="text-xs font-normal text-masa-madre">(dejar vacío para usar el predeterminado)</span>
                                </label>
                                <input type="text" name="subject"
                                    x-bind:value="modal?.type === 'team-invitation'
                                        ? '{{ addslashes($teamInvTpl?->subject ?? '') }}'
                                        : '{{ addslashes($welcomeTpl?->subject ?? '') }}'"
                                    placeholder="Asunto predeterminado del sistema"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-corteza mb-1">
                                    Texto introductorio
                                    <span class="text-xs font-normal text-masa-madre">(se muestra antes del contenido principal)</span>
                                </label>
                                <textarea name="intro_text" rows="3"
                                    placeholder="Texto opcional que aparece al inicio del email..."
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno"
                                    x-text="modal?.type === 'team-invitation'
                                        ? '{{ addslashes($teamInvTpl?->intro_text ?? '') }}'
                                        : '{{ addslashes($welcomeTpl?->intro_text ?? '') }}'"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-corteza mb-1">
                                    Nota del pie
                                    <span class="text-xs font-normal text-masa-madre">(reemplaza "Que tu panadería siga creciendo.")</span>
                                </label>
                                <input type="text" name="footer_note"
                                    x-bind:value="modal?.type === 'team-invitation'
                                        ? '{{ addslashes($teamInvTpl?->footer_note ?? '') }}'
                                        : '{{ addslashes($welcomeTpl?->footer_note ?? '') }}'"
                                    placeholder="Que tu panadería siga creciendo."
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="closeModal()"
                                    class="px-4 py-2 text-sm border border-miga rounded-md hover:bg-miga transition-colors">
                                    Cancelar
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm bg-horno text-white rounded-md hover:bg-corteza transition-colors">
                                    Guardar cambios
                                </button>
                            </div>
                        </form>
                    </template>
                </div>

            </div>
        </div>
    </div>
</x-admin-layout>
