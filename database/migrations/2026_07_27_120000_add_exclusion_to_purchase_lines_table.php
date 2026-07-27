<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->timestamp('excluded_at')->nullable()->after('cost_applied_at');
            $table->string('exclusion_note', 255)->nullable()->after('excluded_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['excluded_at', 'exclusion_note']);
        });
    }
};
