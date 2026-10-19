<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Polis\Plugin\Voting\Support\VotingTables;

/**
 * Per-owner ranking of a votable item within a voting_type scope.
 *
 * Generalized from HSC `user_release_rankings`:
 *   - user_id    -> owner_id + owner_type (polymorphic; a User is one option)
 *   - release_id -> votable_id + votable_type (polymorphic item identity)
 *   - platform/year ranks -> generic list_rank (rank within the item's list)
 *
 * The engine stores only ids + computed figures; the host app owns the actual
 * items. `aggregate_score` is the win rate the CROSS_LIST system reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(VotingTables::itemRankings(), function (Blueprint $table): void {
            $table->id();

            // Polymorphic owner (the entity whose personal ranking this is).
            $table->unsignedBigInteger('owner_id');
            $table->string('owner_type');

            // Polymorphic votable item being ranked.
            $table->unsignedBigInteger('votable_id');
            $table->string('votable_type');

            // Optional list the item belongs to (generalizes platform).
            $table->unsignedBigInteger('list_id')->nullable();

            // Consumer-defined category label (required on write; opaque to the
            // engine). Generalizes HSC voting_type. varchar so apps can define
            // their own categories/tabs.
            $table->string('voting_type', 50);

            // Head-to-head tallies + derived score.
            $table->integer('wins')->default(0);
            $table->integer('total_comparisons')->default(0);
            $table->decimal('aggregate_score', 10, 9)->default(0);

            // Ranks (nullable until computed).
            $table->integer('global_rank')->nullable();
            $table->integer('list_rank')->nullable();

            // Placement lifecycle + lightweight completeness field.
            $table->decimal('progress_percent', 5, 2)->nullable();
            $table->timestamp('placement_locked_at')->nullable();

            // Activity timestamps (generic equivalents of first/last_played_at).
            $table->dateTime('first_compared_at')->nullable();
            $table->dateTime('last_compared_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One ranking row per owner + item + category.
            $table->unique(
                ['owner_type', 'owner_id', 'votable_type', 'votable_id', 'voting_type'],
                'item_rankings_owner_item_type_unique'
            );
            $table->index(['owner_type', 'owner_id', 'voting_type'], 'item_rankings_owner_type_idx');
            $table->index(['list_id', 'voting_type'], 'item_rankings_list_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(VotingTables::itemRankings());
    }
};
