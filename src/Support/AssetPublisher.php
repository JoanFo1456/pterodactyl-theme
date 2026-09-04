<?php

namespace JoanFo\PterodactylUi\Support;

use Illuminate\Support\Facades\File;
use SplFileInfo;
use Throwable;

/**
 * Copies plugin-owned static files into public/plugins so the browser can fetch them.
 *
 * The panel's Vite build is a core concern and this plugin ships a pre-built bundle, so
 * publishing is all that's needed — and it keeps working on installations where Node isn't
 * available to run `yarn build` at all.
 */
class AssetPublisher
{
    /**
     * Mirror a directory into public/plugins/{plugin}/{relative}. Only files that changed
     * are copied, so this is cheap enough to call on every request that serves the shell.
     *
     * Deliberately additive: it never removes files that are no longer in the source. The
     * interface loads parts of itself as separate hashed files on demand, so a browser that
     * already has the previous version open would fail on its next navigation if the old
     * ones disappeared underneath it. Leaving them lets open sessions finish; clearing
     * public/plugins/{plugin} by hand is the way to reclaim the space.
     */
    public static function publishDirectory(string $pluginId, string $source, string $relative): void
    {
        if (!File::isDirectory($source)) {
            return;
        }

        try {
            /** @var SplFileInfo $file */
            foreach (File::allFiles($source) as $file) {
                self::copy(
                    $file->getPathname(),
                    public_path(join_paths('plugins', $pluginId, trim($relative, '/'), str_replace('\\', '/', $file->getRelativePathname())))
                );
            }
        } catch (Throwable) {
            // A read-only public directory shouldn't take the panel down. Whatever was
            // published previously stays usable; the caller reports a missing bundle.
        }
    }

    public static function publish(string $pluginId, string $source, string $relative): ?string
    {
        $destination = public_path(join_paths('plugins', $pluginId, ltrim($relative, '/')));

        try {
            if (!File::exists($source)) {
                return null;
            }

            self::copy($source, $destination);
        } catch (Throwable) {
            return File::exists($destination) ? self::url($pluginId, $relative, $destination) : null;
        }

        return self::url($pluginId, $relative, $destination);
    }

    private static function copy(string $source, string $destination): void
    {
        $isStale = !File::exists($destination)
            || File::lastModified($source) > File::lastModified($destination)
            || File::size($source) !== File::size($destination);

        if (!$isStale) {
            return;
        }

        File::ensureDirectoryExists(dirname($destination), 0o755, true);
        File::copy($source, $destination);
    }

    private static function url(string $pluginId, string $relative, string $destination): string
    {
        // The published copy's mtime is a good enough cache key: it only moves when the
        // source did, so browsers keep the file until the plugin is actually updated.
        $version = @filemtime($destination) ?: 0;

        return asset(join_paths('plugins', $pluginId, ltrim($relative, '/'))) . '?v=' . $version;
    }
}
