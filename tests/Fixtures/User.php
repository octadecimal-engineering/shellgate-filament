<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Test User model with factory support.
 */
class User extends Authenticatable
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'users';

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
