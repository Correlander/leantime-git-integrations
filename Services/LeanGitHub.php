<?php

namespace Leantime\Plugins\LeanGitHub\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** Plugin lifecycle and idempotent initial schema setup. */
class LeanGitHub
{
    public function install(): void { $this->migrate(); }

    public function migrate(): void
    {
        if (! Schema::hasTable('zp_github_schema_migrations')) {
            Schema::create('zp_github_schema_migrations', function ($table): void {
                $table->string('version', 32)->primary();
                $table->timestamp('applied_at');
            });
        }
        if (DB::table('zp_github_schema_migrations')->where('version', '0.1.0')->exists()) return;

        if (! Schema::hasTable('zp_github_user_tokens')) {
            Schema::create('zp_github_user_tokens', function ($table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('leantime_user_id')->unique();
                $table->string('github_user_id', 64)->unique();
                $table->string('github_login', 255);
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->timestamp('access_expires_at')->nullable();
                $table->timestamp('refresh_expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('zp_github_app_config')) {
            Schema::create('zp_github_app_config', function ($table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->string('client_id', 255);
                $table->text('client_secret');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('zp_github_project_connections')) {
            Schema::create('zp_github_project_connections', function ($table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->unique();
                $table->string('repository_owner', 255);
                $table->string('repository_name', 255);
                $table->string('branch_prefix', 80)->default('lt');
                $table->string('base_branch', 255)->nullable();
                $table->timestamps();
                $table->index(['repository_owner', 'repository_name']);
            });
        }

        DB::table('zp_github_schema_migrations')->updateOrInsert(
            ['version' => '0.1.0'],
            ['applied_at' => now()]
        );
    }

    public function uninstall(): void
    {
        Schema::dropIfExists('zp_github_project_connections');
        Schema::dropIfExists('zp_github_app_config');
        Schema::dropIfExists('zp_github_user_tokens');
        Schema::dropIfExists('zp_github_schema_migrations');
    }
}
