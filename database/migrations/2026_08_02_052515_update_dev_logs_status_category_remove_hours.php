<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('dev_logs')->where('status', 'pending')->update(['status' => 'in_progress']);

        Schema::table('dev_logs', function (Blueprint $table) {
            $table->string('category')->default('agent_independent')->after('status');
            $table->dropColumn('hours_spent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dev_logs', function (Blueprint $table) {
            $table->decimal('hours_spent', 5, 1)->nullable()->after('content');
            $table->dropColumn('category');
        });
    }
};
