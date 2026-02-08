<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Filament\Pages;

use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use OctadecimalHQ\ShellGate\ShellGatePlugin;

/**
 * Filament page for terminal interface.
 *
 * Compatible with Filament 3 and 5.
 */
class TerminalPage extends Page
{
    protected string $view = 'shell-gate::terminal-page';

    /**
     * Get page title.
     */
    public function getTitle(): string|Htmlable
    {
        return $this->getNavigationLabel();
    }

    /**
     * Get the page slug.
     */
    public static function getSlug(?Panel $panel = null): string
    {
        return config('shell-gate.filament.path', 'terminal');
    }

    /**
     * Get navigation label.
     */
    public static function getNavigationLabel(): string
    {
        try {
            return ShellGatePlugin::get()->getNavigationLabel();
        } catch (\Exception) {
            return config('shell-gate.filament.navigation_label', 'ShellGate');
        }
    }

    /**
     * Get navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        try {
            return ShellGatePlugin::get()->getNavigationGroup();
        } catch (\Exception) {
            return config('shell-gate.filament.navigation_group', 'System');
        }
    }

    /**
     * Get navigation icon.
     */
    public static function getNavigationIcon(): ?string
    {
        try {
            return ShellGatePlugin::get()->getNavigationIcon();
        } catch (\Exception) {
            return config('shell-gate.filament.navigation_icon', 'heroicon-o-command-line');
        }
    }

    /**
     * Get navigation sort order.
     */
    public static function getNavigationSort(): ?int
    {
        try {
            return ShellGatePlugin::get()->getNavigationSort();
        } catch (\Exception) {
            return config('shell-gate.filament.navigation_sort', 100);
        }
    }

    /**
     * Check if should be shown in navigation.
     */
    public static function shouldRegisterNavigation(): bool
    {
        try {
            return ! ShellGatePlugin::get()->shouldHideFromNavigation();
        } catch (\Exception) {
            return ! config('shell-gate.filament.hide_from_navigation', false);
        }
    }

    /**
     * Check if user can access this page.
     *
     * In local/testing: any authenticated user.
     * In production: requires explicit authorization (is_super_admin / Spatie / custom callback).
     */
    public static function canAccess(): bool
    {
        try {
            return ShellGatePlugin::get()->isAuthorized();
        } catch (\Exception) {
            // Fallback to default check
            $user = auth()->user();

            if (! $user) {
                return false;
            }

            // In local/testing: allow any authenticated user
            if (app()->environment(['local', 'testing'])) {
                return true;
            }

            // Check is_super_admin attribute
            if ($user->is_super_admin ?? false) {
                return true;
            }

            // Check Spatie role
            if (method_exists($user, 'hasRole')) {
                /** @phpstan-ignore-next-line */
                return $user->hasRole('super_admin');
            }

            return false;
        }
    }

    /**
     * Get gateway URL for JavaScript.
     */
    public function getGatewayUrl(): string
    {
        try {
            return ShellGatePlugin::get()->getGatewayUrl();
        } catch (\Exception) {
            return config('shell-gate.gateway.url', 'ws://localhost:7681');
        }
    }

    /**
     * Get token endpoint URL.
     */
    public function getTokenEndpoint(): string
    {
        return route('shell-gate.token.generate');
    }

    /**
     * Get UI configuration for terminal.
     *
     * @return array<string, mixed>
     */
    public function getTerminalConfig(): array
    {
        return [
            'fontSize' => config('shell-gate.ui.font_size', 14),
            'fontFamily' => config('shell-gate.ui.font_family', 'JetBrains Mono, Menlo, Monaco, monospace'),
            'theme' => config('shell-gate.ui.theme', 'dark'),
            'colors' => config('shell-gate.ui.colors', []),
            'cols' => config('shell-gate.terminal.cols', 120),
            'rows' => config('shell-gate.terminal.rows', 30),
        ];
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'gatewayUrl' => $this->getGatewayUrl(),
            'tokenEndpoint' => $this->getTokenEndpoint(),
            'terminalConfig' => $this->getTerminalConfig(),
            'height' => config('shell-gate.ui.height', '600px'),
        ];
    }
}
