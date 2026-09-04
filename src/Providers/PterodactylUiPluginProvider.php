<?php

namespace JoanFo\PterodactylUi\Providers;

use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Server;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JoanFo\PterodactylUi\Http\Controllers\AccountController;
use JoanFo\PterodactylUi\Http\Controllers\LocaleController;
use JoanFo\PterodactylUi\Http\Controllers\ManifestController;
use JoanFo\PterodactylUi\Http\Controllers\PanelController;
use JoanFo\PterodactylUi\Http\Controllers\SessionController;
use JoanFo\PterodactylUi\Http\Responses\LoginResponse;
use JoanFo\PterodactylUi\Support\Paths;

class PterodactylUiPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core binds this a few lines before it loads plugins, so rebinding here wins.
        // Without it, signing in would land users on the relocated Filament panel.
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        $this->registerRoutes();
    }

    /**
     * Routes are declared one path shape at a time rather than as a catch-all: the React
     * app only claims the URLs Pterodactyl itself uses, so /admin, /legacy, /installer,
     * /livewire and the APIs keep resolving the way they always did.
     */
    private function registerRoutes(): void
    {
        $prefix = Paths::uiPrefix();

        Route::middleware($this->middleware())
            ->prefix($prefix)
            ->group(function () {
                Route::get('/', PanelController::class)->name('pterodactyl-ui.home');
                Route::get('/account', PanelController::class)->name('pterodactyl-ui.account');
                Route::get('/account/{path}', PanelController::class)->where('path', '.*')->name('pterodactyl-ui.account.page');
                Route::get('/server/{server}', PanelController::class)->name('pterodactyl-ui.server');
                Route::get('/server/{server}/{path}', PanelController::class)->where('path', '.*')->name('pterodactyl-ui.server.page');
            });

        Route::middleware($this->middleware())
            ->prefix('api/pterodactyl-ui')
            ->group(function () {
                Route::get('/bootstrap', [ManifestController::class, 'bootstrap'])->name('pterodactyl-ui.api.bootstrap');
                Route::get('/servers/{server}/navigation', [ManifestController::class, 'serverNavigation'])->name('pterodactyl-ui.api.server-navigation');

                // Account settings the interface has no native screen for upstream.
                Route::prefix('/account')->group(function () {
                    Route::get('/preferences', [AccountController::class, 'preferences'])->name('pterodactyl-ui.api.preferences');
                    Route::put('/preferences', [AccountController::class, 'updatePreferences']);
                    Route::get('/passkeys', [AccountController::class, 'passkeys'])->name('pterodactyl-ui.api.passkeys');
                    Route::get('/oauth', [AccountController::class, 'oauth'])->name('pterodactyl-ui.api.oauth');
                    Route::delete('/oauth/{driver}', [AccountController::class, 'unlinkOauth']);
                });
            });

        // Endpoints the bundled interface expects at fixed paths. Registering them before
        // core's own /auth group means the logout post resolves here rather than falling
        // through to the guest-only routes.
        Route::middleware($this->middleware())->group(function () {
            Route::post('/auth/logout', [SessionController::class, 'logout'])->name('pterodactyl-ui.logout');
            Route::get('/locales/locale.json', LocaleController::class)->name('pterodactyl-ui.locales');

            // The interface's "open in admin" link uses Pterodactyl's URL shape. Pointing
            // it at Filament's equivalent here avoids editing the vendored component.
            Route::get('/admin/servers/view/{server}', function (string $server) {
                $model = Server::query()->whereKey($server)->orWhere('uuid_short', $server)->firstOrFail();

                abort_unless(user()?->canAccessPanel(Filament::getPanel('admin')) ?? false, 403);

                return redirect(EditServer::getUrl(['record' => $model], panel: 'admin'));
            })->name('pterodactyl-ui.admin-server');
        });
    }

    /**
     * The same stack the Filament panels use, minus the parts that only make sense inside a
     * panel. 2FA enforcement is included so it cannot be bypassed through these routes.
     *
     * @return array<int, string>
     */
    private function middleware(): array
    {
        return [
            'web',
            AuthenticateSession::class,
            'auth',
            RequireTwoFactorAuthentication::class,
            RedirectIfNotInstalled::class,
        ];
    }
}
