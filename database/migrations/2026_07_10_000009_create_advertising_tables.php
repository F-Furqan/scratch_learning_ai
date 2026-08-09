<?php

use App\Enums\AdCampaignStatus;
use App\Enums\AdCreativeStatus;
use App\Enums\AdCreativeType;
use App\Enums\AdPricingModel;
use App\Enums\AdvertiserRequestStatus;
use App\Enums\AdZoneStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->index();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('max_creatives')->default(1);
            $table->string('status')->default(AdZoneStatus::Active->value)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('advertiser_name');
            $table->string('advertiser_email')->nullable();
            $table->string('status')->default(AdCampaignStatus::Draft->value)->index();
            $table->string('pricing_model')->default(AdPricingModel::Cpm->value);
            $table->char('currency', 3)->default('USD');
            $table->decimal('budget_total', 12, 2)->nullable();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('cpm_rate', 10, 4)->nullable();
            $table->decimal('cpc_rate', 10, 4)->nullable();
            $table->decimal('flat_rate', 10, 4)->nullable();
            $table->string('target_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_creatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->default(AdCreativeType::Image->value);
            $table->string('status')->default(AdCreativeStatus::Draft->value)->index();
            $table->string('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('target_url')->nullable();
            $table->text('html_snippet')->nullable();
            $table->unsignedSmallInteger('weight')->default(100);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('advertiser_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_ad_zone_id')->nullable()->constrained('ad_zones')->nullOnDelete();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('website_url')->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default(AdvertiserRequestStatus::Pending->value)->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_pricing_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('pricing_model')->default(AdPricingModel::Cpm->value);
            $table->char('currency', 3)->default('USD');
            $table->decimal('cpm_rate', 10, 4)->nullable();
            $table->decimal('cpc_rate', 10, 4)->nullable();
            $table->decimal('flat_rate', 10, 4)->nullable();
            $table->decimal('min_spend', 12, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_impressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_creative_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->text('url')->nullable();
            $table->text('referrer')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ad_creative_id', 'occurred_at']);
            $table->index(['ad_campaign_id', 'occurred_at']);
        });

        Schema::create('ad_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_creative_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->text('url')->nullable();
            $table->text('referrer')->nullable();
            $table->text('target_url')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ad_creative_id', 'occurred_at']);
            $table->index(['ad_campaign_id', 'occurred_at']);
        });

        Schema::create('ad_campaign_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date')->index();
            $table->foreignId('ad_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_creative_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('revenue', 12, 4)->default(0);
            $table->decimal('spend', 12, 4)->default(0);
            $table->decimal('effective_cpm', 10, 4)->default(0);
            $table->decimal('effective_cpc', 10, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['report_date', 'ad_zone_id', 'ad_campaign_id', 'ad_creative_id'], 'ad_reports_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_reports');
        Schema::dropIfExists('ad_clicks');
        Schema::dropIfExists('ad_impressions');
        Schema::dropIfExists('ad_pricing_settings');
        Schema::dropIfExists('advertiser_requests');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_zones');
    }
};
