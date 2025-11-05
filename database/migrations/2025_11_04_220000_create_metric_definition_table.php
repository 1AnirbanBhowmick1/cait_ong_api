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
        if (!Schema::hasTable('metric_definition')) {
            Schema::create('metric_definition', function (Blueprint $table) {
                $table->id('metric_id');
                $table->string('metric_category');
                $table->string('metric_name_display');
                $table->string('metric_name_internal');
                $table->string('metric_unit');
                $table->string('metric_group');
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('metric_definition')) {
            Schema::dropIfExists('metric_definition');
        }
    }
};

