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
        // Create table if it doesn't exist (for CI/CD and tests)
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id('company_id');
                $table->string('company_name');
                $table->string('ticker_symbol', 10);
                $table->string('sec_cik_number', 20)->nullable();
                $table->string('sic_code')->nullable();
                $table->text('sic_description')->nullable();
                $table->string('entity_type')->nullable();
                $table->boolean('extraction_flag')->default(false);
                $table->string('admin_approval_flag', 100)->nullable();
                // Additional fields from factory
                $table->string('company_type')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        } else {
            // Table exists, just alter the columns to make them nullable
            Schema::table('companies', function (Blueprint $table) {
                // Only alter if columns exist (for backward compatibility)
                if (Schema::hasColumn('companies', 'sic_code')) {
                    $table->string('sic_code')->nullable()->change();
                }
                if (Schema::hasColumn('companies', 'sic_description')) {
                    $table->text('sic_description')->nullable()->change();
                }
                if (Schema::hasColumn('companies', 'entity_type')) {
                    $table->string('entity_type')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only alter table if it exists
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                // Revert to NOT NULL (with empty string default)
                $table->string('sic_code')->nullable(false)->default('')->change();
                $table->text('sic_description')->nullable(false)->default('')->change();
            });
        }
    }
};
