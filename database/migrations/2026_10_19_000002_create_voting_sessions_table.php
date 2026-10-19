<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Polis\Plugin\Voting\Support\VotingTables;

/**
 * A single voting pass. Generalized from HSC `voting_sessions`.
 *
 * REQUIRED selection: `system` (one of the three engine systems) + `voting_type`
 * (consumer category). Neither is ever defaulted silently.
 *
 * Domain couplings removed:
 *   - new_release_id -> focus_votable_id/type (the item being placed; nullable
 *     for list-wide / cross-list / decision sessions)
 *   - platform_id    -> list_id
 *   - champion/focus/last_winner release ids -> generic *_votable_id/type
 *   - the fixed 4-group year/platform ladder -> generic current_group integer +
 *     JSON group state (apps define what a "group"/list means)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(VotingTables::sessions(), function (Blueprint $table): void {
            $table->id();

            // Polymorphic owner.
            $table->unsignedBigInteger('owner_id');
            $table->string('owner_type');

            // REQUIRED engine system + consumer category.
            $table->string('system', 32);
            $table->string('voting_type', 50);

            // Lifecycle.
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');

            // Optional focus item (single-item placement sessions).
            $table->unsignedBigInteger('focus_votable_id')->nullable();
            $table->string('focus_votable_type')->nullable();

            // Optional list scope (generalizes platform).
            $table->unsignedBigInteger('list_id')->nullable();

            // Modifiers.
            $table->boolean('subset_only')->default(false);   // HSC finished_only
            $table->boolean('is_comprehensive')->default(false);
            $table->boolean('is_revote')->default(false);

            // Tallies.
            $table->integer('comparisons_made')->default(0);
            $table->integer('current_priority')->default(1);

            // Smart-selection state.
            $table->unsignedBigInteger('current_focus_votable_id')->nullable();
            $table->string('current_focus_votable_type')->nullable();
            $table->integer('consecutive_wins')->default(0);
            $table->unsignedBigInteger('last_winner_id')->nullable();
            $table->string('last_winner_type')->nullable();
            $table->boolean('last_opponent_higher_rated')->nullable();

            // Group / list-pass progression state.
            $table->unsignedTinyInteger('current_group')->default(1);
            $table->unsignedInteger('group_votes_count')->default(0);
            $table->unsignedInteger('group_start_placement')->nullable();
            $table->unsignedInteger('last_placement')->nullable();
            $table->unsignedInteger('stable_votes_count')->default(0);
            $table->json('group_placements')->nullable();
            $table->json('group_stability')->nullable();
            $table->json('score_changes')->nullable();

            // DECISION_HELPER "find the best" tournament state.
            $table->boolean('is_find_the_best')->default(false);
            $table->unsignedBigInteger('champion_votable_id')->nullable();
            $table->string('champion_votable_type')->nullable();
            $table->integer('champion_wins')->default(0);
            $table->integer('win_goal')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'voting_type'], 'voting_sessions_owner_type_idx');
            $table->index(['owner_type', 'owner_id', 'status'], 'voting_sessions_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(VotingTables::sessions());
    }
};
