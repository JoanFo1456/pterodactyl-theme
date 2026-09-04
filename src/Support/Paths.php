<?php

namespace JoanFo\PterodactylUi\Support;

use Illuminate\Support\Str;

/**
 * Resolves the two URL spaces this plugin juggles: the one the React app is mounted on
 * and the one Filament is moved to. Both are configurable, so every other class asks
 * here instead of reading config directly.
 */
class Paths
{
    public static function takesOver(): bool
    {
        return (bool) config('pterodactyl-ui.takeover', true);
    }

    /**
     * Prefix the React app is served from. Empty string means the site root.
     */
    public static function uiPrefix(): string
    {
        if (self::takesOver()) {
            return '';
        }

        return trim((string) config('pterodactyl-ui.standalone_path', 'ui'), '/');
    }

    /**
     * Absolute URL path the React router treats as its base, always with a trailing slash.
     */
    public static function uiBase(): string
    {
        $prefix = self::uiPrefix();

        return $prefix === '' ? '/' : '/' . $prefix . '/';
    }

    public static function uiRoute(string $path = ''): string
    {
        return rtrim(self::uiBase() . ltrim($path, '/'), '/') ?: '/';
    }

    /**
     * Where a Filament panel lives once this plugin has moved it. Returns the panel's own
     * path untouched when takeover is disabled.
     */
    public static function legacyPathFor(string $panelId): string
    {
        if (!self::takesOver()) {
            return $panelId === 'server' ? 'server' : '';
        }

        $legacy = trim((string) config('pterodactyl-ui.legacy_path', 'legacy'), '/');
        $legacy = $legacy === '' ? 'legacy' : $legacy;

        return $panelId === 'server' ? $legacy . '/server' : $legacy;
    }

    public static function embedParameter(): string
    {
        return (string) config('pterodactyl-ui.embed_parameter', 'ptero-embed');
    }

    /**
     * Tag a Filament URL as embedded so the render hook hides the panel chrome and the
     * response allows itself to be framed.
     */
    public static function asEmbedded(string $url): string
    {
        return $url . (Str::contains($url, '?') ? '&' : '?') . self::embedParameter() . '=1';
    }
}
