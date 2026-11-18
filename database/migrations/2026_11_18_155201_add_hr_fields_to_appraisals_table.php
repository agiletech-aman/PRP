<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisals', function (Blueprint $table) {
            $table->text('hr_comments')->nullable()->after('manager_comments');
            $table->timestamp('hr_submitted_at')->nullable()->after('manager_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('appraisals', function (Blueprint $table) {
            $table->dropColumn(['hr_comments', 'hr_submitted_at']);
        });
    }
};
