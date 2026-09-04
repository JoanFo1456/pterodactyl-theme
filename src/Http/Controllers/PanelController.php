<?php

namespace JoanFo\PterodactylUi\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use JoanFo\PterodactylUi\Support\AssetPublisher;
use JoanFo\PterodactylUi\Support\BootstrapPayload;
use JoanFo\PterodactylUi\Support\ThemeBridge;

/**
 * Serves the single page app shell for every React route. Routing past this point happens
 * in the browser, so the same document answers "/", "/account/..." and "/server/...".
 */
class PanelController extends Controller
{
    public function __construct(private BootstrapPayload $payload) {}

    public function __invoke(Request $request): View
    {
        // The bundle splits into lazily-fetched chunks, so the whole dist directory is
        // mirrored rather than named file by file. Fonts sit outside it because a rebuild
        // empties dist.
        AssetPublisher::publishDirectory('pterodactyl-ui', plugin_path('pterodactyl-ui', 'resources', 'dist'), 'dist');
        AssetPublisher::publishDirectory('pterodactyl-ui', plugin_path('pterodactyl-ui', 'resources', 'fonts'), 'fonts');

        $themeHead = ThemeBridge::head();

        return view('pterodactyl-ui::app', [
            'bootstrap' => $this->payload->build($request->user()),
            // Whatever the panel's theme plugins contribute. Both ends of the body matter:
            // a theme's stylesheet goes in the head, but the element it styles is attached
            // from a body hook.
            'themeHead' => $themeHead,
            // A theme contributing nothing means this interface keeps its own opaque
            // surfaces; one that does means they step back and let the theme's background
            // through. Deciding it here keeps an unthemed install looking untouched.
            'themed' => trim((string) $themeHead) !== '',
            'themeBodyStart' => ThemeBridge::bodyStart(),
            'themeBodyEnd' => ThemeBridge::bodyEnd(),
            // The interface injects its own stylesheet at runtime, so only the bundle and
            // the self-hosted typeface need referencing here.
            'bundle' => AssetPublisher::publish('pterodactyl-ui', plugin_path('pterodactyl-ui', 'resources', 'dist', 'pterodactyl-ui.js'), 'dist/pterodactyl-ui.js'),
            'fonts' => AssetPublisher::publish('pterodactyl-ui', plugin_path('pterodactyl-ui', 'resources', 'fonts', 'fonts.css'), 'fonts/fonts.css'),
        ]);
    }
}
