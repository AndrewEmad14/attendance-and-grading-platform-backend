<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->unique();
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
            $table->boolean('is_active');
            $table->timestamps();
        });

        DB::statement('
            CREATE UNIQUE INDEX unique_active_cohort_per_track 
            ON cohorts (track_id, is_active) 
            WHERE is_active = true
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};
