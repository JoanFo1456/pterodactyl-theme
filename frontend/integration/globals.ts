import type { SiteSettings } from '@/state/settings';

/**
 * Bridges this panel's bootstrap payload into the globals the vendored app expects.
 *
 * Upstream injects `PterodactylUser` and `SiteConfiguration` from its own Blade wrapper.
 * The shell view here injects `PteroUIBootstrap` instead, so those two globals are filled
 * in from it before the app renders. Doing it this way keeps the vendored tree unmodified.
 */
export interface Bootstrap {
    app: { name: string; logo: string | null; favicon: string; version: string; locale: string };
    console?: { rows: number; font: string; font_size: number; graph_period: number };
    paths: {
        base: string;
        assets: string;
        api: string;
        manifest: string;
        legacy: string;
        legacy_server: string;
        admin: string | null;
        logout: string | null;
        profile: string | null;
    };
    user: {
        uuid: string;
        username: string;
        email: string;
        avatar: string | null;
        is_admin: boolean;
        is_root_admin: boolean;
        language: string;
        two_factor_enabled: boolean;
        created_at: string;
        updated_at: string;
    } | null;
    navigation: { account: NavigationEntry[] };
    extensions: { scripts: string[]; styles: string[] };
    csrf: string;
}

export interface NavigationEntry {
    id: string;
    key: string;
    label: string;
    group: string | null;
    icon: string | null;
    sort: number;
    url: string;
    raw_url: string;
    type: 'embed' | 'link' | 'native';
}

/**
 * True only the first time a given page is opened this session.
 *
 * The lists gate their loading spinner on "do I have records", which can't tell an empty
 * collection from one that hasn't loaded — so an empty list blocked on the network every
 * single visit. Remembering that a page has been opened lets the second visit onwards show
 * its empty state straight away and refresh in the background. Marking on open is safe:
 * a collection that has records keeps them in the store, so there is nothing to flash past.
 */
const visited = new Set<string>();

const firstVisit = (): boolean => {
    const key = window.location.pathname;

    if (visited.has(key)) {
        return false;
    }

    visited.add(key);

    return true;
};

/**
 * Console preferences, applied to the terminal and its graphs.
 *
 * The terminal is constructed once and its addon re-fits on every resize, so the font and
 * size go in as construction options while the row count is re-applied after each fit —
 * otherwise fitting to the container would immediately discard it.
 */
const consoleOptions = () => {
    const settings = bootstrap?.console;

    return {
        fontFamily: settings?.font || 'monospace',
        fontSize: settings?.font_size || 14,
        rows: settings?.rows || 30,
    };
};

const graphPoints = (): number => bootstrap?.console?.graph_period || 30;

interface FitLike {
    fit: () => void;
}

interface TerminalLike {
    cols: number;
    rows: number;
    resize: (cols: number, rows: number) => void;
}

const wrapFit = (addon: FitLike, terminal: TerminalLike): FitLike => {
    const fit = addon.fit.bind(addon);

    addon.fit = () => {
        fit();

        const rows = consoleOptions().rows;

        if (rows > 0 && terminal.rows !== rows) {
            terminal.resize(terminal.cols, rows);
        }
    };

    return addon;
};

const brand = (): string => bootstrap?.app?.name || 'Pelican';

const brandUrl = (): string => bootstrap?.paths?.base || '/';

interface ExtendedWindow extends Window {
    __pteroUiFirstVisit?: () => boolean;
    __pteroUiBrand?: () => string;
    __pteroUiBrandUrl?: () => string;
    __pteroUiConsole?: () => Record<string, unknown>;
    __pteroUiGraphPoints?: () => number;
    __pteroUiFit?: (addon: FitLike, terminal: TerminalLike) => FitLike;
    PteroUIBootstrap?: Bootstrap;
    PterodactylUser?: Record<string, unknown>;
    SiteConfiguration?: SiteSettings;
}

const globalWindow = window as ExtendedWindow;

export const bootstrap: Bootstrap = globalWindow.PteroUIBootstrap as Bootstrap;

export const install = (): void => {
    globalWindow.__pteroUiFirstVisit = firstVisit;
    globalWindow.__pteroUiBrand = brand;
    globalWindow.__pteroUiBrandUrl = brandUrl;
    globalWindow.__pteroUiConsole = consoleOptions;
    globalWindow.__pteroUiGraphPoints = graphPoints;
    globalWindow.__pteroUiFit = wrapFit;

    const user = bootstrap?.user;

    if (user) {
        globalWindow.PterodactylUser = {
            uuid: user.uuid,
            username: user.username,
            email: user.email,
            root_admin: user.is_root_admin,
            use_totp: user.two_factor_enabled,
            language: user.language,
            created_at: user.created_at,
            updated_at: user.updated_at,
        };
    }

    globalWindow.SiteConfiguration = {
        name: bootstrap?.app?.name ?? 'Pelican',
        locale: bootstrap?.app?.locale ?? 'en',
        // Captcha only guards the login screen, which this panel still serves itself.
        recaptcha: { enabled: false, siteKey: '' },
    };
};
