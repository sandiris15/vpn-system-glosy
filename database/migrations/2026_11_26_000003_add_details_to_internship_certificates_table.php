<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_certificates', function (Blueprint $table): void {
            $table->string('program', 10)->default('magang');
            $table->string('location', 120)->default('Naomi Aurora Teknologi');
            $table->string('institution', 120)->nullable();
            $table->date('birth_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('internship_certificates', function (Blueprint $table): void {
            $table->dropColumn(['program', 'location', 'institution', 'birth_date']);
        });
    }
};
