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
        // Only alter table if it exists (for CI/CD where table may not exist)
        if (Schema::hasTable('companies_v1')) {
            Schema::table('companies_v1', function (Blueprint $table) {
                // Make sic_code and sic_description nullable
                $table->string('sic_code')->nullable()->change();
                $table->text('sic_description')->nullable()->change();
                // entity_type should also be nullable
                $table->string('entity_type')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only alter table if it exists
        if (Schema::hasTable('companies_v1')) {
            Schema::table('companies_v1', function (Blueprint $table) {
                // Revert to NOT NULL (with empty string default)
                $table->string('sic_code')->nullable(false)->default('')->change();
                $table->text('sic_description')->nullable(false)->default('')->change();
            });
        }
    }
};
