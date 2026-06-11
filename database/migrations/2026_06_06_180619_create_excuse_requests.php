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
        Schema::create('excuse_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected']);
            $table->foreignId('reviewed_by')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excuse_requests');
    }
};
