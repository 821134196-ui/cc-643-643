<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_pass_id')->unique()->constrained();

            // normal（检查合格放行）| manual（人工放行：最后一轮仍不合格，由值班员确认）
            $table->string('release_type', 20);
            $table->foreignId('released_by')->constrained('users');
            $table->text('reason')->nullable(); // 人工放行必须填写原因

            // 模拟闸机接口的回传
            $table->string('gate_command_id', 64)->nullable();
            $table->string('gate_response_code', 20)->nullable();
            $table->json('gate_response_raw')->nullable();
            $table->timestamp('released_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_releases');
    }
};
