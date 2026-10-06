<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 出场单:同一辆车每次出场生成一张新单
        Schema::create('exit_orders', function (Blueprint $table) {
            $table->id();
            // 最终用于放行的车牌(识别或人工登记)
            $table->string('plate_number');
            // 车牌识别设备返回的原始结果(人工登记时也必须保留)
            $table->string('plate_recognized')->nullable();
            // recognized=识别成功直接采用; manual=识别失败后人工登记
            $table->string('plate_source')->default('recognized');
            // open=待放行; released=正常放行; released_manual=人工放行
            $table->string('status')->default('open');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // 检查轮次:每次初检/复洗后复检在原单上追加一轮
        Schema::create('inspection_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_no');
            $table->string('result'); // pass | fail
            $table->string('note')->nullable();
            $table->foreignId('inspector_id')->constrained('users');
            $table->timestamps();

            $table->unique(['exit_order_id', 'round_no']);
        });

        // 检查照片:先上传到本单,再绑定到某一轮;每张照片只能使用一次
        Schema::create('inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_round_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type'); // wheel | body
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        // 闸机指令记录(闸机接口本地模拟)
        Schema::create('gate_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_order_id')->constrained();
            $table->string('type'); // auto | manual
            $table->string('reason')->nullable(); // 人工放行必填
            $table->foreignId('issued_by')->constrained('users');
            $table->string('gate_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_commands');
        Schema::dropIfExists('inspection_photos');
        Schema::dropIfExists('inspection_rounds');
        Schema::dropIfExists('exit_orders');
    }
};
