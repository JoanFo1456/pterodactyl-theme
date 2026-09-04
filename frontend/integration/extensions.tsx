import * as React from 'react';
import { useEffect, useRef, useState } from 'react';
import { history } from '@/components/history';
import { bootstrap, NavigationEntry } from '@ui/globals';

/**
 * Lets pages that other plugins registered appear in the vendored app's navigation.
 *
 * Two sources feed in. The panel's manifest endpoint reports Filament pages and resources
 * that plugins registered on the server panel; those render in a frame. A plugin can also
 * ship its own bundle and call window.PteroUI.registerServerPage() for a real React page.
 * Neither requires this plugin to know anything about the plugin in question.
 */
export interface NativePage {
    id: string;
    label: string;
    sort?: number;
    /**
     * Route to mount at, relative to the section. Omit it and the page is namespaced under
     * /x/ instead, which is the safe default for anything registered by another plugin:
     * keys come from arbitrary code, and one matching a built-in route would collide with
     * it. Set it only when you know the path is free.
     */
    path?: string;
    /** A React component. Rendered as one, so it may use hooks. */
    render?: React.ComponentType;
    /**
     * Escape hatch for bundles that aren't React: return a DOM node and it is mounted as-is.
     */
    mount?: (context: { server?: unknown }) => Element;
}

interface Extension {
    key: string;
    label: string;
    sort: number;
    type: 'embed' | 'link' | 'native';
    url?: string;
    rawUrl?: string;
    path?: string;
    render?: NativePage['render'];
    mount?: NativePage['mount'];
}

type Scope = 'account' | 'server';

const state: Record<Scope, Extension[]> = { account: [], server: [] };
const native: Record<Scope, Extension[]> = { account: [], server: [] };
const listeners = new Set<() => void>();

/**
 * Route lists and their components are cached, and only rebuilt when the set of extensions
 * actually changes.
 *
 * The router reads its route table during render. Handing it a freshly built component
 * function each time makes React treat it as a different component type and tear the whole
 * subtree down and back up on every render — which for a framed page means destroying the
 * frame and reloading the page inside it, over and over. Stable identities are what stop
 * that.
 */
const routeCache: Record<Scope, RouteDefinition[] | null> = { account: null, server: null };
const componentCache = new Map<string, React.ComponentType>();

interface RouteDefinition {
    path: string;
    name: string;
    permission: null;
    component: React.ComponentType;
}

const notify = () => {
    routeCache.account = null;
    routeCache.server = null;
    listeners.forEach((listener) => listener());
};

const fromEntry = (entry: NavigationEntry): Extension => ({
    key: entry.key,
    label: entry.label,
    sort: entry.sort,
    type: entry.type,
    url: entry.url,
    rawUrl: entry.raw_url,
});

/** Renders one extension page: a framed Filament page, or a plugin's own component. */
const ExtensionPage = ({ extension }: { extension: Extension }) => {
    const host = useRef<HTMLDivElement>(null);
    // Starts small: a framed page that opens at full height shows a tall blank box while
    // the page inside it is still booting.
    const [height, setHeight] = useState(240);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        if (extension.type !== 'embed') {
            return undefined;
        }

        // The panel strips its chrome from an embedded page and posts the page height back,
        // which is what lets a framed page scroll with the layout instead of inside a box.
        const onMessage = (event: MessageEvent) => {
            if (event.origin !== window.location.origin || event.data?.source !== 'ptero-ui-embed') {
                return;
            }

            const reported = Number(event.data.height);

            if (Number.isFinite(reported) && reported > 0) {
                setHeight(Math.max(160, Math.ceil(reported)));
                setReady(true);
            }
        };

        window.addEventListener('message', onMessage);

        return () => window.removeEventListener('message', onMessage);
    }, [extension.type]);

    useEffect(() => {
        if (extension.type !== 'native' || !extension.mount || !host.current) {
            return undefined;
        }

        const node = extension.mount({});
        const container = host.current;

        container.appendChild(node);

        return () => {
            if (node.parentNode === container) {
                container.removeChild(node);
            }
        };
    }, [extension]);

    if (extension.type === 'native') {
        // Rendered as a component rather than called, so a page is free to use hooks.
        const Component = extension.render;

        return Component ? <Component /> : <div ref={host} />;
    }

    return (
        <div style={{ position: 'relative', minHeight: ready ? undefined : 160 }}>
            {!ready && (
                <div
                    style={{
                        position: 'absolute',
                        inset: 0,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: '#9aa5b1',
                        fontSize: '0.875rem',
                    }}
                >
                    Loading&hellip;
                </div>
            )}

            <iframe
                src={extension.url}
                title={extension.label}
                loading={'eager'}
                style={{
                    width: '100%',
                    border: 0,
                    display: 'block',
                    height,
                    background: 'transparent',
                    opacity: ready ? 1 : 0,
                    transition: 'opacity .15s ease',
                }}
                sandbox={'allow-same-origin allow-scripts allow-forms allow-popups allow-downloads allow-modals'}
            />
        </div>
    );
};

/**
 * Route definitions in the shape the vendored router expects. Read through a getter on
 * each render, so pages that arrive after the first paint still show up.
 */
const findExtension = (key: string): Extension | undefined =>
    [...native.server, ...native.account, ...state.server, ...state.account].find(
        (extension) => extension.key === key,
    );

/**
 * One component instance per extension key, reused for the life of the page. It looks the
 * extension up when it renders rather than capturing it, so a refreshed manifest is picked
 * up without changing the component's identity.
 */
const componentFor = (key: string): React.ComponentType => {
    const cached = componentCache.get(key);

    if (cached) {
        return cached;
    }

    const component: React.ComponentType = () => {
        const extension = findExtension(key);

        return extension ? <ExtensionPage extension={extension} /> : null;
    };

    component.displayName = `Extension(${key})`;
    componentCache.set(key, component);

    return component;
};

export const extensionRoutes = (scope: Scope): RouteDefinition[] => {
    if (routeCache[scope]) {
        return routeCache[scope] as RouteDefinition[];
    }

    routeCache[scope] = [...native[scope], ...state[scope]]
        .filter((extension) => extension.type !== 'link')
        .sort((a, b) => a.sort - b.sort || a.label.localeCompare(b.label))
        .map((extension) => ({
            path: extension.path ?? `/x/${extension.key}`,
            name: extension.label,
            permission: null,
            component: componentFor(extension.key),
        }));

    return routeCache[scope] as RouteDefinition[];
};

const load = async (identifier: string) => {
    try {
        const response = await fetch(`${bootstrap.paths.manifest}/servers/${encodeURIComponent(identifier)}/navigation`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const payload = await response.json();

        state.server = (payload.pages || []).map(fromEntry);
    } catch (error) {
        // Extra pages are a bonus — the built-in tabs must still work without them.
        state.server = [];
    }

    notify();
};

/**
 * Sits above the vendored app, loads the manifest for whichever server is being viewed,
 * and re-renders on change so the routers pick the new entries up.
 */
export const ExtensionsProvider = ({ children }: { children: React.ReactNode }) => {
    const [, setVersion] = useState(0);
    const loaded = useRef<string | null>(null);

    useEffect(() => {
        const listener = () => setVersion((value) => value + 1);
        listeners.add(listener);

        return () => {
            listeners.delete(listener);
        };
    }, []);

    useEffect(() => {
        const sync = () => {
            const base = bootstrap.paths.base === '/' ? '' : bootstrap.paths.base.replace(/\/$/, '');
            const match = window.location.pathname.replace(base, '').match(/^\/server\/([^/]+)/);
            const identifier = match?.[1] ?? null;

            if (identifier === loaded.current) {
                return;
            }

            loaded.current = identifier;

            if (identifier) {
                load(identifier);
            } else {
                state.server = [];
                notify();
            }
        };

        sync();

        // Subscribing to the router's history is exact; polling for URL changes four times
        // a second was both wasteful and always slightly behind.
        return history.listen(sync);
    }, []);

    return <>{children}</>;
};

const register = (scope: Scope) => (page: NativePage) => {
    if (!page?.id) {
        return;
    }

    native[scope] = [
        ...native[scope].filter((existing) => existing.key !== page.id),
        {
            key: page.id,
            label: page.label || page.id,
            sort: page.sort ?? 100,
            type: 'native',
            path: page.path,
            render: page.render,
            mount: page.mount,
        },
    ];

    notify();
};

export const registerServerPage = register('server');
export const registerAccountPage = register('account');

/** Public browser API for plugin bundles. */
export const install = (): void => {
    (window as unknown as Record<string, unknown>).PteroUI = {
        version: '1.0.0',
        React,
        config: bootstrap,
        registerServerPage: register('server'),
        registerAccountPage: register('account'),
    };

    window.dispatchEvent(new CustomEvent('ptero-ui:ready'));
};

// Account-scope entries come from the shell payload rather than a per-server request.
state.account = (bootstrap?.navigation?.account || []).map(fromEntry);
