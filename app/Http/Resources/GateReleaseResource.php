<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GateReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return null;
        }

        return [
            'release_type' => $this->release_type,
            'reason' => $this->reason,
            'released_at' => $this->released_at,
            'gate_command_id' => $this->gate_command_id,
            'gate_response_code' => $this->gate_response_code,
            'gate_response_raw' => $this->gate_response_raw,
            'operator' => $this->whenLoaded('operator', fn () => [
                'id' => $this->operator->id,
                'name' => $this->operator->name,
            ]),
        ];
    }
}
