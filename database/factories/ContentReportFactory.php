<?php

namespace Database\Factories;

use App\Enums\CommunityReportStatus;
use App\Models\ContentReport;
use App\Models\CourseQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentReport>
 */
class ContentReportFactory extends Factory
{
    protected $model = ContentReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => CourseQuestion::class,
            'reportable_id' => CourseQuestion::factory(),
            'reviewed_by' => null,
            'status' => CommunityReportStatus::Open,
            'reason' => fake()->randomElement(['spam', 'abuse', 'misinformation']),
            'details' => fake()->sentence(),
            'reviewed_at' => null,
            'resolution_note' => null,
        ];
    }
}
