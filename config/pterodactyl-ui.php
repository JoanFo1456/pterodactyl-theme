<?php

return [
    /*
    | Path prefix the original Filament client panels are moved to once this plugin is
    | enabled. The React app takes over "/" and "/server/{server}", so Filament needs
    | somewhere else to live — its pages stay fully functional there and are what the
    | React app embeds when a plugin contributes a page it doesn't know how to render.
    */
    'legacy_path' => env('PTERO_UI_LEGACY_PATH', 'legacy'),

    /*
    | Set to false to keep Filament at its original paths and serve the React app from
    | "/ui" instead. Useful while evaluating the plugin on a live panel.
    */
    'takeover' => env('PTERO_UI_TAKEOVER', true),

    /*
    | Path the React app is served from when 'takeover' is disabled.
    */
    'standalone_path' => env('PTERO_UI_STANDALONE_PATH', 'ui'),

    /*
    | Query parameter appended to embedded Filament URLs. When present, the panel chrome
    | (sidebar, topbar, breadcrumbs) is hidden so an embedded page blends into the React
    | layout, and the response is allowed to be framed by the panel itself.
    */
    'embed_parameter' => 'ptero-embed',

    /*
    | How an embedded Filament page is sized inside the interface.
    |
    | 'max_width' matches the interface's own content container, so a framed page lines up
    | with the pages either side of it rather than running edge to edge — Filament defaults
    | to a wider column (screen-2xl) than this interface uses.
    |
    | 'inset' is the breathing room on each side, as a CSS length or percentage.
    */
    'embed' => [
        'max_width' => env('PTERO_UI_EMBED_MAX_WIDTH', '1500px'),
        'inset' => env('PTERO_UI_EMBED_INSET', '1.5rem'),
    ],

    /*
    | Extra navigation entries, keyed by scope ('app' or 'server'). Each entry accepts
    | id, label, icon (a blade-icons name), url and sort. Plugins are better served by
    | the PteroUi facade, but this lets an administrator pin a link without writing code.
    */
    'extra_navigation' => [
        'app' => [],
        'server' => [],
    ],
];
