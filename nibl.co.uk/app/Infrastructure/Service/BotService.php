<?php

namespace App\Infrastructure\Service;

use App\Domain\Bot;
use App\Models\Bot as BotModel;

final class BotService
{
    public function findByName(string $name): ?Bot
    {
        $bot = BotModel::listable()
            ->withCount('packs')
            ->where('name', $name)
            ->first();

        return $bot === null ? null : Bot::fromModel($bot);
    }

    /**
     * @return Bot[]
     */
    public function listAll(): array
    {
        return BotModel::listable()
            ->withCount('packs')
            ->orderBy('name')
            ->get()
            ->map(static fn (BotModel $bot): Bot => Bot::fromModel($bot))
            ->all();
    }
}
