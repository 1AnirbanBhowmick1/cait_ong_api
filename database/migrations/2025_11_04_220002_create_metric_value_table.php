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
        if (!Schema::hasTable('metric_value')) {
            Schema::create('metric_value', function (Blueprint $table) {
                $table->id('metric_value_id');
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('metric_id');
                $table->unsignedBigInteger('source_document_id')->nullable();
                $table->decimal('extracted_metric_value', 20, 6)->nullable();
                $table->string('extracted_metric_unit')->nullable();
                $table->date('period_start_date')->nullable();
                $table->date('period_end_date')->nullable();
                $table->string('basin_name')->nullable();
                $table->string('segment_name')->nullable();
                $table->string('asset_name')->nullable();
                $table->string('gross_or_net')->nullable();
                $table->string('extraction_method')->nullable();
                $table->decimal('extraction_confidence_score', 5, 2)->nullable();
                $table->string('source_location')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('company_id')->references('company_id')->on('companies_v1');
                $table->foreign('metric_id')->references('metric_id')->on('metric_definition');
                $table->foreign('source_document_id')->references('source_document_id')->on('source_document');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('metric_value')) {
            Schema::dropIfExists('metric_value');
        }
    }
};

