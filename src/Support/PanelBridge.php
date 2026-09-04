<?php

namespace JoanFo\PterodactylUi\Support;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * Reads navigation out of the live Filament panels.
 *
 * The React app hand-writes the pages Pelican ships, but it can't know about a page a
 * third-party plugin registered. Rather than asking plugin authors to port anything, this
 * walks the panel's registered pages and resources, keeps the ones that came from outside
 * core, and hands the React app enough metadata (label, icon, url) to show them in the
 * sidebar and frame them in place.
 */
class PanelBridge
{
    /**
     * Components the React interface already implements itself.
     *
     * Anything not listed here is offered as an extra page, which is deliberate: the
     * interface covers the parts of the server panel Pterodactyl has equivalents for, and
     * Pelican ships more than that. Webhooks and Mounts have no counterpart, so excluding
     * the whole App\Filament namespace would hide them. Listing what is replaced,
     * rather than what is not, means a page added to core in future shows up by default
     * instead of disappearing.
     */
    private const ReplacedComponents = [
        // Core Filament's own pages (login, profile, and so on) plus the log viewer.
        'Filament\\',
        'Boquizo\\FilamentLogViewer\\',

        // The server list, which the interface's dashboard replaces.
        'App\\Filament\\App\\Resources\\Servers\\',

        // Server panel pages and resources with a native equivalent.
        'App\\Filament\\Server\\Pages\\Console',
        'App\\Filament\\Server\\Pages\\ServerFormPage',
        'App\\Filament\\Server\\Pages\\Settings',
        'App\\Filament\\Server\\Pages\\Startup',
        'App\\Filament\\Server\\Resources\\Activities\\',
        'App\\Filament\\Server\\Resources\\Allocations\\',
        'App\\Filament\\Server\\Resources\\Backups\\',
        'App\\Filament\\Server\\Resources\\Databases\\',
        'App\\Filament\\Server\\Resources\\Files\\',
        'App\\Filament\\Server\\Resources\\Schedules\\',
        'App\\Filament\\Server\\Resources\\Subusers\\',
    ];

    /**
     * Navigation contributed to the server panel by plugins, resolved for one server.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serverPages(Model $server): array
    {
        return $this->withContext('server', $server, fn (Panel $panel) => $this->collect($panel, $server));
    }

    /**
     * Navigation contributed to the server list panel by plugins.
     *
     * @return array<int, array<string, mixed>>
     */
    public function accountPages(): array
    {
        return $this->withContext('app', null, fn (Panel $panel) => $this->collect($panel, null));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collect(Panel $panel, ?Model $tenant): array
    {
        $items = [];

        foreach ($panel->getPages() as $page) {
            if ($this->isNative($page)) {
                continue;
            }

            $item = $this->fromPage($page, $panel, $tenant);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        foreach ($panel->getResources() as $resource) {
            if ($this->isNative($resource)) {
                continue;
            }

            $item = $this->fromResource($resource, $panel, $tenant);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        foreach ($this->fromNavigationItems($panel) as $item) {
            $items[] = $item;
        }

        foreach (ExtensionRegistry::navigationFor($panel->getId(), $tenant) as $item) {
            $items[] = $item;
        }

        usort($items, fn (array $a, array $b) => [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']]);

        return array_values($items);
    }

    /**
     * @param  class-string  $page
     * @return null|array<string, mixed>
     */
    private function fromPage(string $page, Panel $panel, ?Model $tenant): ?array
    {
        try {
            if (method_exists($page, 'shouldRegisterNavigation') && !$page::shouldRegisterNavigation()) {
                return null;
            }

            if (method_exists($page, 'canAccess') && !$page::canAccess()) {
                return null;
            }

            $url = $page::getUrl(panel: $panel->getId(), tenant: $tenant, shouldGuessMissingParameters: true);
        } catch (Throwable) {
            // A page that can't build a URL in this context (a missing route parameter, an
            // authorization check that needs state we don't have) simply isn't offered.
            return null;
        }

        return $this->item(
            id: 'page:' . $page,
            key: $this->slug($page, $panel),
            label: $this->label($page),
            url: $url,
            icon: method_exists($page, 'getNavigationIcon') ? $page::getNavigationIcon() : null,
            sort: method_exists($page, 'getNavigationSort') ? $page::getNavigationSort() : null,
            group: method_exists($page, 'getNavigationGroup') ? $page::getNavigationGroup() : null,
        );
    }

    /**
     * @param  class-string  $resource
     * @return null|array<string, mixed>
     */
    private function fromResource(string $resource, Panel $panel, ?Model $tenant): ?array
    {
        try {
            if (method_exists($resource, 'shouldRegisterNavigation') && !$resource::shouldRegisterNavigation()) {
                return null;
            }

            if (method_exists($resource, 'canAccess') && !$resource::canAccess()) {
                return null;
            }

            $url = $resource::getUrl('index', panel: $panel->getId(), tenant: $tenant, shouldGuessMissingParameters: true);
        } catch (Throwable) {
            return null;
        }

        return $this->item(
            id: 'resource:' . $resource,
            key: $this->slug($resource, $panel),
            label: $this->label($resource),
            url: $url,
            icon: method_exists($resource, 'getNavigationIcon') ? $resource::getNavigationIcon() : null,
            sort: method_exists($resource, 'getNavigationSort') ? $resource::getNavigationSort() : null,
            group: method_exists($resource, 'getNavigationGroup') ? $resource::getNavigationGroup() : null,
        );
    }

    /**
     * Plain NavigationItem objects a plugin pushed onto the panel. These are links rather
     * than pages, so they open in place instead of being framed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fromNavigationItems(Panel $panel): array
    {
        $items = [];

        foreach ($panel->getNavigationItems() as $index => $navigationItem) {
            try {
                if (!$navigationItem->isVisible()) {
                    continue;
                }

                $url = $navigationItem->getUrl();

                if (blank($url)) {
                    continue;
                }

                // Anything pointing back into a panel this plugin moved is a Filament page,
                // so keep framing it; genuine outbound links (the admin area, docs) are not.
                $isPanelUrl = Str::startsWith($url, [url(Paths::legacyPathFor('app')), url(Paths::legacyPathFor('server'))]);

                $items[] = $this->item(
                    id: 'nav:' . $index . ':' . Str::slug($navigationItem->getLabel()),
                    key: Str::slug($navigationItem->getLabel()),
                    label: $navigationItem->getLabel(),
                    url: $url,
                    icon: $navigationItem->getIcon(),
                    sort: $navigationItem->getSort(),
                    group: $navigationItem->getGroup(),
                    embed: $isPanelUrl,
                );
            } catch (Throwable) {
                continue;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $id, string $key, string $label, string $url, mixed $icon, ?int $sort, mixed $group, bool $embed = true): array
    {
        return [
            'id' => $id,
            'key' => $key,
            'label' => $label,
            'group' => $this->stringify($group),
            'icon' => IconRenderer::toSvg($icon),
            'sort' => $sort ?? 100,
            'url' => $embed ? Paths::asEmbedded($url) : $url,
            'raw_url' => $url,
            // "embed" pages are framed inside the React layout; the rest are plain links.
            'type' => $embed ? 'embed' : 'link',
        ];
    }

    /**
     * The URL segment Filament itself uses for a component, which becomes the segment the
     * React router uses. Falls back to the class name when a component has no slug.
     *
     * @param  class-string  $component
     */
    private function slug(string $component, Panel $panel): string
    {
        try {
            if (method_exists($component, 'getSlug')) {
                $slug = trim((string) $component::getSlug($panel), '/');

                if ($slug !== '') {
                    // Nested slugs ("files/edit") can't be a single navigation key.
                    return Str::before($slug, '/');
                }
            }
        } catch (Throwable) {
            //
        }

        return Str::slug(Str::kebab(class_basename($component)));
    }

    /** @param  class-string  $component */
    private function label(string $component): string
    {
        try {
            if (method_exists($component, 'getNavigationLabel')) {
                $label = $component::getNavigationLabel();

                if (filled($label)) {
                    return $label;
                }
            }
        } catch (Throwable) {
            //
        }

        return Str::headline(class_basename($component));
    }

    private function stringify(mixed $value): ?string
    {
        if ($value instanceof Htmlable) {
            return strip_tags($value->toHtml());
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return filled($value) ? (string) $value : null;
    }

    /** @param  class-string  $component */
    private function isNative(string $component): bool
    {
        return Str::startsWith($component, self::ReplacedComponents);
    }

    /**
     * Filament resolves labels, icons and authorization against the "current" panel and
     * tenant. Set both for the duration of the scan and put them back afterwards, so a
     * manifest request made from outside a panel doesn't leak state into the rest of the
     * request.
     *
     * @template T
     *
     * @param  callable(Panel): T  $callback
     * @return T
     */
    private function withContext(string $panelId, ?Model $tenant, callable $callback): mixed
    {
        $panel = Filament::getPanel($panelId, isStrict: false);

        if ($panel === null) {
            return [];
        }

        $previousPanel = Filament::getCurrentPanel();
        $previousTenant = Filament::getTenant();

        Filament::setCurrentPanel($panel);

        if ($tenant !== null) {
            Filament::setTenant($tenant, isQuiet: true);
        }

        try {
            return $callback($panel);
        } catch (Throwable) {
            return [];
        } finally {
            Filament::setTenant($previousTenant, isQuiet: true);
            Filament::setCurrentPanel($previousPanel);
        }
    }
}
