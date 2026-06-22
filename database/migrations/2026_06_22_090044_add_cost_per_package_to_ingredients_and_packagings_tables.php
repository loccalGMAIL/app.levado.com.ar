<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('cost_per_package', 10, 4)->nullable()->after('cost_per_unit');
        });

        Schema::table('packagings', function (Blueprint $table) {
            $table->decimal('cost_per_package', 10, 4)->nullable()->after('cost_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('cost_per_package');
        });

        Schema::table('packagings', function (Blueprint $table) {
            $table->dropColumn('cost_per_package');
        });
    }
};
