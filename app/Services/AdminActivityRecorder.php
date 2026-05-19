<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminActivityRecorder
{
    public function __construct(private readonly Request $request) {}

    public function record(
        User $actor,
        string $targetType,
        int $targetId,
        string $action,
        array $payload = [],
    ): void {
        AdminAuditLog::create([
            'actor_user_id' => $actor->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'payload' => $payload ?: null,
            'was_impersonating' => $this->request->session()->has('impersonating_tenant_id'),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
