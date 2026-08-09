<?php

namespace App\Models\Concerns;

use App\Models\ApprovalHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovalHistories
{
    /**
     * @return MorphMany<ApprovalHistory, $this>
     */
    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'subject')->latest();
    }
}
