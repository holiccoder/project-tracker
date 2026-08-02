<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->constrained('admins');
            $table->timestamps();
        });

        // Migrate existing project paid_amounts to payments table as initial records
        $projects = DB::table('projects')->where('paid_amount', '>', 0)->get();
        foreach ($projects as $project) {
            DB::table('payments')->insert([
                'project_id' => $project->id,
                'amount' => $project->paid_amount,
                'date' => now()->toDateString(),
                'remark' => '初始已付金额导入',
                'created_by' => $project->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
