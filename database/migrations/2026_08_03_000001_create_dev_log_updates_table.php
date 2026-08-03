<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_log_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dev_log_id')
                ->constrained('dev_logs')
                ->cascadeOnDelete();
            $table->text('update');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_log_updates');
    }
};
