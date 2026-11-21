<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_vpn_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('routeros_id', 191);
            $table->string('username', 64);
            $table->string('remote_address', 45);
            $table->json('nat_rule_ids')->nullable();
            $table->timestamps();
            $table->unique(['server_id', 'routeros_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_vpn_accounts');
    }
};
