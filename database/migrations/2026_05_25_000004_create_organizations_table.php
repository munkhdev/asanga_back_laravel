<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('singleton')->unique()->default('default');
            $table->string('name')->nullable();
            $table->text('introduction')->nullable();
            $table->text('story')->nullable();
            $table->string('logo')->nullable();
            $table->string('colorcover')->nullable();
            $table->json('cover')->nullable();
            $table->string('phone1')->nullable();
            $table->string('phone2')->nullable();
            $table->decimal('shipping_price', 14, 2)->default(0);
            $table->json('social')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
