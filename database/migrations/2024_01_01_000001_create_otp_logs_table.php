<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('filament-otp-login.otp_log.table', 'admin_otp_logs');
        $fk = config('filament-otp-login.otp_log.user_foreign_key', 'admin_user_id');

        Schema::create($table, function (Blueprint $schema) use ($fk): void {
            $schema->id();
            $schema->foreignId($fk)->index();
            $schema->unsignedInteger('otp_code');
            $schema->ipAddress('ip');
            $schema->boolean('is_used')->default(false)->index();
            $schema->timestamps();
        });
    }

    public function down(): void
    {
        $table = config('filament-otp-login.otp_log.table', 'admin_otp_logs');
        Schema::dropIfExists($table);
    }
};
