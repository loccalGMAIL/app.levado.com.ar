<?php

namespace App\Http\Controllers;

use App\Enums\MobileShortcut;
use App\Http\Requests\UpdateMobileShortcutsRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MobileShortcutSettingsController extends Controller
{
    public function edit(): View
    {
        $selected = app(Tenant::class)->mobileShortcuts();

        $optionsByGroup = collect(MobileShortcut::cases())->groupBy(fn (MobileShortcut $s) => $s->group());

        return view('mobile-shortcuts.edit', compact('selected', 'optionsByGroup'));
    }

    public function update(UpdateMobileShortcutsRequest $request): RedirectResponse
    {
        /** @var array<int, string> $shortcuts */
        $shortcuts = $request->validated('shortcuts');

        app(Tenant::class)->setSetting('mobile_nav.shortcuts', implode(',', $shortcuts));

        return back()->with('status', 'mobile-shortcuts-updated');
    }
}
