<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionPhoto extends Model
{
    public const VIEW_WHEEL = 'wheel';
    public const VIEW_BODY = 'body';

    protected $fillable = [
        'inspection_id',
        'view_type',
        'file_path',
        'original_name',
        'file_size',
        'file_hash',
        'uploaded_by',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
