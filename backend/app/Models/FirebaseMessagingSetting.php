<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirebaseMessagingSetting extends Model
{
    protected $fillable = [
        'project_id',
        'api_key',
        'app_id',
        'messaging_sender_id',
        'service_account',
        'enabled',
    ];

    protected $hidden = [
        'service_account',
    ];

    protected function casts(): array
    {
        return [
            'service_account' => 'encrypted:array',
            'enabled' => 'boolean',
        ];
    }
}
