<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Support\VotingTables;

/**
 * A single voting pass for one owner. Entity-agnostic.
 *
 * `system` (the engine system) and `voting_type` (the consumer category) are
 * both required at creation and never defaulted.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $owner_type
 * @property string $system
 * @property string $voting_type
 * @property string $status
 * @property int|null $focus_votable_id
 * @property string|null $focus_votable_type
 * @property int|null $list_id
 * @property bool $subset_only
 * @property bool $is_comprehensive
 * @property bool $is_revote
 * @property int $comparisons_made
 * @property int $current_priority
 * @property int $consecutive_wins
 * @property int|null $current_focus_votable_id
 * @property string|null $current_focus_votable_type
 * @property int|null $last_winner_id
 * @property string|null $last_winner_type
 * @property bool|null $last_opponent_higher_rated
 * @property int $current_group
 * @property int $group_votes_count
 * @property int|null $group_start_placement
 * @property int|null $last_placement
 * @property int $stable_votes_count
 * @property array<string, mixed>|null $group_placements
 * @property array<string, mixed>|null $group_stability
 * @property array<int, mixed>|null $score_changes
 * @property bool $is_find_the_best
 * @property int|null $champion_votable_id
 * @property string|null $champion_votable_type
 * @property int $champion_wins
 * @property int|null $win_goal
 */
class VotingSession extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected $casts = [
        'subset_only' => 'boolean',
        'is_comprehensive' => 'boolean',
        'is_revote' => 'boolean',
        'comparisons_made' => 'integer',
        'current_priority' => 'integer',
        'consecutive_wins' => 'integer',
        'last_opponent_higher_rated' => 'boolean',
        'current_group' => 'integer',
        'group_votes_count' => 'integer',
        'group_start_placement' => 'integer',
        'last_placement' => 'integer',
        'stable_votes_count' => 'integer',
        'group_placements' => 'array',
        'group_stability' => 'array',
        'score_changes' => 'array',
        'is_find_the_best' => 'boolean',
        'champion_wins' => 'integer',
        'win_goal' => 'integer',
    ];

    public function getTable(): string
    {
        return VotingTables::sessions();
    }

    public function systemEnum(): VotingSystem
    {
        return VotingSystem::from($this->system);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function focusVotable(): MorphTo
    {
        return $this->morphTo('focus_votable');
    }

    public function comparisons(): HasMany
    {
        return $this->hasMany(VotingComparison::class, 'voting_session_id');
    }
}
