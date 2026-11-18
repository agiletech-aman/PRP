<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The manager and department are copied from the employee when the appraisal is sent,
     * so later changes to the employee's profile do not move an appraisal that is in progress.
     */
    public function up(): void
    {
        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('employee_comments')->nullable();
            $table->text('manager_comments')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('employee_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['manager_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisals');
    }
};
