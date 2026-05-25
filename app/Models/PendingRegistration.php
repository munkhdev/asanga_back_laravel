<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    use HasFactory;

    protected $table = 'pending_registrations';

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password_hash',
        'role',
        'otp',
        'otp_expires_at',
        'verify_session_id',
        'verify_callback_status',
        'verify_instruction',
        'status',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
    ];
}
