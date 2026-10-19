<?php

declare(strict_types=1);

use Polis\Plugin\Voting\Services\GroupProgressCalculator;
use Polis\Plugin\Voting\Services\PlacementConfidenceCalculator;
use Polis\Plugin\Voting\Services\ScoreProximityWeighting;

/*
|--------------------------------------------------------------------------
| Voting plugin defaults
|--------------------------------------------------------------------------
|
| Merged under config('plugin.voting.defaults.*') by the plugin manager. A
| consumer can override any value from its own config/plugin/voting.php without
| touching the package. Every value here is a tunable of the generic engine —
| there is no domain (game / platform / year) vocabulary anywhere.
|
*/

return [
    // The engine column prefix. Tables ship as `voting_sessions`,
    // `voting_comparisons`, `item_rankings`. If a consumer app already owns a
    // `voting_sessions` table, set a prefix (e.g. "polis_") to namespace the
    // plugin's own tables and avoid a collision.
    'table_prefix' => '',

    // Smart pair-selection weighting knobs. Defaults mirror the engine the
    // plugin was extracted from; see ScoreProximityWeighting for the math.
    'selection' => [
        // Score differences at/above this fraction are treated uniformly.
        'score_diff_cap' => ScoreProximityWeighting::DIFF_CAP,
        // Consecutive wins before the smart-selection focus rotates.
        'focus_rotation_wins' => 5,
    ],

    // Placement-confidence thresholds (ranking-level completeness signal).
    'confidence' => [
        'stability_change_threshold' => PlacementConfidenceCalculator::STABILITY_CHANGE_THRESHOLD,
        'stability_target_streak' => PlacementConfidenceCalculator::STABILITY_TARGET_STREAK,
    ],

    // Group-progress / locking thresholds (per-list convergence signal).
    'progress' => [
        'required_stable_placements' => GroupProgressCalculator::REQUIRED_STABLE_PLACEMENTS,
        'absolute_minimum_votes' => GroupProgressCalculator::ABSOLUTE_MINIMUM_VOTES,
        'absolute_maximum_votes' => GroupProgressCalculator::ABSOLUTE_MAXIMUM_VOTES,
    ],
];
