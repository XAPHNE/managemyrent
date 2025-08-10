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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('water_charge', 10, 2)->default(0);
            $table->decimal('electricity_rate', 10, 2)->default(0); // per-unit rate
            $table->decimal('monthly_rent', 10, 2)->default(0);
            $table->decimal('advance_payment', 10, 2)->default(0);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->string('upi_vpa')->nullable(); // landlord UPI ID like name@bank
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
