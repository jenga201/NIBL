<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner' => $this->owner,
            'last_processed' => $this->last_processed,
            'batch_enable' => $this->batchenable,
            'pack_count' => $this->whenCounted('packs'),
            'packs' => PackResource::collection($this->whenLoaded('packs')),
        ];
    }
}
