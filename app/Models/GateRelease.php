<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateRelease extends Model
{
    public const TYPE_NORMAL = 'normal'; // 最后一轮合格后正常放行
    public const TYPE_MANUAL = 'manual'; // 值班员人工放行（需原因）

    protected $fillable = [
        'gate_pass_id',
        'release_type',
        'released_by',
        'reason',
        'gate_command_id',
        'gate_response_code',
        'gate_response_raw',
        'released_at',
    ];

    protected $casts = [
        'gate_response_raw' => 'array',
        'released_at' => 'datetime',
    ];

    public function gatePass(): BelongsTo
    {
        return $this->belongsTo(GatePass::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
