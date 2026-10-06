<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_passes', function (Blueprint $table) {
            $table->id();

            // 当次出场归属的车辆（同一辆车每天可多次出场，每次一张新单）
            $table->string('plate_number', 20)->index()->nullable();
            // 识别服务原样返回的结果；识别失败时为失败状态/原始片段，不得覆盖
            $table->string('recognition_status', 20)->default('success'); // success|failed
            $table->string('recognized_text', 31)->nullable();
            $table->decimal('recognition_confidence', 5, 2)->nullable();
            $table->json('recognition_raw')->nullable();

            // 识别失败后的人工登记
            $table->string('manual_plate_number', 20)->nullable();
            $table->foreignId('manual_entered_by')->nullable()->constrained('users');

            $table->string('driver_name')->nullable();
            $table->string('remark')->nullable();

            // pending（检查中/复洗中）| passed（最后一轮合格，可放行）| released（已放行）
            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_passes');
    }
};
