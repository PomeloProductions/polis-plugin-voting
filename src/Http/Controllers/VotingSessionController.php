<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Polis\Plugin\Voting\Contracts\VotingEngineContract;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Http\Requests\SkipRequest;
use Polis\Plugin\Voting\Http\Requests\StartSessionRequest;
use Polis\Plugin\Voting\Http\Requests\VoteRequest;
use Polis\Plugin\Voting\Models\VotingSession;
use Polis\Plugin\Voting\Support\VotableItem;

/**
 * Generic HTTP surface for the voting engine, covering the four core flows:
 * start session, get next comparison, cast vote, get rankings (plus skip +
 * complete).
 *
 * Owner identity is resolved via {@see resolveOwner()} — a protected seam a
 * consumer app overrides to bind the session to its authenticated user (or any
 * owner entity). The default reads an authenticated user; absent one it falls
 * back to explicit owner_type/owner_id in the payload, keeping the package
 * runnable standalone and in tests.
 */
class VotingSessionController extends Controller
{
    public function __construct(protected readonly VotingEngineContract $engine) {}

    public function start(StartSessionRequest $request): JsonResponse
    {
        [$ownerType, $ownerId] = $this->resolveOwner($request);

        $system = VotingSystem::from($request->string('system')->toString());
        $votingType = $request->string('voting_type')->toString();

        $items = array_map(
            static fn (array $i): VotableItem => VotableItem::fromArray($i),
            $request->array('items'),
        );

        $options = $this->parseOptions($request->array('options'));

        $session = $this->engine->startSession($ownerType, $ownerId, $system, $votingType, $items, $options);
        $next = $this->engine->nextComparison($session);

        return response()->json([
            'session' => $session,
            'next_comparison' => $this->presentPair($next),
            'progress' => $this->engine->sessionProgress($session),
        ], 201);
    }

    public function next(VotingSession $session): JsonResponse
    {
        return response()->json([
            'session' => $session,
            'next_comparison' => $this->presentPair($this->engine->nextComparison($session)),
            'progress' => $this->engine->sessionProgress($session),
        ]);
    }

    public function vote(VoteRequest $request, VotingSession $session): JsonResponse
    {
        $winner = VotableItem::fromArray($request->array('winner'));
        $loser = VotableItem::fromArray($request->array('loser'));
        $firstShown = $request->has('first_shown')
            ? VotableItem::fromArray($request->array('first_shown'))
            : null;

        $this->engine->recordVote($session, $winner, $loser, $firstShown);
        $session->refresh();

        return response()->json([
            'session' => $session,
            'next_comparison' => $this->presentPair($this->engine->nextComparison($session)),
            'progress' => $this->engine->sessionProgress($session),
        ]);
    }

    public function skip(SkipRequest $request, VotingSession $session): JsonResponse
    {
        $itemA = VotableItem::fromArray($request->array('item_a'));
        $itemB = VotableItem::fromArray($request->array('item_b'));

        $this->engine->skip($session, $itemA, $itemB);

        return response()->json([
            'session' => $session,
            'next_comparison' => $this->presentPair($this->engine->nextComparison($session)),
            'progress' => $this->engine->sessionProgress($session),
        ]);
    }

    public function complete(VotingSession $session): JsonResponse
    {
        $this->engine->completeSession($session);
        $session->refresh();

        return response()->json(['session' => $session]);
    }

    public function rankings(Request $request): JsonResponse
    {
        [$ownerType, $ownerId] = $this->resolveOwner($request);
        $votingType = $request->string('voting_type')->toString();

        abort_if($votingType === '', 422, 'voting_type is required.');

        return response()->json([
            'rankings' => $this->engine->rankings($ownerType, $ownerId, $votingType),
        ]);
    }

    /**
     * Resolve the owner the session belongs to. Override in a consumer to bind
     * to the authenticated principal.
     *
     * @return array{0: string, 1: int}
     */
    protected function resolveOwner(Request $request): array
    {
        $user = $request->user();
        if ($user !== null) {
            return [$user::class, (int) $user->getAuthIdentifier()];
        }

        return [
            (string) $request->input('owner_type', 'owner'),
            (int) $request->input('owner_id', 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function parseOptions(array $options): array
    {
        if (isset($options['focus']) && is_array($options['focus'])) {
            $options['focus'] = VotableItem::fromArray($options['focus']);
        }

        return $options;
    }

    /**
     * @param  array{0: VotableItem, 1: VotableItem}|null  $pair
     * @return array<int, array<string, mixed>>|null
     */
    private function presentPair(?array $pair): ?array
    {
        if ($pair === null) {
            return null;
        }

        return [$pair[0]->toArray(), $pair[1]->toArray()];
    }
}
