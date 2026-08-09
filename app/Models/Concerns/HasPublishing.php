<?php

namespace App\Models\Concerns;

use App\Enums\PublishStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasPublishing
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where($this->getTable().'.status', PublishStatus::Published->value)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull($this->getTable().'.published_at')
                    ->orWhere($this->getTable().'.published_at', '<=', now());
            });
    }

    public function isPublished(): bool
    {
        return $this->hasPublishStatus(PublishStatus::Published)
            && $this->isPublishedAtInPast();
    }

    public function markPublished(?Carbon $publishedAt = null): static
    {
        $this->forceFill([
            'status' => PublishStatus::Published,
            'published_at' => $publishedAt ?? now(),
        ]);

        return $this;
    }

    public function submitForReview(): static
    {
        $this->forceFill([
            'status' => PublishStatus::Pending,
            'published_at' => null,
        ]);

        return $this;
    }

    public function markDraft(): static
    {
        $this->forceFill([
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);

        return $this;
    }

    public function reject(?string $reason = null): static
    {
        $attributes = [
            'status' => PublishStatus::Rejected,
            'published_at' => null,
        ];

        if (array_key_exists('rejection_reason', $this->getAttributes()) || $this->isFillable('rejection_reason')) {
            $attributes['rejection_reason'] = $reason;
        }

        $this->forceFill($attributes);

        return $this;
    }

    public function archive(): static
    {
        $this->forceFill([
            'status' => PublishStatus::Archived,
            'published_at' => null,
        ]);

        return $this;
    }

    private function hasPublishStatus(PublishStatus $status): bool
    {
        $actual = $this->getAttribute('status');

        if ($actual instanceof PublishStatus) {
            return $actual === $status;
        }

        return $actual === $status->value;
    }

    private function isPublishedAtInPast(): bool
    {
        $publishedAt = $this->getAttribute('published_at');

        if ($publishedAt === null) {
            return true;
        }

        if ($publishedAt instanceof CarbonInterface) {
            return $publishedAt->isPast();
        }

        return is_string($publishedAt) && Carbon::parse($publishedAt)->isPast();
    }
}
