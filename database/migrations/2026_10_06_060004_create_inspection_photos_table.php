<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();

            // wheel（车轮/轮胎）| body（车身）
            $table->string('view_type', 10)->index();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // 上传文件的 sha256：服务端强校验同一轮必须使用当次新上传的照片，
            // 不允许引用历史出场单/历史轮次的照片文件。DB 唯一索引兜底防并发绕过。
            $table->string('file_hash', 64)->unique();

            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_photos');
    }
};
