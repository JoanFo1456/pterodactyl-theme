<?php

namespace JoanFo\PterodactylUi\Support;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * The extension surface other plugins build against.
 *
 * Two levels are supported. A plugin that only wants a link or a framed Filament page in
 * the new navigation calls navigation() from its service provider. A plugin that wants a
 * real React page ships a JS file at resources/ptero-ui/*.js and calls
 * window.PteroUI.registerServerPage() from it — those files are discovered and published
 * automatically, so no registration call is needed for the script itself.
 */
class ExtensionRegistry
{
    /** @var array<string, array<int, array<string, mixed>|Closure>> */
    private static array $navigation = [
        'app' => [],
        'server' => [],
    ];

    /** @var array<int, string> */
    private static array $scripts = [];

    /** @var array<int, string> */
    private static array $styles = [];

    /**
     * Add a navigation entry to the React app.
     *
     * @param  'app'|'server'  $scope
     * @param  array<string, mixed>|Closure(?Model): (null|array<string, mixed>)  $item
     */
    public static function navigation(string $scope, array|Closure $item): void
    {
        self::$navigation[$scope][] = $item;
    }

    /**
     * Load an extra script into the React app. Use for bundles that register native pages.
     */
    public static function script(string $url): void
    {
        self::$scripts[] = $url;
    }

    public static function style(string $url): void
    {
        self::$styles[] = $url;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function navigationFor(string $scope, ?Model $tenant): array
    {
        $items = [];

        $configured = config('pterodactyl-ui.extra_navigation.' . $scope, []);

        foreach ([...(is_array($configured) ? $configured : []), ...(self::$navigation[$scope] ?? [])] as $index => $item) {
            try {
                if ($item instanceof Closure) {
                    $item = $item($tenant);
                }

                if (!is_array($item) || blank($item['url'] ?? null) || blank($item['label'] ?? null)) {
                    continue;
                }

                $url = (string) $item['url'];
                $type = $item['type'] ?? 'link';

                $items[] = [
                    'id' => 'ext:' . ($item['id'] ?? $scope . ':' . $index),
                    'key' => (string) ($item['id'] ?? 'ext-' . $index),
                    'label' => (string) $item['label'],
                    'group' => $item['group'] ?? null,
                    'icon' => IconRenderer::toSvg($item['icon'] ?? null),
                    'sort' => (int) ($item['sort'] ?? 100),
                    'url' => $type === 'embed' ? Paths::asEmbedded($url) : $url,
                    'raw_url' => $url,
                    'type' => $type,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $items;
    }

    /**
     * Every extension script the React app should load: the ones registered in PHP plus the
     * ones discovered on disk.
     *
     * @return array{scripts: array<int, string>, styles: array<int, string>}
     */
    public static function assets(): array
    {
        $discovered = self::discover();

        return [
            'scripts' => array_values(array_unique([...$discovered['scripts'], ...self::$scripts])),
            'styles' => array_values(array_unique([...$discovered['styles'], ...self::$styles])),
        ];
    }

    /**
     * Publish and list resources/ptero-ui assets belonging to enabled plugins.
     *
     * @return array{scripts: array<int, string>, styles: array<int, string>}
     */
    private static function discover(): array
    {
        try {
            $plugins = Plugin::query()->orderBy('load_order')->get();
        } catch (Throwable) {
            return ['scripts' => [], 'styles' => []];
        }

        $scripts = [];
        $styles = [];

        foreach ($plugins as $plugin) {
            if ($plugin->status !== PluginStatus::Enabled || $plugin->id === 'pterodactyl-ui') {
                continue;
            }

            $source = plugin_path($plugin->id, 'resources', 'ptero-ui');

            if (!File::isDirectory($source)) {
                continue;
            }

            foreach (File::files($source) as $file) {
                $extension = $file->getExtension();

                if (!in_array($extension, ['js', 'mjs', 'css'], true)) {
                    continue;
                }

                $url = AssetPublisher::publish($plugin->id, $file->getPathname(), 'ptero-ui/' . $file->getFilename());

                if ($url === null) {
                    continue;
                }

                if ($extension === 'css') {
                    $styles[] = $url;
                } else {
                    $scripts[] = $url;
                }
            }
        }

        return ['scripts' => $scripts, 'styles' => $styles];
    }
}
