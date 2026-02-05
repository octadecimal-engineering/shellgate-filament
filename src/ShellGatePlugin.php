<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Octadecimal\ShellGate\Filament\Pages\TerminalPage;

/**
 * Filament plugin for Shell Gate terminal.
 */
class ShellGatePlugin implements Plugin
{
    use EvaluatesClosures;

    /**
     * Authorization callback.
     */
    protected Closure|bool $authorizeUsing = true;

    /**
     * Navigation group.
     */
    protected ?string $navigationGroup = null;

    /**
     * Navigation label.
     */
    protected ?string $navigationLabel = null;

    /**
     * Navigation icon.
     */
    protected ?string $navigationIcon = null;

    /**
     * Navigation sort order.
     */
    protected ?int $navigationSort = null;

    /**
     * Gateway URL override.
     */
    protected ?string $gatewayUrl = null;

    /**
     * Hide from navigation.
     */
    protected bool $hideFromNavigation = false;

    /**
     * Create a new plugin instance.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Get the plugin from panel.
     */
    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Get the plugin identifier.
     */
    public function getId(): string
    {
        return 'shell-gate';
    }

    /**
     * Register the plugin with a panel.
     */
    public function register(Panel $panel): void
    {
        $panel->pages([
            TerminalPage::class,
        ]);
    }

    /**
     * Boot the plugin.
     */
    public function boot(Panel $panel): void
    {
        // Store plugin instance for later retrieval
        app()->instance(static::class, $this);
    }

    /**
     * Set authorization callback.
     */
    public function authorize(Closure|bool $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    /**
     * Check if user is authorized.
     */
    public function isAuthorized(): bool
    {
        return $this->evaluate($this->authorizeUsing) === true;
    }

    /**
     * Set navigation group.
     */
    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    /**
     * Get navigation group.
     */
    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? config('shell-gate.filament.navigation_group');
    }

    /**
     * Set navigation label.
     */
    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    /**
     * Get navigation label.
     */
    public function getNavigationLabel(): string
    {
        return $this->navigationLabel ?? config('shell-gate.filament.navigation_label', 'Terminal');
    }

    /**
     * Set navigation icon.
     */
    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    /**
     * Get navigation icon.
     */
    public function getNavigationIcon(): string
    {
        return $this->navigationIcon ?? config('shell-gate.filament.navigation_icon', 'heroicon-o-command-line');
    }

    /**
     * Set navigation sort order.
     */
    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /**
     * Get navigation sort order.
     */
    public function getNavigationSort(): int
    {
        return $this->navigationSort ?? config('shell-gate.filament.navigation_sort', 100);
    }

    /**
     * Set gateway URL.
     */
    public function gatewayUrl(?string $url): static
    {
        $this->gatewayUrl = $url;

        return $this;
    }

    /**
     * Get gateway URL.
     */
    public function getGatewayUrl(): string
    {
        return $this->gatewayUrl ?? config('shell-gate.gateway.url', 'ws://localhost:7681');
    }

    /**
     * Hide from navigation.
     */
    public function hideFromNavigation(bool $hide = true): static
    {
        $this->hideFromNavigation = $hide;

        return $this;
    }

    /**
     * Check if should be hidden from navigation.
     */
    public function shouldHideFromNavigation(): bool
    {
        return $this->hideFromNavigation || config('shell-gate.filament.hide_from_navigation', false);
    }
}
