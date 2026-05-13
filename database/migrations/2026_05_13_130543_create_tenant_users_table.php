<?php

use App\Enums\TenantUserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(TenantUserRole::Viewer->value);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
