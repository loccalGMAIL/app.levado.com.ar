<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $this->notifications->syncStateAlerts($tenant);

        $filter = request('filter') === 'all' ? 'all' : 'unread';

        $notifications = Notification::where('tenant_id', $tenant->id)
            ->active()
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $unreadCount = Notification::where('tenant_id', $tenant->id)->active()->unread()->count();

        return view('notifications.index', compact('notifications', 'filter', 'unreadCount'));
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $this->notifications->markRead($notification);

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        $this->notifications->markAllRead(app(Tenant::class));

        return back();
    }

    public function dismiss(Notification $notification): RedirectResponse
    {
        $this->notifications->dismiss($notification);

        return back();
    }
}
