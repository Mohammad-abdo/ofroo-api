<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reports_cache', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Report name');
            $table->string('params_hash', 64)->comment('Parameters hash for uniqueness');
            $table->timestamp('generated_at')->comment('Generation timestamp');
            $table->string('file_path', 500)->comment('Report file path');
            $table->string('file_format', 10)->comment('File format: pdf, xlsx, csv');
            $table->integer('file_size')->nullable()->comment('File size in bytes');
            $table->json('params')->nullable()->comment('Report parameters JSON');
            $table->timestamps();
            
            $table->unique('params_hash');
            $table->index('name');
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reports_cache');
    }
};


