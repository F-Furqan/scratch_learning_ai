<?php

namespace App\Models;

use App\Enums\PublishStatus;
use Database\Factories\BlogCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['blog_post_id', 'user_id', 'parent_id', 'body', 'status'])]
class BlogComment extends Model
{
    /** @use HasFactory<BlogCommentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<self, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
        ];
    }
}
