<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variable_expenses', function (Blueprint $table) {
            $table->string('receipt_image_path')->nullable()->after('expense_date');
        });
    }

    public function down(): void
    {
        Schema::table('variable_expenses', function (Blueprint $table) {
            $table->dropColumn('receipt_image_path');
        });
    }
};
