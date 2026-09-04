/* eslint-disable camelcase, no-undef */
import { bootstrap } from '@ui/globals';

/**
 * Points webpack's lazy-chunk loader at wherever the plugin published its assets.
 *
 * The build bakes in an absolute default, which breaks if the panel is served from a
 * sub-directory. Reading it from the bootstrap payload at runtime keeps chunk URLs correct
 * wherever the panel lives. Must be imported before anything that can trigger a chunk load.
 */
declare let __webpack_public_path__: string;

const configured = bootstrap?.paths?.assets;

if (configured) {
    __webpack_public_path__ = configured.endsWith('/') ? configured : `${configured}/`;
}
