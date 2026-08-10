<?php

namespace App\Services\Operations;

use App\Models\OperationalAlert;
use Illuminate\Support\Facades\Schema;

final class OperationalAlertStore
{
    /** @param array<string, bool|float|int|string|null> $context */
    public function record(
        string $key,
        string $title,
        string $message,
        array $context = [],
        string $source = 'runtime',
        string $severity = 'critical',
    ): ?OperationalAlert {
        if (! Schema::hasTable('operations_alerts')) {
            return null;
        }

        $alert = OperationalAlert::query()->firstOrNew(['key' => $key]);
        $isNew = ! $alert->exists;

        $alert->forceFill([
            'source' => $source,
            'severity' => $severity,
            'status' => 'open',
            'title' => $title,
            'message' => $message,
            'occurrence_count' => $isNew ? 1 : $alert->occurrence_count + 1,
            'first_detected_at' => $isNew ? now() : $alert->first_detected_at,
            'last_detected_at' => now(),
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_note' => null,
            'metadata' => $context,
        ])->save();

        return $alert;
    }

    /** @param list<string> $activeKeys */
    public function resolveMissingMonitorAlerts(array $activeKeys): int
    {
        if (! Schema::hasTable('operations_alerts')) {
            return 0;
        }

        return OperationalAlert::query()
            ->where('source', 'monitor')
            ->whereIn('status', ['open', 'acknowledged'])
            ->when($activeKeys !== [], fn ($query) => $query->whereNotIn('key', $activeKeys))
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolution_note' => 'Automatically resolved after a successful monitoring inspection.',
                'updated_at' => now(),
            ]);
    }
}
