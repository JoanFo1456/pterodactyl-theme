<?php

namespace JoanFo\PterodactylUi;

use Closure;
use Illuminate\Database\Eloquent\Model;
use JoanFo\PterodactylUi\Support\ExtensionRegistry;
use JoanFo\PterodactylUi\Support\Paths;

/**
 * Public entry point for other plugins.
 *
 * Nothing here is required to appear in the new navigation — a Filament page registered on
 * the app or server panel is picked up on its own. These methods are for the cases
 * discovery can't cover: a link somewhere else, a page that needs a custom label, or a
 * React page shipped as its own bundle.
 *
 * <code>
 * PteroUi::serverNavigation([
 *     'id' => 'my-plugin',
 *     'label' => 'My Plugin',
 *     'icon' => 'tabler-rocket',
 *     'url' => fn (Server $server) => route('my-plugin.page', $server),
 * ]);
 * </code>
 */
class PteroUi
{
    /**
     * @param  array<string, mixed>|Closure(?Model): (null|array<string, mixed>)  $item
     */
    public static function serverNavigation(array|Closure $item): void
    {
        ExtensionRegistry::navigation('server', $item);
    }

    /**
     * @param  array<string, mixed>|Closure(?Model): (null|array<string, mixed>)  $item
     */
    public static function accountNavigation(array|Closure $item): void
    {
        ExtensionRegistry::navigation('app', $item);
    }

    /**
     * Load an extra JavaScript bundle into the React app. Bundles dropped into a plugin's
     * resources/ptero-ui directory are picked up without calling this.
     */
    public static function script(string $url): void
    {
        ExtensionRegistry::script($url);
    }

    public static function style(string $url): void
    {
        ExtensionRegistry::style($url);
    }

    /**
     * URL of a page inside the React app, e.g. url('server/1a2b3c/files').
     */
    public static function url(string $path = ''): string
    {
        return url(Paths::uiRoute($path));
    }

    /**
     * Wrap a Filament URL so it renders without panel chrome, ready to be framed.
     */
    public static function embedded(string $url): string
    {
        return Paths::asEmbedded($url);
    }
}
