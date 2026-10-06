<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GatePass extends Model
{
    public const STATUS_PENDING = 'pending';   // 检查中/已退回复洗
    public const STATUS_PASSED = 'passed';     // 最后一轮检查合格，待放行
    public const STATUS_RELEASED = 'released'; // 闸机已放行

    protected $fillable = [
        'plate_number',
        'recognition_status',
        'recognized_text',
        'recognition_confidence',
        'recognition_raw',
        'manual_plate_number',
        'manual_entered_by',
        'driver_name',
        'remark',
        'status',
        'created_by',
    ];

    protected $casts = [
        'recognition_raw' => 'array',
        'recognition_confidence' => 'decimal:2',
    ];

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->orderBy('round');
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->latestOfMany('round');
    }

    public function release(): HasOne
    {
        return $this->hasOne(GateRelease::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manualOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_entered_by');
    }

    public function effectivePlate(): ?string
    {
        return $this->plate_number ?? $this->manual_plate_number ?? $this->recognized_text;
    }

    /**
     * 只有本次出场单的最后一轮检查结论为合格时才允许放行。
     * 历史出场单的合格结论不参与本单判定。
     */
    public function latestRoundQualified(): bool
    {
        $latest = $this->inspections()->reorder()->orderByDesc('round')->first();

        return $latest !== null && $latest->result === Inspection::RESULT_QUALIFIED;
    }
}
