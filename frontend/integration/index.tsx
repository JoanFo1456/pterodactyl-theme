// Imported first: it sets webpack's chunk base path, which must be in place before any
// lazily-loaded route is reached.
import '@ui/public-path';
import * as React from 'react';
import ReactDOM from 'react-dom';
import { install as installGlobals } from '@ui/globals';
import { install as installExtensionApi, ExtensionsProvider } from '@ui/extensions';
import { installAccountPages } from '@ui/account';

// The vendored app reads its user and site settings from globals during render, and plugin
// bundles expect window.PteroUI to exist before they run, so both are set up first.
installGlobals();
installExtensionApi();
installAccountPages();

// Imported after the globals are in place; the module graph has side effects of its own.
/* eslint-disable import/first */
import App from '@/components/App';
import http from '@/api/http';
// Upstream's entry point initialises this. Without it react-i18next has no instance, and
// the activity logs — the only screens that translate strings — fail to render.
import '@/i18n';
/* eslint-enable import/first */

// Upstream handles an expired session through its own login screen, which this panel
// doesn't serve — Filament owns authentication. Reloading sends the request back through
// the panel's auth middleware, which redirects to the real login page.
http.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 401) {
            window.location.reload();
        }

        throw error;
    },
);

const mount = () => {
    const element = document.getElementById('pterodactyl-ui-root');

    if (!element) {
        return;
    }

    ReactDOM.render(
        <ExtensionsProvider>
            <App />
        </ExtensionsProvider>,
        element,
    );
};

// Deferred scripts run in order and DOMContentLoaded fires after the last of them, so
// waiting for it gives every plugin bundle a chance to register before the first render.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
