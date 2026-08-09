<?php

namespace Database\Factories;

use App\Enums\ScheduledPublicationStatus;
use App\Models\BlogPost;
use App\Models\EditorialRevision;
use App\Models\ScheduledPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledPublication>
 */
class ScheduledPublicationFactory extends Factory
{
    protected $model = ScheduledPublication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $post = BlogPost::factory();

        return [
            'publishable_type' => BlogPost::class,
            'publishable_id' => $post,
            'editorial_revision_id' => EditorialRevision::factory(),
            'created_by' => User::factory(),
            'approved_by' => null,
            'publish_at' => now()->addDay(),
            'timezone' => 'UTC',
            'status' => ScheduledPublicationStatus::Scheduled,
            'published_at' => null,
            'failure_reason' => null,
            'metadata' => [],
        ];
    }
}
