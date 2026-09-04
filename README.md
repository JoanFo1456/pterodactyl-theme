# Pterodactyl UI

A Pelican plugin that replaces the Filament client area with Pterodactyl Panel's frontend.

The interface is Pterodactyl's actual React app, vendored under `frontend/vendor/` and
built from source. The plugin provides the Laravel side that makes it run on Pelican. No
core panel file is modified.

## Requirements

- Pelican Panel
- PHP 8.3+
- Node 18+ and Yarn, only if you want to rebuild the frontend

## Installation

Copy this directory into your panel's `plugins/` folder as `pterodactyl-ui`, then enable
it from the admin area under Plugins, or:

```bash
php artisan p:plugin:install pterodactyl-ui
```

Assets are published to `public/plugins/pterodactyl-ui` automatically on the first
request. A prebuilt bundle ships in `resources/dist`, so Node is not required to install.

## What changes

| Path | Before | After |
| --- | --- | --- |
| `/` | Filament server list | Pterodactyl dashboard |
| `/server/{id}` | Filament server panel | Pterodactyl server view |
| `/account` | — | Pterodactyl account area |
| `/legacy`, `/legacy/server` | — | The Filament panels, moved here |
| `/admin` | Admin panel | Unchanged |

Login, password reset, two-factor and the admin area stay on Filament. Two-factor
enforcement applies to the new routes as well.

The interface talks to Pelican's existing client API using the session cookie, so no API
key is created and all subuser permission checks still apply.

## Configuration

`config/pterodactyl-ui.php`, or the matching environment variables:

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `legacy_path` | `PTERO_UI_LEGACY_PATH` | `legacy` | Where the Filament panels move to |
| `takeover` | `PTERO_UI_TAKEOVER` | `true` | Set false to leave Filament in place and serve from `standalone_path` |
| `standalone_path` | `PTERO_UI_STANDALONE_PATH` | `ui` | Mount point when `takeover` is off |
| `embed.max_width` | `PTERO_UI_EMBED_MAX_WIDTH` | `1500px` | Width of an embedded Filament page |
| `embed.inset` | `PTERO_UI_EMBED_INSET` | `1.5rem` | Padding either side of one |

## Building the frontend

Only needed if you change anything under `frontend/`. The built output in
`resources/dist` is what the plugin serves.

```bash
cd frontend
yarn install
yarn build
```

`yarn watch` rebuilds on change. The build uses webpack 4, which needs
`--openssl-legacy-provider` on modern Node; the scripts already set it.

Then republish the assets by loading any page of the interface, or clear
`public/plugins/pterodactyl-ui` to force a full copy.

### Layout

| Path | Contents |
| --- | --- |
| `frontend/vendor/` | Pterodactyl's `resources/scripts`, unmodified. MIT, see `frontend/vendor/LICENSE.md` |
| `frontend/integration/` | The code written for this plugin |
| `frontend/package.json`, `yarn.lock` | Upstream's dependency set, so the vendored sources build against the versions they expect |
| `resources/dist/` | Build output. Committed, so installs do not need Node |

### Changing vendored code

Do not edit `frontend/vendor/` directly. Adaptations are find/replace pairs in
`frontend/integration/upstream-patches.js`, applied at build time:

```js
{
    file: 'components/NavigationBar.tsx',
    why: 'Themes style the panel header through .fi-topbar.',
    find: "<div className={'w-full bg-neutral-900 shadow-md overflow-x-auto'}>",
    replace: "<div className={'fi-topbar w-full bg-neutral-900 shadow-md overflow-x-auto'}>",
}
```

Each patch must match exactly once, or at least once with `all: true`. The build fails if
one stops matching, so updating the vendored tree cannot silently drop a change.

## Pages from other plugins

A plugin that registers a Filament page or resource on the `app` or `server` panel is
picked up automatically and rendered in place, with its own label and icon. Nothing is
required from the plugin author.

For anything else, `JoanFo\PterodactylUi\PteroUi` takes explicit entries:

```php
use JoanFo\PterodactylUi\PteroUi;

PteroUi::serverNavigation([
    'id' => 'my-plugin',
    'label' => 'My Plugin',
    'icon' => 'tabler-rocket',
    'url' => fn (Server $server) => route('my-plugin.page', $server),
]);
```

A plugin can also ship a React page by placing a bundle at `resources/ptero-ui/entry.js`.
It is published and loaded automatically:

```js
window.PteroUI.registerServerPage({
    id: 'my-plugin',
    label: 'My Plugin',
    render: () => window.PteroUI.React.createElement('p', null, 'Hello'),
});
```

## Themes

Panel themes work here. Their render hooks run for this interface, and its components use
the same class names themes target on Filament (`fi-topbar`, `fi-main`, `fi-ta-row` and so
on), so an existing theme applies without changes.

Rules aimed at Filament components that have no equivalent here will not match. Themes can
also set these properties, which the interface reads:

```css
--ptero-ui-bg
--ptero-ui-surface
--ptero-ui-accent
--ptero-ui-text
```

## Updating

The interface loads parts of itself on demand as separate hashed files. Publishing is
additive so a browser with the old version open keeps working, but reload any tab left
open across an update. Emptying `public/plugins/pterodactyl-ui` reclaims the space used by
superseded versions and is rebuilt on the next request.

## Licence

This plugin is GPL-3.0. `frontend/vendor/` is Pterodactyl Panel, copyright Dane Everitt
and contributors, used under the MIT licence; its licence text is kept at
`frontend/vendor/LICENSE.md` and must stay with those files in any redistribution.

Pterodactyl is a registered trademark of its owners. The name is used here to describe
what the interface is and does not imply endorsement or affiliation.
