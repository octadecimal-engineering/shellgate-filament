<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate;

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

        $this->app->singleton(Services\LicenseService::class, function ($app) {
            return new Services\LicenseService(
                licenseKey: config('shell-gate.license.key'),
                apiKey: config('shell-gate.license.anystack.api_key'),
                productId: config('shell-gate.license.anystack.product_id'),
                enabled: config('shell-gate.license.verify', true),
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
            $this->commands([
                Console\InstallCommand::class,
                Console\ServeCommand::class,
                Console\CloseSessionsCommand::class,
                Console\LicenseStatusCommand::class,
            ]);

            // Hint: show install reminder after `composer require` (first run only)
            $this->showInstallHintIfNeeded();
        }
    }

    /**
     * Show a one-time install hint when the package is first required.
     *
     * Detects "first boot" by checking if config/shell-gate.php has been published.
     * Only displays during `composer require` / `composer update` (not artisan commands).
     */
    protected function showInstallHintIfNeeded(): void
    {
        // Only show if config is not published yet (fresh install)
        if (file_exists(config_path('shell-gate.php'))) {
            return;
        }

        // Only show during package discovery (not during artisan commands)
        $command = $_SERVER['argv'][0] ?? '';
        if (! str_contains($command, 'composer')) {
            return;
        }

        echo "\n";
        echo "  \033[32m✓ Shell Gate installed.\033[0m\n";
        echo "  Run \033[36mphp artisan shellgate:install\033[0m to complete setup.\n";
        echo "\n";
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

        // Optional: migration adding is_super_admin to users (for default authorize callback)
        $this->publishes([
            __DIR__ . '/../stubs/add_is_super_admin_to_users_table.php' =>
                database_path('migrations/2026_02_01_000002_add_is_super_admin_to_users_table.php'),
        ], 'shell-gate-user-migration');
    }
}
