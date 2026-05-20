<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessRequest;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function edit(): View
    {
        $tenant = app(Tenant::class);
        $totalFixedCosts = $tenant->fixedCosts()->where('active', true)->sum('monthly_amount');
        $overheadPerHour = $tenant->productive_hours_month > 0
            ? (float) $totalFixedCosts / $tenant->productive_hours_month
            : null;

        $invitationMessage = $tenant->getSetting('invitation_message', '');

        return view('business.edit', compact('tenant', 'totalFixedCosts', 'overheadPerHour', 'invitationMessage'));
    }

    public function update(UpdateBusinessRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }

            $data['logo_path'] = $request->file('logo')
                ->store("logos/{$tenant->id}", 'public');
        }

        $tenant->update($data);

        $tenant->setSetting('invitation_message', $request->validated('invitation_message') ?? '');

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'tenant',
            targetId: $tenant->id,
            action: 'business.updated',
            tenantId: $tenant->id,
        );

        return back()->with('status', 'business-updated');
    }
}
