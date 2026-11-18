<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per rating factor on the appraisal form. Heading and factor names are copied in
     * when the appraisal is sent, so editing the KRA setup later does not change a sent form.
     */
    public function up(): void
    {
        Schema::create('appraisal_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_factor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_code', 10);
            $table->string('category_name');
            $table->string('factor_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('employee_rating')->nullable();
            $table->unsignedTinyInteger('manager_rating')->nullable();
            $table->timestamps();

            $table->index(['appraisal_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_ratings');
    }
};
