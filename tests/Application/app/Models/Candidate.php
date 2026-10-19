<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An arbitrary votable item in the dummy consumer app. Deliberately has nothing
 * to do with the voting engine's schema — it exists only to prove the engine
 * ranks any host-owned entity via its polymorphic (type, id) identity. It
 * carries a `list_id` to exercise the generic list scoping (the generalization
 * of HSC's platform) and an `image_url` to show the host owns item presentation.
 */
class Candidate extends Model
{
    protected $table = 'candidates';

    protected $guarded = ['id'];
}
