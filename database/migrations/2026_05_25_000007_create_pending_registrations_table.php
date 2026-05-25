<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('password_hash');
            $table->enum('role', ['user', 'artist', 'admin', 'sub-admin'])->default('user')->index();
            $table->string('otp', 8)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('verify_session_id')->nullable()->unique();
            $table->enum('verify_callback_status', ['pending', 'completed', 'failed'])->default('pending')->index();
            $table->string('verify_instruction')->nullable();
            $table->enum('status', ['pending', 'verified', 'completed', 'expired'])->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
