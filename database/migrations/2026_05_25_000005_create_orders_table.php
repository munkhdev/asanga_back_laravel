<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['product', 'booking'])->default('product')->index();
            $table->enum('delivery_method', ['delivery', 'pickup'])->default('delivery');

            $table->json('items')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('shipping_fee', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->enum('status', ['pending', 'paid', 'processing', 'completed', 'cancelled'])->default('pending')->index();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->index();
            $table->enum('payment_method', ['qpay', 'bank_app'])->default('qpay');
            $table->string('payment_id')->nullable();

            $table->string('qpay_invoice_id')->nullable()->index();
            $table->string('qpay_sender_invoice_no')->nullable()->index();
            $table->text('qpay_qr_text')->nullable();
            $table->longText('qpay_qr_image_base64')->nullable();
            $table->timestamp('qpay_invoice_created_at')->nullable();
            $table->json('qpay_urls')->nullable();

            $table->enum('shipping_status', ['order_placed', 'confirmed', 'preparing', 'shipped', 'out_for_delivery', 'delivered'])->nullable()->default('order_placed')->index();
            $table->json('shipping_address')->nullable();
            $table->json('tracking')->nullable();

            $table->timestamp('estimated_delivery')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
