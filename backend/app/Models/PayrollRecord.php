<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'gross_amount',
        'deductions_amount',
        'net_amount',
        'components',
        'payslip_path',
        'published_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'gross_amount' => 'decimal:2',
            'deductions_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'components' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
