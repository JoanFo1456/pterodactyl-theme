<?php

namespace JoanFo\PterodactylUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Serves translations to the interface at the path it fetches them from.
 *
 * The bundled app loads strings through i18next, which expects a JSON endpoint keyed by
 * locale and namespace. Rather than shipping a second copy of the strings, this reads the
 * panel's own lang files — so activity descriptions and the like come out in whatever
 * language the panel is configured for, including any a language plugin added.
 */
class LocaleController extends Controller
{
    /**
     * i18next's multiload adapter joins several locales or namespaces with a "+".
     */
    public function __invoke(Request $request): JsonResponse
    {
        $locales = $this->split($request->query('locale', 'en'));
        $namespaces = $this->split($request->query('namespace', 'translation'));

        $response = [];

        foreach ($locales as $locale) {
            foreach ($namespaces as $namespace) {
                $response[$locale][$namespace] = $this->strings($locale, $namespace);
            }
        }

        return new JsonResponse($response);
    }

    /**
     * @return array<int, string>
     */
    private function split(mixed $value): array
    {
        return collect(preg_split('/[+,]/', is_string($value) ? $value : ''))
            ->map(fn ($part) => trim($part))
            // These become filesystem lookups, so only accept plain identifiers.
            ->filter(fn ($part) => $part !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $part) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function strings(string $locale, string $namespace): array
    {
        if (!Lang::has($namespace, $locale)) {
            return [];
        }

        $strings = Lang::get($namespace, [], $locale);

        if (!is_array($strings)) {
            return [];
        }

        // i18next interpolates with {{name}}; Laravel's lang files use :name.
        return $this->convertPlaceholders($strings);
    }

    /**
     * @param  array<string, mixed>  $strings
     * @return array<string, mixed>
     */
    private function convertPlaceholders(array $strings): array
    {
        return Arr::map($strings, function ($value) {
            if (is_array($value)) {
                return $this->convertPlaceholders($value);
            }

            return is_string($value)
                ? Str::of($value)->replaceMatches('/:(\w+)/', fn ($match) => '{{' . $match[1] . '}}')->toString()
                : $value;
        });
    }
}
