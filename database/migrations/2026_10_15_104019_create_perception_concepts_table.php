<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perception_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('jurisdiction', 30)->nullable();
            $table->string('name');
            $table->decimal('default_rate', 7, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'jurisdiction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perception_concepts');
    }
};
