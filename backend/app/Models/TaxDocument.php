<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxDocument extends Model
{
    protected $fillable = [
        'user_id',
        'tax_year',
        'title',
        'description',
        'file_path',
        'uploaded_by',
        'visible_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'visible_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
