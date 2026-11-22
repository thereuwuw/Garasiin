<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chargings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rental_id')->unique()->constrained('rentals')->cascadeOnDelete();
            $table->decimal('current_battery_percent', 5, 2);
            $table->decimal('charge_needed_kwh', 8, 2);
            $table->decimal('charging_cost', 12, 2);
            $table->string('payment_proof');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chargings');
    }
};
