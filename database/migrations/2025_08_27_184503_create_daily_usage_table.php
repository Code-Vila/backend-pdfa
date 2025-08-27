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
        Schema::create('daily_usage', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->date('usage_date');
            $table->integer('conversions_count')->default(0);
            $table->integer('daily_limit')->default(10);
            $table->boolean('is_expanded')->default(false);
            $table->foreignId('expansion_request_id')->nullable()->constrained('user_expansion_requests')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->unique(['ip_address', 'usage_date']);
            $table->index('usage_date');
            $table->index(['ip_address', 'usage_date', 'conversions_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_usage');
    }
};
