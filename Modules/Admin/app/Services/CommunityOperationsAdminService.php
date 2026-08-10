<?php

namespace Modules\Admin\Services;

use App\Enums\CommunityContentStatus;
use App\Enums\PublishStatus;
use App\Models\CommunityGroupMember;
use App\Models\CommunityReaction;
use App\Models\CommunityReputationScore;
use App\Models\CourseQuestion;
use App\Models\DiscussionPost;
use App\Models\LessonQuestionAnswer;
use App\Models\ModerationQueueItem;
use App\Models\ReputationEvent;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final class CommunityOperationsAdminService
{
    /** @var list<string> */
    public const RESOURCES = [
        'course_questions',
        'lesson_question_answers',
        'discussion_posts',
        'community_reactions',
        'reputation_events',
        'community_reputation_scores',
        'community_group_members',
        'moderation_history',
    ];

    public function supports(string $resource): bool
    {
        return in_array($resource, self::RESOURCES, true);
    }

    public function readOnly(string $resource): bool
    {
        return $this->supports($resource);
    }

    public function exportable(string $resource): bool
    {
        return $this->supports($resource);
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(string $resource): array
    {
        $columns = match ($resource) {
            'course_questions' => ['title', 'student', 'course', 'lesson', 'status', 'answers', 'accepted_answer'],
            'lesson_question_answers' => ['answer', 'author', 'question', 'course', 'status', 'accepted', 'upvotes'],
            'discussion_posts' => ['post', 'author', 'thread', 'forum', 'status', 'replies', 'upvotes'],
            'community_reactions' => ['user', 'type', 'value', 'target', 'created_at'],
            'reputation_events' => ['user', 'type', 'points', 'reason', 'actor', 'subject', 'created_at'],
            'community_reputation_scores' => ['user', 'points', 'level', 'updated_at'],
            'community_group_members' => ['user', 'group', 'course', 'role', 'status', 'joined_at'],
            'moderation_history' => ['subject', 'status', 'reason', 'spam_score', 'reporter', 'moderator', 'reviewed_at'],
            default => [],
        };

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => str($key)->replace('_', ' ')->headline()->toString(),
        ], $columns);
    }

    /** @return array<int, array<string, mixed>> */
    public function fields(string $resource): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function filters(string $resource): array
    {
        $filters = [$this->field('search', 'Search', 'search')];

        if (in_array($resource, ['course_questions', 'lesson_question_answers', 'discussion_posts', 'community_group_members', 'moderation_history'], true)) {
            $options = $resource === 'course_questions'
                ? $this->enumOptions(PublishStatus::cases())
                : ($resource === 'community_group_members'
                    ? $this->valueOptions(['active', 'removed'])
                    : $this->enumOptions(CommunityContentStatus::cases()));
            $filters[] = $this->field('status', 'Status', 'select', $options);
        }

        return $filters;
    }

    /** @return array<int, array<string, mixed>> */
    public function bulkActions(string $resource): array
    {
        return [];
    }

    /** @return array<string, int> */
    public function metrics(string $resource): array
    {
        return match ($resource) {
            'course_questions' => ['pending' => CourseQuestion::query()->where('status', PublishStatus::Pending->value)->count(), 'answered' => CourseQuestion::query()->whereNotNull('accepted_answer_id')->count()],
            'lesson_question_answers' => ['pending' => LessonQuestionAnswer::query()->where('status', CommunityContentStatus::Pending->value)->count(), 'accepted' => LessonQuestionAnswer::query()->whereNotNull('accepted_at')->count()],
            'discussion_posts' => ['pending' => DiscussionPost::query()->where('status', CommunityContentStatus::Pending->value)->count(), 'approved' => DiscussionPost::query()->where('status', CommunityContentStatus::Approved->value)->count()],
            'community_reactions' => ['reactions' => CommunityReaction::query()->count(), 'members' => CommunityReaction::query()->distinct()->count('user_id')],
            'reputation_events' => ['events' => ReputationEvent::query()->count(), 'points' => (int) ReputationEvent::query()->sum('points')],
            'community_reputation_scores' => ['members' => CommunityReputationScore::query()->count(), 'points' => (int) CommunityReputationScore::query()->sum('points')],
            'community_group_members' => ['active' => CommunityGroupMember::query()->where('status', 'active')->count(), 'removed' => CommunityGroupMember::query()->where('status', 'removed')->count()],
            'moderation_history' => ['reviewed' => ModerationQueueItem::query()->whereNotNull('reviewed_at')->count(), 'spam' => ModerationQueueItem::query()->whereNotNull('reviewed_at')->where('status', CommunityContentStatus::Spam->value)->count()],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function row(string $resource, Model $record): array
    {
        return match ($resource) {
            'course_questions' => $this->questionRow($record),
            'lesson_question_answers' => $this->answerRow($record),
            'discussion_posts' => $this->postRow($record),
            'community_reactions' => $this->reactionRow($record),
            'reputation_events' => $this->reputationEventRow($record),
            'community_reputation_scores' => $this->reputationScoreRow($record),
            'community_group_members' => $this->groupMemberRow($record),
            'moderation_history' => $this->moderationHistoryRow($record),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function questionRow(Model $record): array
    {
        abort_unless($record instanceof CourseQuestion, 500);

        return $this->baseRow($record) + [
            'title' => $record->title,
            'student' => $record->user?->email,
            'course' => $record->course?->title,
            'lesson' => $record->lesson?->title,
            'status' => $this->enumValue($record->status),
            'answers' => $record->answers_count ?? 0,
            'accepted_answer' => $record->acceptedAnswer?->user?->name,
            'workflow_actions' => [$this->manageAction('question', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function answerRow(Model $record): array
    {
        abort_unless($record instanceof LessonQuestionAnswer, 500);

        return $this->baseRow($record) + [
            'answer' => $record->body,
            'author' => $record->user?->email,
            'question' => $record->question?->title,
            'course' => $record->question?->course?->title,
            'status' => $this->enumValue($record->status),
            'accepted' => $record->accepted_at ? 'Yes' : 'No',
            'upvotes' => $record->upvotes_count,
            'workflow_actions' => [$this->manageAction('answer', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function postRow(Model $record): array
    {
        abort_unless($record instanceof DiscussionPost, 500);

        return $this->baseRow($record) + [
            'post' => $record->body,
            'author' => $record->user?->email,
            'thread' => $record->thread?->title,
            'forum' => $record->thread?->forum?->title,
            'status' => $this->enumValue($record->status),
            'replies' => $record->replies_count ?? 0,
            'upvotes' => $record->upvotes_count,
            'workflow_actions' => [$this->manageAction('discussion-post', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function reactionRow(Model $record): array
    {
        abort_unless($record instanceof CommunityReaction, 500);

        return $this->baseRow($record) + [
            'user' => $record->user?->email,
            'type' => $this->enumValue($record->type),
            'value' => $record->value,
            'target' => $this->modelLabel($record->reactable),
            'created_at' => $this->dateTime($record->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function reputationEventRow(Model $record): array
    {
        abort_unless($record instanceof ReputationEvent, 500);

        return $this->baseRow($record) + [
            'user' => $record->user?->email,
            'type' => $this->enumValue($record->type),
            'points' => $record->points,
            'reason' => $record->reason,
            'actor' => $record->actor?->email,
            'subject' => $this->modelLabel($record->subject),
            'created_at' => $this->dateTime($record->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function reputationScoreRow(Model $record): array
    {
        abort_unless($record instanceof CommunityReputationScore, 500);

        return $this->baseRow($record) + [
            'user' => $record->user?->email,
            'points' => $record->points,
            'level' => $record->level,
            'updated_at' => $this->dateTime($record->updated_at),
            'workflow_actions' => [$this->manageAction('reputation-score', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function groupMemberRow(Model $record): array
    {
        abort_unless($record instanceof CommunityGroupMember, 500);

        return $this->baseRow($record) + [
            'user' => $record->user?->email,
            'group' => $record->group?->name,
            'course' => $record->group?->course?->title,
            'role' => $record->role,
            'status' => $record->status,
            'joined_at' => $this->dateTime($record->joined_at),
            'workflow_actions' => [$this->manageAction('group-member', $record)],
        ];
    }

    /** @return array<string, mixed> */
    private function moderationHistoryRow(Model $record): array
    {
        abort_unless($record instanceof ModerationQueueItem, 500);

        return $this->baseRow($record) + [
            'subject' => $this->modelLabel($record->subject),
            'status' => $this->enumValue($record->status),
            'reason' => $record->reason,
            'spam_score' => $record->spam_score,
            'reporter' => $record->reporter?->email,
            'moderator' => $record->assignee?->email,
            'reviewed_at' => $this->dateTime($record->reviewed_at),
        ];
    }

    /** @return array{id: mixed, form: array<mixed>} */
    private function baseRow(Model $record): array
    {
        return ['id' => $record->getKey(), 'form' => []];
    }

    /** @return array{label: string, url: string, tone: string, method: string} */
    private function manageAction(string $type, Model $record): array
    {
        return [
            'label' => 'Manage',
            'url' => route('admin.operational-records.show', ['type' => $type, 'id' => $record->getKey()], false),
            'tone' => 'default',
            'method' => 'get',
        ];
    }

    /** @param array<int, BackedEnum> $cases @return array<int, array{label: string, value: string}> */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => ['label' => str((string) $case->value)->replace('_', ' ')->headline()->toString(), 'value' => (string) $case->value], $cases);
    }

    /** @param list<string> $values @return array<int, array{label: string, value: string}> */
    private function valueOptions(array $values): array
    {
        return array_map(fn (string $value): array => ['label' => str($value)->replace('_', ' ')->headline()->toString(), 'value' => $value], $values);
    }

    /** @param array<int, array{label: string, value: string|int}> $options @return array<string, mixed> */
    private function field(string $key, string $label, string $type, array $options = []): array
    {
        return compact('key', 'label', 'type', 'options') + ['required' => false];
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toDateTimeString() : null;
    }

    private function modelLabel(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        return (string) ($model->getAttribute('title')
            ?? $model->getAttribute('name')
            ?? $model->getAttribute('body')
            ?? class_basename($model).' #'.$model->getKey());
    }
}
