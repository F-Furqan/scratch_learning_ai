<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_agreement_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version', 64);
            $table->timestamp('accepted_at');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'terms_version'], 'creator_terms_user_version_unique');
            $table->index(['user_id', 'accepted_at'], 'creator_terms_user_accepted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_agreement_acceptances');
    }
};
