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
        Schema::create('garages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 4)->unique();
            $table->enum('type', ['sepeda', 'mobil', 'mobil_listrik']);
            $table->integer('sequence');
            $table->decimal('daily_price', 12, 2);
            $table->decimal('electricity_price_per_kwh', 12, 2)->nullable(); // hanya garasi mobil listrik
            $table->enum('status', ['available', 'used']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
