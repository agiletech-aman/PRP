<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A category with a null department_id is common to every department;
     * otherwise it only appears on that department's appraisal form (e.g. KRA).
     */
    public function up(): void
    {
        Schema::create('rating_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_categories');
    }
};
