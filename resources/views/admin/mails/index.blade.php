<x-admin-layout>
    <x-slot name="title">Emails</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-corteza leading-tight">Emails</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-masa-madre">
                Previsualizá los emails que Levado envía automáticamente. Los datos mostrados son de ejemplo.
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
                                <a href="{{ route('admin.mails.preview.team-invitation') }}"
                                   target="_blank"
                                   class="text-sm text-corteza hover:text-horno hover:underline font-medium">
                                    Ver preview →
                                </a>
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
                                <a href="{{ route('admin.mails.preview.welcome') }}"
                                   target="_blank"
                                   class="text-sm text-corteza hover:text-horno hover:underline font-medium">
                                    Ver preview →
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-admin-layout>
