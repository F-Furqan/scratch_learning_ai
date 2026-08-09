<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\BloggerStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserStatus $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'status', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return HasOne<BloggerProfile, $this>
     */
    public function bloggerProfile(): HasOne
    {
        return $this->hasOne(BloggerProfile::class);
    }

    /**
     * @return HasOne<InstructorProfile, $this>
     */
    public function instructorProfile(): HasOne
    {
        return $this->hasOne(InstructorProfile::class);
    }

    /**
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'created_by');
    }

    /**
     * @return HasMany<BlogPost, $this>
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id');
    }

    /**
     * @return BelongsToMany<AuthorBadge, $this>
     */
    public function authorBadges(): BelongsToMany
    {
        return $this->belongsToMany(AuthorBadge::class, 'author_badge_user')
            ->withPivot(['awarded_by', 'awarded_at', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'author_id');
    }

    /**
     * @return HasMany<MediaAsset, $this>
     */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'uploaded_by');
    }

    /**
     * @return HasMany<CreatorAgreementAcceptance, $this>
     */
    public function creatorAgreementAcceptances(): HasMany
    {
        return $this->hasMany(CreatorAgreementAcceptance::class);
    }

    /**
     * @return HasMany<CreatorContentDeletionRequest, $this>
     */
    public function creatorContentDeletionRequests(): HasMany
    {
        return $this->hasMany(CreatorContentDeletionRequest::class, 'requester_id');
    }

    /**
     * @return HasOne<CreatorAgreementAcceptance, $this>
     */
    public function latestCreatorAgreementAcceptance(): HasOne
    {
        return $this->hasOne(CreatorAgreementAcceptance::class)->latestOfMany('accepted_at');
    }

    public function hasAcceptedCreatorAgreement(?string $version = null): bool
    {
        $version ??= (string) config('platform.creator_agreement.version');

        return $this->creatorAgreementAcceptances()
            ->where('terms_version', $version)
            ->exists();
    }

    /**
     * @return HasMany<PaymentCheckout, $this>
     */
    public function paymentCheckouts(): HasMany
    {
        return $this->hasMany(PaymentCheckout::class);
    }

    /**
     * @return HasMany<PaymentOrder, $this>
     */
    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }

    /**
     * @return HasMany<PaymentSubscription, $this>
     */
    public function paymentSubscriptions(): HasMany
    {
        return $this->hasMany(PaymentSubscription::class);
    }

    /**
     * @return HasMany<CoursePurchase, $this>
     */
    public function coursePurchases(): HasMany
    {
        return $this->hasMany(CoursePurchase::class);
    }

    /**
     * @return HasMany<PaymentEntitlement, $this>
     */
    public function paymentEntitlements(): HasMany
    {
        return $this->hasMany(PaymentEntitlement::class);
    }

    /**
     * @return HasMany<TeamAccount, $this>
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(TeamAccount::class, 'owner_id');
    }

    /**
     * @return HasMany<TeamSeat, $this>
     */
    public function teamSeats(): HasMany
    {
        return $this->hasMany(TeamSeat::class);
    }

    /**
     * @return HasMany<CourseEnrollment, $this>
     */
    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * @return HasMany<LessonNote, $this>
     */
    public function lessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class);
    }

    /**
     * @return HasMany<LessonBookmark, $this>
     */
    public function lessonBookmarks(): HasMany
    {
        return $this->hasMany(LessonBookmark::class);
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<CreatorAnalyticsSnapshot, $this>
     */
    public function creatorAnalyticsSnapshots(): HasMany
    {
        return $this->hasMany(CreatorAnalyticsSnapshot::class);
    }

    /**
     * @return HasMany<EditorialRevision, $this>
     */
    public function editorialRevisions(): HasMany
    {
        return $this->hasMany(EditorialRevision::class, 'author_id');
    }

    /**
     * @return HasMany<RevenueShareRule, $this>
     */
    public function revenueShareRules(): HasMany
    {
        return $this->hasMany(RevenueShareRule::class);
    }

    /**
     * @return HasMany<CommunityGroupMember, $this>
     */
    public function communityGroupMemberships(): HasMany
    {
        return $this->hasMany(CommunityGroupMember::class);
    }

    /**
     * @return HasMany<CommunityReaction, $this>
     */
    public function communityReactions(): HasMany
    {
        return $this->hasMany(CommunityReaction::class);
    }

    /**
     * @return HasOne<CommunityReputationScore, $this>
     */
    public function communityReputationScore(): HasOne
    {
        return $this->hasOne(CommunityReputationScore::class);
    }

    /**
     * @return HasMany<ReputationEvent, $this>
     */
    public function reputationEvents(): HasMany
    {
        return $this->hasMany(ReputationEvent::class);
    }

    /**
     * @return BelongsToMany<LearningPath, $this>
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_enrollments')
            ->withPivot(['status', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<CourseBundle, $this>
     */
    public function courseBundles(): BelongsToMany
    {
        return $this->belongsToMany(CourseBundle::class, 'course_bundle_enrollments')
            ->withPivot(['status', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isApprovedBlogger(): bool
    {
        return $this->hasRole(RoleName::Blogger->value)
            && $this->bloggerProfile?->status === BloggerStatus::Approved;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
