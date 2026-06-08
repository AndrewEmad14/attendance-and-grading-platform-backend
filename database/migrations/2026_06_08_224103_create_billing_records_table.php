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
        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('staff_profiles')->onDelete('cascade');
            $table->integer('delivered_hours');
            $table->integer('total_amount');
            $table->dateTime('forwarded_at')->nullable(); // null till we send it to accounting
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_records');
    }
};
