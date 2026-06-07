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
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->morphs('engageable');
            $table->foreignId('staff_id')->constrained('staff_profiles')->onDelete('cascade');
            $table->enum('type', ['lecture', 'lab', 'business_session']);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('scheduled_hours');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engagements');
    }
};
