<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Optional remarks HR can add to an appraisal at any stage.
     */
    public function up(): void
    {
        Schema::table('appraisals', function (Blueprint $table) {
            $table->text('hr_remarks')->nullable()->after('manager_comments');
            $table->foreignId('hr_remarks_by')->nullable()->after('hr_remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('hr_remarked_at')->nullable()->after('manager_submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hr_remarks_by');
            $table->dropColumn(['hr_remarks', 'hr_remarked_at']);
        });
    }
};
