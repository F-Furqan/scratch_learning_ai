<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublishing;
use Database\Factories\BlogFaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['blog_post_id', 'question', 'answer', 'sort_order', 'status', 'published_at'])]
class BlogFaq extends Model
{
    /** @use HasFactory<BlogFaqFactory> */
    use HasFactory, HasPublishing;

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
