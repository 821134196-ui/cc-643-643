<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round' => $this->round,
            'result' => $this->result,
            'finding' => $this->finding,
            'inspected_at' => $this->inspected_at,
            'inspector' => $this->whenLoaded('inspector', fn () => [
                'id' => $this->inspector->id,
                'name' => $this->inspector->name,
            ]),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
