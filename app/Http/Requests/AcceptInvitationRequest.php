<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    private ?Invitation $invitation = null;

    private bool $invitationResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Resuelve la invitación por el token de la ruta una sola vez por request,
     * reutilizada acá y en InvitationController::accept().
     */
    public function invitation(): ?Invitation
    {
        if (! $this->invitationResolved) {
            $this->invitation = Invitation::where('token', $this->route('token'))->first();
            $this->invitationResolved = true;
        }

        return $this->invitation;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $invitationEmail = $this->invitation()?->email;
        $userExists = $invitationEmail && User::where('email', $invitationEmail)->exists();

        if ($userExists) {
            return [];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
