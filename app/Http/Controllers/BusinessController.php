<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function edit(): View
    {
        return view('business.edit', [
            'tenant' => app(Tenant::class),
        ]);
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

        return back()->with('status', 'business-updated');
    }
}
