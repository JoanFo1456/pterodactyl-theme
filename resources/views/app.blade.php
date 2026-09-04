{{-- Shell document for the React client area. Everything below the root element is
     rendered by resources/dist/pterodactyl-ui.js; this page only has to hand it a
     starting payload and get out of the way. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>{{ config('app.name', 'Pelican') }}</title>
    <link rel="icon" href="{{ config('app.favicon', '/pelican.ico') }}">

    @if ($fonts)
        <link rel="stylesheet" href="{{ $fonts }}">
    @endif

    @foreach ($bootstrap['extensions']['styles'] as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach

    {{-- Theme contract.
         Declared before the panel's themes so a theme can override any of it, and read
         through var() so a theme that only sets the panel's own variables still carries
         across. Rules aimed at Filament's markup work here too: the interface's components
         answer to the Filament class names, so a theme needs no changes to style this page.
         These properties are for what class names cannot express. --}}
    <style>
        :root {
            --ptero-ui-bg: var(--gt-bg, hsl(209, 20%, 25%));
            --ptero-ui-surface: var(--gt-surface, hsl(209, 18%, 30%));
            --ptero-ui-accent: var(--gt-btn-primary, var(--primary-500, #0967d2));
            --ptero-ui-text: var(--gt-text, hsl(210, 16%, 82%));

            /* Filament derives its sizing from this token. Themes that restyle Filament
               write calc(var(--spacing) * n), and a calc() over an undefined variable is
               invalid, so without it every such rule would be dropped rather than applied.
               Nothing in this interface's own stylesheet uses it. */
            --spacing: 0.25rem;
        }

        /* No !important: a theme's own body rule loads after this block and should win. */
        body {
            background: var(--ptero-ui-bg);
            color: var(--ptero-ui-text);
        }
    </style>

    {{-- Surfaces, when a theme is present.

         The interface paints its panels with fixed Tailwind greys — bg-neutral-700 on a
         row, bg-gray-600 on a console block. Those are opaque, so a theme could set the
         page background and still be covered by a grid of grey boxes sitting on top of it.
         Naming each box for a theme to override doesn't scale: every new surface upstream
         adds is another one that stays grey.

         So the surfaces step back instead. Turning the greys into translucent overlays
         lets whatever the theme paints on the body show through them, which is how a theme
         reaches this interface without knowing anything about it — the same effect it gets
         on the panel by making Filament's sections transparent. The depth ordering is kept
         so panels still read as panels.

         !important is needed because the interface's stylesheet is injected by script at
         runtime, which puts it after everything in the head. It is declared before the
         themes below, so a theme that wants a surface back still wins.

         The interface is dark only — the shell fixes color-scheme — so white is the right
         thing to lift these with. --}}
    @if ($themed)
        <style>
            .bg-neutral-900,
            .bg-gray-900 {
                background-color: transparent !important;
            }

            .bg-neutral-800,
            .bg-gray-800 {
                background-color: rgb(255 255 255 / 0.03) !important;
            }

            .bg-neutral-700,
            .bg-gray-700 {
                background-color: rgb(255 255 255 / 0.07) !important;
            }

            .bg-neutral-600,
            .bg-gray-600 {
                background-color: rgb(255 255 255 / 0.10) !important;
            }

            /* Hover states are separate classes, so they need the same treatment or a row
               would snap back to solid grey under the cursor. */
            .hover\:bg-neutral-600:hover,
            .hover\:bg-gray-600:hover {
                background-color: rgb(255 255 255 / 0.14) !important;
            }

            .hover\:bg-neutral-700:hover,
            .hover\:bg-gray-700:hover {
                background-color: rgb(255 255 255 / 0.11) !important;
            }
        </style>
    @endif

    {{-- The panel's theme plugins. These normally run when a Filament panel boots, which
         never happens on these routes, so they are rendered explicitly. --}}
    {!! $themeHead !!}

    <style>
        /*
         * Colour is not set here. The theme contract above is already in the head, so the
         * first paint is covered by it — and repeating a background here would land after
         * the panel's themes and stamp the default back over whatever they set.
         *
         * <html> is deliberately left without a background so <body>'s propagates to the
         * canvas. Giving html its own colour is what previously showed as a hard cut below
         * short pages, and it would also strand a theme's background at the body box.
         */
        body { margin: 0; }
    </style>
</head>
{{-- Filament's body classes, so panel themes that key off them reach this page too.
     The panel-scoped name is what Filament actually emits (fi-panel-app, not fi-panel):
     a bare fi-panel is a section-level class to themes, and putting it on the body made
     them strip the page background. --}}
<body class="fi-body fi-panel-app">
    {{-- Themes attach backgrounds and overlays here. Rendered before the interface mounts
         so anything they draw sits behind it. --}}
    {!! $themeBodyStart !!}

    {{-- Modals and dropdowns render through a portal into this element. It has to exist
         before the interface mounts, or every portal gets a null container. --}}
    <div id="modal-portal"></div>

    <div id="pterodactyl-ui-root"></div>

    <script>
        window.PteroUIBootstrap = @json($bootstrap);
    </script>

    @if ($bundle)
        <script src="{{ $bundle }}" defer></script>
    @else
        {{-- The published bundle is missing, which usually means public/plugins isn't
             writable. Say so instead of showing an empty page. --}}
        <script>
            document.getElementById('pterodactyl-ui-root').innerHTML =
                '<div style="font:14px/1.6 system-ui;padding:48px;max-width:640px;margin:0 auto">' +
                '<h1 style="font-size:20px">Pterodactyl UI assets are missing</h1>' +
                '<p>The interface bundle could not be published to <code>public/plugins/pterodactyl-ui/dist</code>. ' +
                'Check that the directory is writable by the web server, then reload.</p></div>';
        </script>
    @endif

    {{-- Loaded after the main bundle so window.PteroUI already exists when a plugin's
         extension script calls registerServerPage(). --}}
    @foreach ($bootstrap['extensions']['scripts'] as $script)
        <script src="{{ $script }}" defer></script>
    @endforeach

    {!! $themeBodyEnd !!}
</body>
</html>
