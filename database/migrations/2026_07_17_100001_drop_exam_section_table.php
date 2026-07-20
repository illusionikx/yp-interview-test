<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('exam_section');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally not recreated — D-1 is a permanent structural
        // decision (dropping the pivot is what makes the v2.0 cross-subject
        // leak unexpressible). A down() that recreates the table would
        // silently reintroduce the leak vector on rollback. See CONTEXT.md
        // D-1 and app/Models/Exam.php::scopeVisibleTo()'s doc comment.
    }
};
