<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_logs', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('content');
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['task_id']);
                $table->dropColumn('task_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dev_logs', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete()->after('project_id');
            $table->dropColumn('status');
        });
    }
};
