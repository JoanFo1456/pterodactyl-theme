import vendored from '@vendor/routers/routes';
import { extensionRoutes } from '@ui/extensions';

/**
 * Stands in for the vendored route table (aliased at build time).
 *
 * Both routers read `routes.account` / `routes.server` during render, so exposing them as
 * getters means pages contributed by other plugins appear as soon as the manifest arrives,
 * without the vendored routers needing to know they exist.
 */
export default {
    get account() {
        return [...vendored.account, ...extensionRoutes('account')];
    },
    get server() {
        return [...vendored.server, ...extensionRoutes('server')];
    },
};
