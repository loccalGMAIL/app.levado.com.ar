<?php

use App\Enums\TenantUserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(TenantUserRole::Viewer->value);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'active']);
        });

        DB::statement('INSERT INTO tenant_users_new (tenant_id, user_id, role, active, created_at) SELECT tenant_id, user_id, role, active, created_at FROM tenant_users');

        Schema::drop('tenant_users');
        Schema::rename('tenant_users_new', 'tenant_users');
    }

    public function down(): void
    {
        Schema::create('tenant_users_old', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(TenantUserRole::Viewer->value);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'active']);
        });

        DB::statement('INSERT INTO tenant_users_old (tenant_id, user_id, role, active, created_at) SELECT tenant_id, user_id, role, active, created_at FROM tenant_users');

        Schema::drop('tenant_users');
        Schema::rename('tenant_users_old', 'tenant_users');
    }
};
