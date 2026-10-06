<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class GateCommand extends Model
{
    public const TYPE_AUTO = 'auto';
    public const TYPE_MANUAL = 'manual';

    protected $fillable = [
        'exit_order_id',
        'type',
        'reason',
        'issued_by',
        'gate_response',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ExitOrder::class, 'exit_order_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
