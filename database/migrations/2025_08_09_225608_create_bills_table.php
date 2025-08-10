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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->constrained()->cascadeOnDelete();
            $table->date('bill_date');              // e.g., 2025-08-01
            $table->string('period_label');         // e.g., "Aug 2025"
            $table->decimal('previous_units', 10, 2);
            $table->decimal('present_units', 10, 2);
            $table->decimal('units_consumed', 10, 2);
            $table->decimal('electricity_amount', 10, 2);
            $table->decimal('water_amount', 10, 2);
            $table->decimal('rent_amount', 10, 2);
            $table->decimal('other_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('upi_qr_path')->nullable(); // stored PNG
            $table->string('upi_intent')->nullable();  // upi:// URL
            $table->date('paid_at')->nullable();
            $table->string('payment_ref')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenancy_id','period_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
