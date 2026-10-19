<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Polis\Plugin\Voting\Support\VotingTables;

/**
 * A single owner's ranking of one votable item within a voting_type scope.
 *
 * Entity-agnostic: both the owner and the votable are polymorphic. The host app
 * supplies the concrete types; the engine only stores ids, types, tallies and
 * the derived aggregate_score.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $owner_type
 * @property int $votable_id
 * @property string $votable_type
 * @property int|null $list_id
 * @property string $voting_type
 * @property int $wins
 * @property int $total_comparisons
 * @property float $aggregate_score
 * @property int|null $global_rank
 * @property int|null $list_rank
 * @property float|null $progress_percent
 * @property Carbon|null $placement_locked_at
 * @property Carbon|null $first_compared_at
 * @property Carbon|null $last_compared_at
 */
class ItemRanking extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'wins' => 'integer',
        'total_comparisons' => 'integer',
        'aggregate_score' => 'decimal:9',
        'global_rank' => 'integer',
        'list_rank' => 'integer',
        'progress_percent' => 'decimal:2',
        'placement_locked_at' => 'datetime:c',
        'first_compared_at' => 'datetime:c',
        'last_compared_at' => 'datetime:c',
    ];

    public function getTable(): string
    {
        return VotingTables::itemRankings();
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function votable(): MorphTo
    {
        return $this->morphTo();
    }
}
