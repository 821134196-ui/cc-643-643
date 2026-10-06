<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    public const RESULT_QUALIFIED = 'qualified';
    public const RESULT_UNQUALIFIED = 'unqualified';

    protected $fillable = [
        'gate_pass_id',
        'round',
        'result',
        'finding',
        'inspected_by',
        'inspected_at',
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
    ];

    public function gatePass(): BelongsTo
    {
        return $this->belongsTo(GatePass::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class);
    }
}
