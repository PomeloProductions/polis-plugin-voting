<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Support;

/**
 * An entity-agnostic reference to a thing being ranked.
 *
 * The engine ranks an arbitrary list of these. An item needs only a stable
 * polymorphic identity `(type, id)` and an optional `listId` (the generic
 * equivalent of HSC's platform). The consuming app owns everything else about
 * the item (title, image, metadata); the engine never looks past these fields.
 */
final class VotableItem
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly ?int $listId = null,
    ) {}

    /**
     * Build from a loose array as it arrives from a request payload.
     *
     * @param  array{type: string, id: int|string, list_id?: int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) $data['type'],
            id: (int) $data['id'],
            listId: isset($data['list_id']) ? (int) $data['list_id'] : null,
        );
    }

    /**
     * Canonical string handle used for de-duplication and ordering. Combines
     * type + id so items of different types never collide.
     */
    public function handle(): string
    {
        return $this->type.'#'.$this->id;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    /**
     * @return array{type: string, id: int, list_id: int|null}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id, 'list_id' => $this->listId];
    }
}
