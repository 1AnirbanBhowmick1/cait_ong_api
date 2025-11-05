<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('source_document')) {
            Schema::create('source_document', function (Blueprint $table) {
                $table->id('source_document_id');
                $table->unsignedBigInteger('company_id');
                $table->string('source_type');
                $table->string('filing_type')->nullable();
                $table->date('filing_date')->nullable();
                $table->date('period_end_date')->nullable();
                $table->text('source_url')->nullable();
                $table->text('raw_text_blob_path')->nullable();
                $table->string('file_format')->nullable();
                $table->decimal('extraction_confidence_score', 5, 2)->nullable();
                $table->timestamps();

                // Add foreign key only for PostgreSQL (skip for SQLite in tests)
                if (DB::getDriverName() !== 'sqlite' && Schema::hasTable('companies')) {
                    $table->foreign('company_id')->references('company_id')->on('companies');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('source_document')) {
            Schema::dropIfExists('source_document');
        }
    }
};

