<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class ExitOrder extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RELEASED = 'released';
    public const STATUS_RELEASED_MANUAL = 'released_manual';

    protected $fillable = [
        'plate_number',
        'plate_recognized',
        'plate_source',
        'status',
        'created_by',
    ];

    public function rounds(): HasMany
    {
        return $this->hasMany(InspectionRound::class)->orderBy('round_no');
    }

    public function latestRound(): HasOne
    {
        return $this->hasOne(InspectionRound::class)->latestOfMany('round_no');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class);
    }

    public function gateCommands(): HasMany
    {
        return $this->hasMany(GateCommand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * 只有本次出场最后一轮检查合格,才允许发送闸机放行指令。
     */
    public function canAutoRelease(): bool
    {
        $latest = $this->latestRound()->first();

        return $this->isOpen() && $latest !== null && $latest->result === InspectionRound::RESULT_PASS;
    }
}
