<?php

namespace App\Domain;

final class Bot
{
    private int $id;

    private string $name;

    private ?string $owner;

    private \DateTime $lastProcessed;

    private bool $isBatchEnabled;

    private int $packSize;

    private function __construct(int $id, string $name, ?string $owner, \DateTime $lastProcessed, bool $isBatchEnabled, int $packSize)
    {
        $this->id = $id;
        $this->name = $name;
        $this->owner = $owner;
        $this->lastProcessed = $lastProcessed;
        $this->isBatchEnabled = $isBatchEnabled;
        $this->packSize = $packSize;
    }

    public static function fromModel(\App\Models\Bot $bot): self
    {
        return new self(
            $bot->id,
            $bot->name,
            $bot->owner,
            $bot->last_processed,
            (bool) $bot->batchenable,
            (int) ($bot->getAttribute('packs_count') ?? 0)
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function getLastProcessed(): \DateTime
    {
        return $this->lastProcessed;
    }

    public function isBatchEnabled(): bool
    {
        return $this->isBatchEnabled;
    }

    public function getPackSize(): int
    {
        return $this->packSize;
    }

    public function isArchive()
    {
        $now = new \DateTime;
        $diff = $this->getLastProcessed()->diff($now);

        return $diff->y >= 5;
    }

    public function isOld()
    {
        $now = new \DateTime;
        $diff = $this->getLastProcessed()->diff($now);

        return $diff->y > 1 && $diff->y <= 4;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
