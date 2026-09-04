import { createBrowserHistory } from 'history';
import { bootstrap } from '@ui/globals';

/**
 * Replaces the vendored history module so the router honours the path the plugin is
 * mounted on. With takeover enabled that is "/", which matches upstream; with the app
 * served from a sub-path it is that prefix instead.
 */
export const history = createBrowserHistory({ basename: bootstrap?.paths?.base || '/' });
