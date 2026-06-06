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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('deliverable_id')->constrained('courses_deliverables')->onDelete('cascade');
            // $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->string('submission_url');
            $table->decimal('raw_score');
            $table->decimal('override_score');
            $table->text('override_note');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
