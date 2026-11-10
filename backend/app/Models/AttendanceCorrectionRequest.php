<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'attendance_id',
        'user_id',
        'department_id',
        'work_group_id',
        'original_check_in_at',
        'original_check_out_at',
        'requested_check_in_at',
        'requested_check_out_at',
        'reason',
        'status',
        'applied_by',
        'applied_at',
        'application_note',
    ];

    protected function casts(): array
    {
        return [
            'original_check_in_at' => 'datetime',
            'original_check_out_at' => 'datetime',
            'requested_check_in_at' => 'datetime',
            'requested_check_out_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionReview::class, 'correction_request_id')
            ->orderBy('reviewed_at');
    }
}
