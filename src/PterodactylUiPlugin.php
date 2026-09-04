<?php

namespace JoanFo\PterodactylUi;

use Filament\Contracts\Plugin;
use Filament\Panel;
use JoanFo\PterodactylUi\Http\Middleware\AllowPanelEmbedding;
use JoanFo\PterodactylUi\Support\Paths;

/**
 * Registered against the "app" and "server" panels.
 *
 * The plugin does two things to those panels: it moves them out of the way so the React
 * app can own the URLs users actually visit, and it makes their pages embeddable so a
 * page contributed by another plugin can still be rendered inside the new layout.
 */
class PterodactylUiPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pterodactyl-ui';
    }

    public function register(Panel $panel): void
    {
        // Filament is relocated rather than removed. Every page other plugins register —
        // including ones this plugin has never heard of — keeps working at the new prefix,
        // which is what the React app frames when it has no native renderer for a page.
        if (Paths::takesOver()) {
            $panel->path(Paths::legacyPathFor($panel->getId()));
        }

        // Core sends X-Frame-Options: DENY on every response. This only relaxes it to
        // SAMEORIGIN, and only for requests that carry the embed parameter.
        $panel->middleware([AllowPanelEmbedding::class], isPersistent: true);

        // Strips the panel chrome from embedded pages so they blend into the React layout.
        $panel->renderHook('panels::head.end', fn () => view('pterodactyl-ui::embed'));
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
