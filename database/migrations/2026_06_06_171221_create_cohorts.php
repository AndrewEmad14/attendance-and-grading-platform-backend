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
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->unique();
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
            $table->boolean('is_active');
            $table->timestamps();

            $table->unique(['track_id', 'is_active'], 'unique_active_cohort_per_track')
                ->where('is_active', true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};
