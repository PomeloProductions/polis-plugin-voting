<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Polis\Plugin\Voting\Support\VotingTables;

/**
 * One recorded head-to-head comparison. Generalized from HSC
 * `voting_comparisons`.
 *
 * The pair is NORMALISED by the engine's internal item-id handle so that
 * item_a_id <= item_b_id (de-duplication regardless of presentation order; see
 * ComparisonPairKey). Because items are polymorphic, the normalisation handle is
 * stored per side alongside the type.
 *
 * Domain couplings removed:
 *   - release_a_id/release_b_id/winner_id/first_shown_id ->
 *     generic *_votable_id + *_votable_type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(VotingTables::comparisons(), function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('voting_session_id');

            // Normalised pair (a is the lower handle).
            $table->unsignedBigInteger('item_a_id');
            $table->string('item_a_type');
            $table->unsignedBigInteger('item_b_id');
            $table->string('item_b_type');

            // Winner (null when skipped). Always one of the pair.
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->string('winner_type')->nullable();

            // Which side was shown first in the UI (for undo restoration).
            $table->unsignedBigInteger('first_shown_id')->nullable();
            $table->string('first_shown_type')->nullable();

            $table->boolean('skipped')->default(false);
            $table->boolean('is_auto_resolved')->default(false);
            $table->unsignedTinyInteger('group')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('voting_session_id', 'voting_comparisons_session_idx');
            $table->index(['item_a_id', 'item_b_id'], 'voting_comparisons_pair_idx');
            // Covering index for the hottest query (all compared pairs in a
            // session), mirroring the HSC performance index.
            $table->index(
                ['voting_session_id', 'deleted_at', 'item_a_id', 'item_b_id'],
                'voting_comparisons_compared_pairs_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(VotingTables::comparisons());
    }
};
