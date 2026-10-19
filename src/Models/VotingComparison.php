<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Polis\Plugin\Voting\Support\VotingTables;

/**
 * One recorded head-to-head comparison between two polymorphic items.
 *
 * The pair is normalised (item_a is the lower internal handle) so a pair can be
 * de-duplicated regardless of presentation order. Soft-deleted on revote so an
 * undo can restore the prior decision.
 *
 * @property int $id
 * @property int $voting_session_id
 * @property int $item_a_id
 * @property string $item_a_type
 * @property int $item_b_id
 * @property string $item_b_type
 * @property int|null $winner_id
 * @property string|null $winner_type
 * @property int|null $first_shown_id
 * @property string|null $first_shown_type
 * @property bool $skipped
 * @property bool $is_auto_resolved
 * @property int|null $group
 */
class VotingComparison extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'skipped' => 'boolean',
        'is_auto_resolved' => 'boolean',
        'group' => 'integer',
    ];

    public function getTable(): string
    {
        return VotingTables::comparisons();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VotingSession::class, 'voting_session_id');
    }

    public function itemA(): MorphTo
    {
        return $this->morphTo('item_a');
    }

    public function itemB(): MorphTo
    {
        return $this->morphTo('item_b');
    }

    public function winner(): MorphTo
    {
        return $this->morphTo('winner');
    }
}
