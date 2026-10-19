# polis-plugin-voting

An **entity-agnostic head-to-head (pairwise) ranking engine**, delivered as an
in-process [Polis](https://github.com/PomeloProductions/polis-laravel) plugin.

It implements **one** comparison mechanism — show the user two items, record the
winner, repeat — that powers **three user-selectable systems**. The engine knows
nothing about what it ranks: an item is just a polymorphic `(type, id)` with an
optional `list_id`. The consuming app owns the items (titles, images, metadata);
the engine only ever stores and reasons about ids.

> Phase 1 is the **backend engine only** (this package). No frontend, no app.

## The three systems

Every session picks exactly one `system` (**required**, never defaulted):

| `system` | What it does | Same-list pairs? | Persists rankings? |
|---|---|---|---|
| `list_ranking` | Persistent pairwise ranking of items **within one list**. Produces the `aggregate_score`. | allowed | yes |
| `cross_list` | Rank the top items **across lists** (prioritization). Only ever pairs items from **different** lists. Depends on list-ranking scores. | forbidden | yes |
| `decision_helper` | Ephemeral "which should I pick". Rankings are cleared each session; nothing persists. Includes the "find the best" king-of-the-hill tournament. | allowed | no |

**Modifiers** (per session, via `options`): `subset_only` (rank a subset of the
pool), `is_comprehensive` (exhaustive coverage mode), `is_revote` (re-rank an
already-ranked list), `is_find_the_best` + `win_goal` (tournament). Smart
pair-selection (score-proximity weighting, low-comparison boost, pair freshness)
and placement-confidence are built in.

## The generic item / list model

The engine ranks `VotableItem`s:

```php
new VotableItem(type: App\Models\Song::class, id: 42, listId: 7);
```

- **`type` + `id`** — a stable polymorphic identity. Any host-owned entity works.
- **`list_id`** — the generic equivalent of a "list"/"group"/"category" the item
  belongs to (e.g. an album, a platform, a shelf). Optional; only `cross_list`
  requires it (to know which pairs straddle lists).

This is a **polymorphic `(votable_type, votable_id)`** identity, chosen over a
plugin-owned `items` table so the host app never has to sync a mirror of its
entities into the plugin.

### `voting_type` is required everywhere

`voting_type` is a **consumer-defined category label** (e.g. a tab name like
`"favourites"` or `"to_play"`). It is **required** on every start-session and
rankings call — the engine never guesses one — and it scopes rankings so the
same item can be ranked independently under different categories. It is opaque to
the engine: a `varchar`, not an enum. (The HSC-specific values like
`played`/`finished`/`top_picks` are app-layer concerns and deliberately do **not**
live in this package.)

## Data model (plugin-owned tables)

Three tables, auto-loaded via the plugin migration mechanism. Set
`config('plugin.voting.defaults.table_prefix')` to namespace them if a host app
already owns a `voting_sessions` table.

- **`item_rankings`** — one row per `owner` + `votable` + `voting_type`:
  `wins`, `total_comparisons`, `aggregate_score` (the win rate), `global_rank`,
  `list_rank`, `placement_locked_at`, `first/last_compared_at`.
- **`voting_sessions`** — a single pass: the required `system` + `voting_type`,
  polymorphic `owner` + optional `focus_votable` + `list_id`, the modifiers, the
  smart-selection state, the group/list-pass progression JSON, and the
  find-the-best tournament state.
- **`voting_comparisons`** — one recorded comparison: a **normalised**
  polymorphic pair (`item_a` ≤ `item_b` by handle), the polymorphic `winner`,
  `skipped`, `first_shown` (for undo), soft-deleted on revote.

Owner is **polymorphic** too (`owner_type` + `owner_id`), so the engine ranks per
any owner entity — a `User` is just one option.

## The API surface

Routes mount under the prefix the plugin declares (default `voting`, middleware
`api-v1`):

| Method + URI | Flow |
|---|---|
| `POST voting/sessions` | **start** a session (`system` + `voting_type` + `items[]` required) |
| `GET  voting/sessions/{session}/next` | **next comparison** (the pair to show, or `null`) |
| `POST voting/sessions/{session}/vote` | **cast a vote** (`winner` + `loser`) |
| `POST voting/sessions/{session}/skip` | skip the presented pair |
| `POST voting/sessions/{session}/complete` | complete + lock placements (persistent systems) |
| `GET  voting/rankings` | **rankings** for an `owner` + `voting_type`, best first |

The same four core flows are available directly on the
[`VotingEngineContract`](src/Contracts/VotingEngineContract.php)
(`startSession` / `nextComparison` / `recordVote` / `rankings`, plus `skip` /
`completeSession` / `sessionProgress`).

## Wiring it into a consumer app

1. Require the package:

   ```bash
   composer require polis/plugin-voting
   ```

2. Enable the plugin (the only wiring needed — the polis-laravel
   `PluginManager` applies its routes, migrations, binding and config):

   ```php
   // config/plugins.php
   return [
       'enabled' => [
           \Polis\Plugin\Voting\VotingPlugin::class,
       ],
   ];
   ```

3. Run migrations (`php artisan migrate`) — the plugin's tables load
   automatically through the plugin migration path.

4. (Optional) Override the engine with your own implementation by binding
   `App\Services\Voting\HeadToHeadVotingService` — the plugin's binding is
   consumer-override-aware, so your class wins.

5. (Optional) Make the HTTP controller bind sessions to your authenticated
   principal by overriding `VotingSessionController::resolveOwner()`.

## The pure calculators

The ranking math lives in standalone, dependency-free calculators under
[`src/Services`](src/Services) — unit-tested in isolation:

- `AggregateScoreCalculator` — win rate + score-change magnitude.
- `ComparisonPairKey` — canonical order-independent pair key.
- `RankAssigner` — standard competition ranking with ties.
- `PlacementConfidenceCalculator` — coverage + stability of a ranking.
- `ScoreProximityWeighting` — the smart pair-selection weights.
- `GroupProgressCalculator` — per-list convergence / progress + the lock decision.

## Tests

Per the fleet test standards:

- **Unit** (`phpunit.xml`, `--testsuite=Unit`) — the pure calculators, the
  `VotingSystem` enum, and the plugin contract. No DB.
- **Integration** (`phpunit-app.xml`, `--testsuite=Integration`) — the engine
  against a real DB across all three systems.
- **Feature** (`phpunit-app.xml`, `--testsuite=Feature`) — one file per endpoint,
  every validation error asserted.

```bash
composer install
composer test:unit      # Unit suite
composer test:app       # Integration + Feature against a dummy consumer app
composer stan           # PHPStan level 5
composer pint           # formatting
```

## License

MIT.
