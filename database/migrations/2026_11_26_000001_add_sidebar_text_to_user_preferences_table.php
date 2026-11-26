<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->string('sidebar_title', 60)->nullable();
            $table->string('sidebar_description', 240)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->dropColumn(['sidebar_title', 'sidebar_description']);
        });
    }
};
