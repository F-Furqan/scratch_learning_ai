<?php

namespace Database\Seeders;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityVisibility;
use App\Models\BlockedWord;
use App\Models\CommunityGroup;
use App\Models\Course;
use App\Models\DiscussionForum;
use App\Models\DiscussionThread;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        BlockedWord::query()->firstOrCreate(
            ['word' => 'buy followers'],
            ['match_type' => 'contains', 'severity' => 3, 'is_active' => true, 'notes' => 'Commercial spam pattern.'],
        );

        $course = Course::query()->first();
        $user = User::query()->first();

        if (! $course || ! $user) {
            return;
        }

        $forum = DiscussionForum::query()->firstOrCreate(
            ['course_id' => $course->id, 'slug' => 'course-discussion'],
            [
                'created_by' => $user->id,
                'title' => 'Course Discussion',
                'description' => 'General discussion and peer support for this course.',
                'visibility' => CommunityVisibility::Members,
                'status' => CommunityContentStatus::Approved,
                'sort_order' => 10,
            ],
        );

        DiscussionThread::query()->firstOrCreate(
            ['discussion_forum_id' => $forum->id, 'slug' => 'welcome-and-introductions'],
            [
                'user_id' => $user->id,
                'title' => 'Welcome and introductions',
                'body' => 'Share what you are learning and what you want help with.',
                'status' => CommunityContentStatus::Approved,
                'is_pinned' => true,
                'last_activity_at' => now(),
            ],
        );

        CommunityGroup::query()->firstOrCreate(
            ['slug' => 'paid-members-lab'],
            [
                'course_id' => $course->id,
                'created_by' => $user->id,
                'name' => 'Paid Members Lab',
                'description' => 'Private discussion space for paid members and team seats.',
                'visibility' => CommunityVisibility::PaidMembers,
                'status' => CommunityContentStatus::Approved,
                'requires_paid_access' => true,
            ],
        );
    }
}
