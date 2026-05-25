<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'image',
        'stock',
        'unit',
        'category_id',
        'is_active',
        'audience',
    ];

    protected $casts = [
        'price' => 'float',
        'stock' => 'float',
        'is_active' => 'boolean',
    ];
}
