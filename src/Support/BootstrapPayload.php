<?php

namespace JoanFo\PterodactylUi\Support;

use App\Enums\CustomizationKey;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Everything the React app needs before it can render its first frame: who is signed in,
 * where the panel's other URL spaces live, and which extension assets to load.
 *
 * It is inlined into the shell document so the first paint doesn't wait on a request, and
 * served from an endpoint as well so the app can refresh it after a plugin is enabled.
 */
class BootstrapPayload
{
    public function __construct(private PanelBridge $bridge) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user): array
    {
        $assets = ExtensionRegistry::assets();

        return [
            'app' => [
                'name' => config('app.name', 'Pelican'),
                'logo' => config('app.logo'),
                'favicon' => config('app.favicon', '/pelican.ico'),
                'version' => config('app.version', 'canary'),
                'locale' => app()->getLocale(),
            ],
            // Read by the terminal and the graphs as they mount, so there is no request to
            // wait on before the console is drawn with the right font and size.
            'console' => [
                'rows' => (int) ($user?->getCustomization(CustomizationKey::ConsoleRows) ?? 30),
                'font' => (string) ($user?->getCustomization(CustomizationKey::ConsoleFont) ?? 'monospace'),
                'font_size' => (int) ($user?->getCustomization(CustomizationKey::ConsoleFontSize) ?? 14),
                'graph_period' => (int) ($user?->getCustomization(CustomizationKey::ConsoleGraphPeriod) ?? 30),
            ],
            'paths' => [
                'base' => Paths::uiBase(),
                // Where the interface's lazily-loaded chunks live. Read at runtime so the
                // panel works when served from a sub-directory.
                'assets' => asset('plugins/pterodactyl-ui/dist') . '/',
                'api' => url('/api/client'),
                'manifest' => url('/api/pterodactyl-ui'),
                'legacy' => url(Paths::legacyPathFor('app')),
                'legacy_server' => url(Paths::legacyPathFor('server')),
                'admin' => $this->adminUrl($user),
                'logout' => $this->logoutUrl(),
                'profile' => $this->profileUrl(),
            ],
            'user' => $user === null ? null : [
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
                'name' => trim(($user->username ?? '') ?: (string) $user->email),
                'avatar' => $user instanceof HasAvatar ? $user->getFilamentAvatarUrl() : null,
                'is_admin' => (bool) $user->isAdmin(),
                'is_root_admin' => (bool) $user->isRootAdmin(),
                'language' => $user->language,
                'two_factor_enabled' => $user->hasEmailAuthentication() || filled($user->getAppAuthenticationSecret()),
                'created_at' => $user->created_at?->toAtomString(),
                'updated_at' => $user->updated_at?->toAtomString(),
            ],
            'navigation' => [
                'account' => $this->accountNavigation(),
            ],
            'extensions' => $assets,
            'csrf' => csrf_token(),
        ];
    }

    /**
     * Account-area navigation.
     *
     * The interface's own account pages are used as-is: password, email and two-factor
     * each save independently, the way Pterodactyl does it, rather than one form behind a
     * save/cancel pair. The panel's Filament profile is deliberately not offered here — it
     * follows a different design.
     *
     * The cost is that the settings only Pelican has — passkeys, interface language,
     * appearance customization and linked OAuth accounts — have no native screen yet and
     * remain reachable from the panel's own profile page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accountNavigation(): array
    {
        return $this->bridge->accountPages();
    }

    private function adminUrl(?User $user): ?string
    {
        try {
            $admin = Filament::getPanel('admin', isStrict: false);

            if ($admin === null || $user === null || !$user->canAccessPanel($admin)) {
                return null;
            }

            return $admin->getUrl();
        } catch (Throwable) {
            return null;
        }
    }

    private function logoutUrl(): ?string
    {
        return $this->routeUrl('filament.app.auth.logout') ?? $this->routeUrl('filament.admin.auth.logout');
    }

    private function profileUrl(): ?string
    {
        try {
            $url = Filament::getPanel('app', isStrict: false)?->getProfileUrl();
        } catch (Throwable) {
            return null;
        }

        return $url === null ? null : Paths::asEmbedded($url);
    }

    private function routeUrl(string $name): ?string
    {
        try {
            return Route::has($name) ? route($name) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
