<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class InspectionRound extends Model
{
    public const RESULT_PASS = 'pass';
    public const RESULT_FAIL = 'fail';

    protected $fillable = [
        'exit_order_id',
        'round_no',
        'result',
        'note',
        'inspector_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ExitOrder::class, 'exit_order_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
