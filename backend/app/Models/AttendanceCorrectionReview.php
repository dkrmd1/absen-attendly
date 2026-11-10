<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'correction_request_id',
        'reviewer_id',
        'stage',
        'decision',
        'note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class, 'correction_request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
