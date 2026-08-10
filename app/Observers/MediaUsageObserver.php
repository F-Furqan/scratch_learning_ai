<?php

namespace App\Observers;

use App\Services\Media\MediaUsageTracker;
use Illuminate\Database\Eloquent\Model;

final readonly class MediaUsageObserver
{
    public function __construct(private MediaUsageTracker $tracker) {}

    public function saved(Model $model): void
    {
        $this->tracker->syncKnown($model);
    }

    public function deleted(Model $model): void
    {
        $this->tracker->clear($model);
    }

    public function restored(Model $model): void
    {
        $this->tracker->syncKnown($model);
    }
}
