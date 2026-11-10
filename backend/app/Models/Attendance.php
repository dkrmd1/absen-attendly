<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    protected $hidden = [
        'check_in_selfie_path',
        'check_out_selfie_path',
    ];

    protected $fillable = [
        'work_date',
        'check_in_at',
        'check_out_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'work_location_id',
        'department_id',
        'work_shift_id',
        'shift_name',
        'shift_start_time',
        'shift_end_time',
        'shift_grace_period_minutes',
        'shift_work_days',
        'is_scheduled_workday',
        'late_minutes',
        'check_in_selfie_path',
        'check_out_selfie_path',
        'selfie_review_status',
        'selfie_reviewed_by',
        'selfie_reviewed_at',
        'selfie_review_note',
        'work_assignment_id',
        'attendance_mode',
        'verification_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'reviewed_at' => 'datetime',
            'selfie_reviewed_at' => 'datetime',
            'shift_grace_period_minutes' => 'integer',
            'shift_work_days' => 'array',
            'is_scheduled_workday' => 'boolean',
            'late_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function workAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAssignment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function selfieReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selfie_reviewed_by');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }
}
