<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional migration: adds is_super_admin to users table.
 * Required if you use the default authorize callback: auth()->user()?->is_super_admin
 *
 * Publish with: php artisan vendor:publish --tag=shell-gate-user-migration
 * Then run: php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_super_admin')) {
                return;
            }
            $table->boolean('is_super_admin')->default(false)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
