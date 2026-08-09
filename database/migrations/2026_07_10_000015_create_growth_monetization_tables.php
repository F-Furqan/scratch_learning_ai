<?php

use App\Enums\AffiliateStatus;
use App\Enums\CheckoutRecoveryStatus;
use App\Enums\DiscountType;
use App\Enums\GiftPurchaseStatus;
use App\Enums\GrowthStatus;
use App\Enums\LeadSubmissionStatus;
use App\Enums\ReferralConversionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_products', function (Blueprint $table): void {
            $table->foreignId('course_bundle_id')
                ->nullable()
                ->after('course_id')
                ->constrained('course_bundles')
                ->nullOnDelete();
        });

        Schema::create('payment_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_product_id')->nullable()->constrained('payment_products')->cascadeOnDelete();
            $table->foreignId('payment_price_id')->nullable()->constrained('payment_prices')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('type', 32)->default(DiscountType::Percent->value)->index();
            $table->unsignedInteger('value');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default(GrowthStatus::Draft->value)->index();
            $table->boolean('is_launch_offer')->default(false)->index();
            $table->string('paddle_discount_id')->nullable()->unique();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_checkouts', function (Blueprint $table): void {
            $table->foreignId('payment_discount_id')->nullable()->after('team_account_id')->constrained('payment_discounts')->nullOnDelete();
            $table->unsignedBigInteger('discount_amount')->default(0)->after('quantity');
            $table->string('recovery_email')->nullable()->after('checkout_url');
        });

        Schema::create('payment_discount_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamp('redeemed_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'code']);
        });

        Schema::create('gift_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchaser_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_price_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('code')->unique();
            $table->string('status', 32)->default(GiftPurchaseStatus::Pending->value)->index();
            $table->text('message')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status', 32)->default(AffiliateStatus::Pending->value)->index();
            $table->unsignedInteger('commission_rate_basis_points')->default(1000);
            $table->unsignedInteger('cookie_days')->default(30);
            $table->string('payout_email')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_id', 64)->index();
            $table->text('landing_url')->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('ip_hash')->nullable();
            $table->string('user_agent_hash')->nullable();
            $table->timestamp('clicked_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('referral_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default(ReferralConversionStatus::Pending->value)->index();
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->timestamp('converted_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('abandoned_checkout_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_checkout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('status', 32)->default(CheckoutRecoveryStatus::Open->value)->index();
            $table->string('recovery_token')->unique();
            $table->text('recovery_url')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_magnets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 32)->default(GrowthStatus::Draft->value)->index();
            $table->string('form_headline')->nullable();
            $table->text('delivery_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_magnet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject');
            $table->string('audience')->default('all_leads');
            $table->string('status', 32)->default(GrowthStatus::Draft->value)->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_magnet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 32)->default(LeadSubmissionStatus::New->value)->index();
            $table->text('source_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['lead_magnet_id', 'email']);
        });

        Schema::create('ab_experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('surface')->default('pricing_cta')->index();
            $table->string('status', 32)->default(GrowthStatus::Draft->value)->index();
            $table->string('winning_variant_key')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ab_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ab_experiment_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('weight')->default(50);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('conversions_count')->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['ab_experiment_id', 'key']);
        });

        Schema::create('ab_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ab_experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ab_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_id', 64)->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['ab_experiment_id', 'visitor_id']);
        });

        Schema::create('social_share_images', function (Blueprint $table): void {
            $table->id();
            $table->morphs('shareable');
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->text('image_url')->nullable();
            $table->string('title');
            $table->string('alt_text')->nullable();
            $table->string('template')->default('default');
            $table->string('status', 32)->default(GrowthStatus::Active->value)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_share_images');
        Schema::dropIfExists('ab_assignments');
        Schema::dropIfExists('ab_variants');
        Schema::dropIfExists('ab_experiments');
        Schema::dropIfExists('lead_submissions');
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('lead_magnets');
        Schema::dropIfExists('abandoned_checkout_recoveries');
        Schema::dropIfExists('referral_conversions');
        Schema::dropIfExists('affiliate_visits');
        Schema::dropIfExists('affiliate_partners');
        Schema::dropIfExists('gift_purchases');
        Schema::dropIfExists('payment_discount_redemptions');
        Schema::table('payment_checkouts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_discount_id');
            $table->dropColumn(['discount_amount', 'recovery_email']);
        });

        Schema::dropIfExists('payment_discounts');

        Schema::table('payment_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_bundle_id');
        });
    }
};
