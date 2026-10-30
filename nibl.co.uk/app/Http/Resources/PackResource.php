<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bot_id' => $this->bot_id,
            'number' => $this->number,
            'name' => $this->name,
            'size' => $this->size,
            'sizekbits' => $this->sizekbits,
            'episode_number' => $this->episode_number,
            'last_modified' => $this->last_modified,
            'bot_name' => $this->whenLoaded('bot', fn () => $this->bot->name),
            'bot_batch_enabled' => $this->whenLoaded('bot', fn () => (bool) $this->bot->batchenable),
        ];
    }
}
