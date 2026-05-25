<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('email')->nullable()->index();
            $table->string('password')->nullable();
            $table->enum('role', ['user', 'artist', 'admin', 'sub-admin'])->default('user')->index();
            $table->enum('artist_approval_status', ['pending', 'approved', 'rejected'])->nullable()->index();
            $table->string('photo')->nullable();
            $table->enum('social_provider', ['google', 'facebook'])->nullable()->index();
            $table->string('social_provider_id')->nullable()->index();
            $table->boolean('is_verified')->default(false)->index();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
