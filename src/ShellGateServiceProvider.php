<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for Shell Gate.
 *
 * Registers configuration, migrations, routes, views and publishing.
 */
class ShellGateServiceProvider extends ServiceProvider
{
    /**
     * Register services and bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/shell-gate.php',
            'shell-gate'
        );

        // Register services
        $this->app->singleton(Services\JwtService::class, function ($app) {
            return new Services\JwtService(
                config('shell-gate.auth.jwt_secret') ?: config('app.key'),
                config('shell-gate.auth.token_ttl', 300),
                config('shell-gate.auth.bind_ip', true),
                config('shell-gate.auth.bind_user_agent', true)
            );
        });

        $this->app->singleton(Services\AuditService::class, function ($app) {
            return new Services\AuditService(
                config('shell-gate.audit.channel', 'shell-gate-audit'),
                config('shell-gate.audit.enabled', true)
            );
        });
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shell-gate');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * Register publishing tags.
     */
    protected function registerPublishing(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/shell-gate.php' => config_path('shell-gate.php'),
        ], 'shell-gate-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'shell-gate-migrations');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/shell-gate'),
        ], 'shell-gate-views');

        // Publish assets (JS/CSS)
        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/shell-gate'),
        ], 'shell-gate-assets');

        // Publish stubs (Nginx, systemd)
        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/shell-gate'),
        ], 'shell-gate-stubs');
    }
}
