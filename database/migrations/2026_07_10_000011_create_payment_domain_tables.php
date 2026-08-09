<?php

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
        Schema::create('payment_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->index();
            $table->string('status')->default('active')->index();
            $table->string('paddle_product_id')->nullable()->unique();
            $table->string('tax_category')->default('training-services');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_product_id')->constrained('payment_products')->cascadeOnDelete();
            $table->string('name');
            $table->string('paddle_price_id')->nullable()->unique();
            $table->string('billing_interval')->default('one_time')->index();
            $table->boolean('is_recurring')->default(false)->index();
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->unsignedInteger('seat_min')->nullable();
            $table->unsignedInteger('seat_max')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('team_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('seat_limit')->default(1);
            $table->string('paddle_customer_id')->nullable()->index();
            $table->string('paddle_subscription_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('team_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_account_id')->constrained('team_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('status')->default('invited')->index();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['team_account_id', 'email']);
        });

        Schema::create('payment_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_price_id')->constrained('payment_prices')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('team_account_id')->nullable()->constrained('team_accounts')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('initiated')->index();
            $table->string('paddle_transaction_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->json('custom_data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_account_id')->nullable()->constrained('team_accounts')->nullOnDelete();
            $table->foreignId('payment_checkout_id')->nullable()->constrained('payment_checkouts')->nullOnDelete();
            $table->string('provider')->default('paddle')->index();
            $table->string('status')->default('pending')->index();
            $table->string('paddle_transaction_id')->nullable()->unique();
            $table->string('paddle_customer_id')->nullable()->index();
            $table->string('paddle_subscription_id')->nullable()->index();
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->timestamp('purchased_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_order_id')->constrained('payment_orders')->cascadeOnDelete();
            $table->foreignId('payment_product_id')->nullable()->constrained('payment_products')->nullOnDelete();
            $table->foreignId('payment_price_id')->nullable()->constrained('payment_prices')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_account_id')->nullable()->constrained('team_accounts')->nullOnDelete();
            $table->foreignId('payment_product_id')->nullable()->constrained('payment_products')->nullOnDelete();
            $table->foreignId('payment_price_id')->nullable()->constrained('payment_prices')->nullOnDelete();
            $table->string('provider')->default('paddle')->index();
            $table->string('status')->default('inactive')->index();
            $table->string('paddle_subscription_id')->unique();
            $table->string('paddle_customer_id')->nullable()->index();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('next_billed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('course_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
            $table->string('source')->default('paddle')->index();
            $table->string('status')->default('active')->index();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('payment_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_account_id')->nullable()->constrained('team_accounts')->cascadeOnDelete();
            $table->nullableMorphs('entitlementable', 'pay_entitlements_entity_idx');
            $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
            $table->foreignId('payment_subscription_id')->nullable()->constrained('payment_subscriptions')->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('active')->index();
            $table->string('source')->default('paddle')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index(['team_account_id', 'type', 'status']);
        });

        Schema::create('payment_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('auditable');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_reconciliation_records', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('paddle')->index();
            $table->string('event_id')->nullable()->index();
            $table->string('record_type')->index();
            $table->string('status')->default('pending')->index();
            $table->string('paddle_transaction_id')->nullable()->index();
            $table->string('paddle_subscription_id')->nullable()->index();
            $table->string('paddle_customer_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_records');
        Schema::dropIfExists('payment_audit_logs');
        Schema::dropIfExists('payment_entitlements');
        Schema::dropIfExists('course_purchases');
        Schema::dropIfExists('payment_subscriptions');
        Schema::dropIfExists('payment_order_items');
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('payment_checkouts');
        Schema::dropIfExists('team_seats');
        Schema::dropIfExists('team_accounts');
        Schema::dropIfExists('payment_prices');
        Schema::dropIfExists('payment_products');
    }
};
