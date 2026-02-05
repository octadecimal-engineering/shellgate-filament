<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration creating the terminal sessions table.
 *
 * Stores information about user PTY sessions for audit and limit purposes.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('terminal_sessions', function (Blueprint $table) {
            $table->id();

            // User
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Unique session identifier (UUID)
            $table->uuid('session_id')->unique();

            // Session start and end time
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();

            // Client information
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->string('user_agent', 500)->nullable();

            // Session end reason
            $table->string('end_reason', 50)->nullable(); // 'user', 'timeout', 'error', 'admin'

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('ended_at'); // For finding active sessions
            $table->index(['user_id', 'ended_at']); // For counting user's active sessions
            $table->index('started_at'); // For reports
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_sessions');
    }
};
