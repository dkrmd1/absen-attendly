<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    protected $fillable = [
        'type',
        'subtype',
        'start_date',
        'end_date',
        'reason',
        'details',
        'attachment_path',
        'department_id',
        'work_group_id',
        'requester_role',
        'status',
        'group_status',
        'group_reviewed_by',
        'group_reviewed_at',
        'supervisor_status',
        'supervisor_reviewed_by',
        'supervisor_reviewed_at',
        'director_status',
        'director_reviewed_by',
        'director_reviewed_at',
        'ceo_status',
        'ceo_reviewed_by',
        'ceo_reviewed_at',
        'commissioner_status',
        'commissioner_reviewed_by',
        'commissioner_reviewed_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'details' => 'array',
            'group_reviewed_at' => 'datetime',
            'supervisor_reviewed_at' => 'datetime',
            'director_reviewed_at' => 'datetime',
            'ceo_reviewed_at' => 'datetime',
            'commissioner_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function ceoReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ceo_reviewed_by');
    }

    public function commissionerReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_reviewed_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LeaveRequestReview::class)->orderBy('reviewed_at');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class);
    }
}
