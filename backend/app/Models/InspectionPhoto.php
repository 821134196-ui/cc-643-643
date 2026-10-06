<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class InspectionPhoto extends Model
{
    public const TYPE_WHEEL = 'wheel';
    public const TYPE_BODY = 'body';

    protected $fillable = [
        'exit_order_id',
        'inspection_round_id',
        'type',
        'path',
        'original_name',
        'uploaded_by',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        // 返回相对路径,前端经 Vite 代理访问后端存储
        return '/storage/'.$this->path;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ExitOrder::class, 'exit_order_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(InspectionRound::class, 'inspection_round_id');
    }
}
