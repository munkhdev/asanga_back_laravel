<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'type',
        'delivery_method',
        'payment_method',
        'items',
        'subtotal',
        'shipping_fee',
        'total',
        'status',
        'payment_status',
        'payment_id',
        'shipping_address',
        'qpay_invoice_id',
        'qpay_sender_invoice_no',
        'qpay_qr_text',
        'qpay_qr_image_base64',
        'qpay_invoice_created_at',
        'qpay_urls',
    ];

    protected $casts = [
        'items' => 'array',
        'shipping_address' => 'array',
        'qpay_urls' => 'array',
        'subtotal' => 'float',
        'shipping_fee' => 'float',
        'total' => 'float',
        'qpay_invoice_created_at' => 'datetime',
    ];
}
