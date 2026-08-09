<?php

namespace App\Models;

use App\Enums\LeadSubmissionStatus;
use Database\Factories\LeadSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_magnet_id', 'newsletter_campaign_id', 'user_id', 'email', 'name', 'status', 'source_url', 'metadata'])]
class LeadSubmission extends Model
{
    /** @use HasFactory<LeadSubmissionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LeadMagnet, $this>
     */
    public function leadMagnet(): BelongsTo
    {
        return $this->belongsTo(LeadMagnet::class);
    }

    /**
     * @return BelongsTo<NewsletterCampaign, $this>
     */
    public function newsletterCampaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'status' => LeadSubmissionStatus::class,
            'metadata' => 'array',
        ];
    }
}
