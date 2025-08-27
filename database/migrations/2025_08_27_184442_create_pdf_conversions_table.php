<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pdf_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('original_filename');
            $table->string('original_path');
            $table->string('converted_filename');
            $table->string('converted_path');
            $table->bigInteger('original_size'); // bytes
            $table->bigInteger('converted_size'); // bytes
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('error_message')->nullable();
            $table->integer('processing_time')->nullable(); // seconds
            $table->json('metadata')->nullable(); // PDF metadata
            $table->timestamps();
            
            // Indexes
            $table->index(['ip_address', 'created_at']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_conversions');
    }
};
