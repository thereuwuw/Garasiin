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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['sepeda', 'sepeda_motor', 'mobil', 'mobil_listrik']);
            $table->string('plate_number', 15)->nullable()->unique();   // kosong untuk sepeda
            $table->string('brand');
            $table->string('model');
            $table->decimal('max_battery_capacity_kwh', 8, 2)->nullable(); // hanya mobil listrik
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
