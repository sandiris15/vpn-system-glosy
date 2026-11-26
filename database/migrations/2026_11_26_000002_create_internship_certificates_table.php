<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('issued_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_certificates');
    }
};
