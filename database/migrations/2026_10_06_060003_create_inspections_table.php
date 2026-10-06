<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_pass_id')->constrained()->cascadeOnDelete();

            // 同一出场单上的检查轮次：1=初次检查，2..=复洗后复查
            $table->unsignedInteger('round')->default(1);

            // qualified（合格）| unqualified（不合格，退回洗轮区）
            $table->string('result', 20);
            $table->string('finding')->nullable();   // 泥污位置等检查说明
            $table->foreignId('inspected_by')->constrained('users');
            $table->timestamp('inspected_at');

            $table->timestamps();

            $table->unique(['gate_pass_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
