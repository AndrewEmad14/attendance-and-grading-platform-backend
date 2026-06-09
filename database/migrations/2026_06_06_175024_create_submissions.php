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
            $table->foreignId('deliverable_id')->constrained('courses_deliverables')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('student_profiles')->onDelete('cascade');
            $table->enum('submission_type', ['file', 'link']);
            $table->string('submission_path');
            $table->float('raw_score');
            $table->foreignId('graded_by')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->float('override_score')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->text('override_note')->nullable();
            $table->timestamp('overridden_at')->nullable();
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
