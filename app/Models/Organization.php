<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'organizations';

    protected $fillable = [
        'singleton',
        'shipping_price',
    ];

    protected $casts = [
        'cover' => 'array',
        'social' => 'array',
        'stats' => 'array',
        'shipping_price' => 'float',
    ];
}
