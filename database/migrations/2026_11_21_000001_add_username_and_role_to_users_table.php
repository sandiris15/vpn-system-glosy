<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 64)->nullable()->unique();
            $table->string('role', 32)->default('admin');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Cannot roll back username support while users without email addresses exist.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
