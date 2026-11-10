<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'work_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grace_period_minutes' => 'integer',
            'work_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
