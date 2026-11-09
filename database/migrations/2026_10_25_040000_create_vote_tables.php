<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createGuestTable();
        $this->createSiteTables();
        $this->createVotingTables();
        $this->createDeliveryTables();
        $this->createRewardTables();
        $this->createReviewTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('vote__callback_receipts');
        Schema::dropIfExists('vote__reviews');
        Schema::dropIfExists('vote__role_grants');
        Schema::dropIfExists('vote__reward_runs');
        Schema::dropIfExists('vote__reward_claims');
        Schema::dropIfExists('vote__rewards');
        Schema::dropIfExists('vote__deliveries');
        Schema::dropIfExists('vote__votes');
        Schema::dropIfExists('vote__challenges');
        Schema::dropIfExists('vote__site_servers');
        Schema::dropIfExists('vote__sites');
        Schema::dropIfExists('vote__guests');
    }

    private function createGuestTable(): void
    {
        Schema::create('vote__guests', function (Blueprint $table) {
            $table->id();
            $table->string('identity_key', 64)->unique();
            $table->string('game', 40);
            $table->string('player_name', 60);
            $table->timestamp('last_seen_at');
        });
    }

    private function createSiteTables(): void
    {
        Schema::create('vote__sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('game', 40)->nullable()->index();
            $table->string('url', 2000);
            $table->string('provider', 40)->default('custom');
            $table->text('provider_config')->nullable();
            $table->text('callback_secret');
            $table->unsignedInteger('cooldown_minutes')->default(90);
            $table->string('reset_time', 5)->nullable();
            $table->string('reset_timezone', 64)->default('UTC');
            $table->unsignedInteger('reward_points')->default(1);
            $table->text('commands')->nullable();
            $table->string('delivery_mode', 16)->default('command');
            $table->unsignedSmallInteger('votifier_port')->default(8192);
            $table->string('votifier_website_name', 64)->default('KraftInk');
            $table->text('votifier_token')->nullable();
            $table->boolean('requires_online')->default(false);
            $table->boolean('allow_guests')->default(false);
            $table->string('ip_mode', 4)->default('any');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        Schema::create('vote__site_servers', function (Blueprint $table) {
            $table->foreignId('site_id')->constrained('vote__sites')->cascadeOnDelete();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->primary(['site_id', 'server_id']);
        });
    }

    private function createVotingTables(): void
    {
        Schema::create('vote__challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('vote__sites')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('vote__guests')->restrictOnDelete();
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->unsignedBigInteger('vote_id')->nullable();
            $table->string('token', 64)->unique();
            $table->string('identity_key', 64)->nullable()->index();
            $table->string('browser_hash', 64)->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->text('ip_address')->nullable();
            $table->text('provider_token')->nullable();
            $table->text('votifier_payload')->nullable();
            $table->string('game', 40)->nullable()->index();
            $table->string('player_uuid', 64)->nullable();
            $table->string('player_name', 60)->nullable();
            $table->text('command_identity')->nullable();
            $table->boolean('requires_online')->default(false);
            $table->text('commands')->nullable();
            $table->string('verification_state', 20)->default('pending');
            $table->timestamp('provider_claimed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('vote__votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('vote__sites')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('vote__guests')->restrictOnDelete();
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->string('provider_event', 180);
            $table->string('identity_key', 64)->nullable()->index();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('game', 40)->nullable();
            $table->unsignedInteger('points');
            $table->timestamp('created_at');
            $table->unique(['site_id', 'provider_event']);
            $table->index(['site_id', 'user_id', 'created_at']);
            $table->index(['created_at', 'user_id']);
            $table->index(['game', 'created_at', 'user_id'], 'vote__votes_game_created_user');
            $table->index(['user_id', 'game', 'created_at'], 'vote__votes_user_game_created');
        });
    }

    private function createDeliveryTables(): void
    {
        Schema::create('vote__deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('vote_id')->constrained('vote__votes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('vote__guests')->restrictOnDelete();
            $table->unsignedBigInteger('reward_claim_id')->nullable()->index();
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->unsignedBigInteger('link_server_id')->nullable()->index();
            $table->string('game', 40)->nullable()->index();
            $table->string('player_uuid', 64)->nullable();
            $table->string('player_name', 60)->nullable();
            $table->boolean('requires_online')->default(false);
            $table->text('command');
            $table->string('channel', 16)->default('command');
            $table->text('votifier_payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    private function createRewardTables(): void
    {
        Schema::create('vote__rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('vote__sites')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->json('titles');
            $table->string('type', 16);
            $table->string('game', 40)->nullable()->index();
            $table->string('period', 12)->default('month');
            $table->string('metric', 12)->default('votes');
            $table->unsignedInteger('threshold')->default(1);
            $table->unsignedInteger('rank_to')->nullable();
            $table->string('pool', 80)->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('chance_bps')->default(10000);
            $table->string('permission', 100)->nullable();
            $table->json('role_permissions')->nullable();
            $table->unsignedInteger('role_days')->nullable();
            $table->unsignedInteger('wallet_minor')->default(0);
            $table->string('wallet_currency', 3)->nullable();
            $table->text('commands')->nullable();
            $table->boolean('requires_online')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['enabled', 'type']);
        });
        Schema::create('vote__reward_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained('vote__rewards')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('vote_id')->nullable()->constrained('vote__votes')->nullOnDelete();
            $table->string('claim_key', 64)->unique();
            $table->string('period_key', 32);
            $table->json('snapshot');
            $table->string('status', 16)->default('pending');
            $table->string('error', 80)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'period_key']);
            $table->index(['status', 'created_at']);
        });
        Schema::create('vote__reward_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained('vote__rewards')->restrictOnDelete();
            $table->unsignedBigInteger('cursor_user_id')->default(0);
            $table->string('period_key', 32);
            $table->string('status', 16)->default('pending');
            $table->json('snapshot');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['reward_id', 'period_key']);
        });
        Schema::create('vote__role_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained('vote__reward_claims')->restrictOnDelete();
            $table->json('permissions');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['claim_id', 'role_id']);
            $table->index(['user_id', 'revoked_at', 'expires_at']);
        });
    }

    private function createReviewTables(): void
    {
        Schema::create('vote__reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->nullable()->constrained('vote__challenges')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('vote__guests')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vote_id')->nullable()->constrained('vote__votes')->nullOnDelete();
            $table->text('note');
            $table->string('decision', 16);
            $table->timestamp('created_at');
            $table->unique('challenge_id');
        });
        Schema::create('vote__callback_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('vote__sites')->cascadeOnDelete();
            $table->foreignId('challenge_id')->unique()->constrained('vote__challenges')->cascadeOnDelete();
            $table->string('provider_event', 180);
            $table->timestamp('created_at');
            $table->unique(['site_id', 'provider_event']);
        });
    }
};
