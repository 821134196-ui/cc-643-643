<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'view_type' => $this->view_type,
            'url' => Storage::disk('public')->url($this->file_path),
            'original_name' => $this->original_name,
            'file_size' => $this->file_size,
            'file_hash' => $this->file_hash,
            'uploaded_at' => $this->created_at,
        ];
    }
}
