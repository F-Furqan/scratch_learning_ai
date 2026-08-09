<?php

namespace App\Services\Payments;

use App\Models\PaymentAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PaymentAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(string $action, ?Model $auditable = null, ?User $user = null, ?array $before = null, ?array $after = null, ?array $metadata = null, ?Request $request = null): PaymentAuditLog
    {
        return PaymentAuditLog::query()->create([
            'actor_id' => $request?->user()?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
