<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GatePassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_number' => $this->plate_number,
            'effective_plate' => $this->effectivePlate(),
            'recognition' => [
                'status' => $this->recognition_status,
                'recognized_text' => $this->recognized_text,
                'confidence' => $this->recognition_confidence,
                'raw' => $this->recognition_raw,
            ],
            'manual_plate_number' => $this->manual_plate_number,
            'manual_entered_by' => $this->manual_entered_by,
            'manual_operator' => $this->whenLoaded('manualOperator', fn () => [
                'id' => $this->manualOperator->id,
                'name' => $this->manualOperator->name,
            ]),
            'driver_name' => $this->driver_name,
            'remark' => $this->remark,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            // 门岗页面严格按检查顺序（轮次）展示照片与结论
            'inspections' => InspectionResource::collection($this->whenLoaded('inspections')),
            'latest_round' => $this->relationLoaded('latestInspection') && $this->latestInspection
                ? [
                    'round' => $this->latestInspection->round,
                    'result' => $this->latestInspection->result,
                ]
                : null,
            'inspections_count' => $this->when(isset($this->inspections_count), $this->inspections_count),
            'release' => new GateReleaseResource($this->whenLoaded('release')),
        ];
    }
}
