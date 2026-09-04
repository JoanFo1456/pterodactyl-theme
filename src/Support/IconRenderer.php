<?php

namespace JoanFo\PterodactylUi\Support;

use BackedEnum;
use BladeUI\Icons\Factory;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns whatever a Filament component returned for its navigation icon into an inline SVG
 * string the React app can drop into the DOM.
 *
 * Plugins pick icons from any blade-icons set they like, so mapping names to React
 * components would mean guessing. Rendering the same SVG the Filament sidebar would have
 * rendered means an unknown plugin's icon looks right without this plugin knowing it.
 */
class IconRenderer
{
    public static function toSvg(mixed $icon, string $class = 'ptero-nav-icon'): ?string
    {
        if ($icon instanceof Htmlable) {
            return self::sanitize($icon->toHtml());
        }

        if ($icon instanceof BackedEnum) {
            $icon = $icon->value;
        }

        if (!is_string($icon) || $icon === '') {
            return null;
        }

        // Already an SVG rather than an icon name.
        if (Str::startsWith(ltrim($icon), '<svg')) {
            return self::sanitize($icon);
        }

        try {
            return self::sanitize(app(Factory::class)->svg($icon, $class)->toHtml());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The manifest is consumed with dangerouslySetInnerHTML, so nothing that can execute is
     * allowed through even though these strings come from the panel's own icon sets.
     */
    private static function sanitize(string $svg): ?string
    {
        $svg = preg_replace('/<script\b.*?<\/script>/is', '', $svg) ?? '';
        $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg) ?? '';

        return Str::startsWith(ltrim($svg), '<svg') ? trim($svg) : null;
    }
}
