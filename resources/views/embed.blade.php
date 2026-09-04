{{-- Injected into every Filament page through the panels::head.end render hook.

     It does nothing at all unless the request carries the embed parameter, which only
     happens for URLs the interface framed. In that case the panel chrome is removed and
     the remaining content is reshaped to sit inside the interface's own layout: same
     surface colours, same width, and tables that scroll within their own box instead of
     forcing the whole frame wide. The page also reports its height back so the frame can
     size itself. --}}
@php
    $embedParameter = \JoanFo\PterodactylUi\Support\Paths::embedParameter();
    $embedMaxWidth = config('pterodactyl-ui.embed.max_width', '1200px');
    $embedInset = config('pterodactyl-ui.embed.inset', '7.5%');
@endphp

@if (request()->boolean($embedParameter))
    <style>
        /* ---- chrome the surrounding layout already provides ---------------- */
        .fi-sidebar,
        .fi-sidebar-open,
        .fi-topbar,
        .fi-breadcrumbs,
        .fi-global-search,
        .fi-user-menu,
        .fi-sidebar-close-overlay,
        .fi-simple-footer,
        .fi-layout-sidebar-toggle-btn-ctn {
            display: none !important;
        }

        /* ---- size the page like the rest of the interface -------------------
           Filament is configured for a wider column than this interface uses, so a framed
           page would otherwise run edge to edge while everything around it sits in a
           centred column. Match the container and inset the content. */
        .fi-layout,
        .fi-main-ctn,
        .fi-page {
            margin-inline: 0 !important;
            padding-inline: 0 !important;
            max-width: none !important;
            width: 100% !important;
        }

        /* The container is a flex row, which makes the main column a flex item — and a
           stretched flex item ignores max-width, so the width cap has no effect on
           pages laid out this way. Blocking it out restores normal centring. Filament also
           starts this element hidden and reveals it from its sidebar script, which never
           runs once the sidebar is removed. */
        .fi-main-ctn {
            display: block !important;
            opacity: 1 !important;
        }

        .fi-main {
            max-width: {{ $embedMaxWidth }} !important;
            margin-inline: auto !important;
            padding-block: 1rem !important;
            padding-inline: max(1rem, {{ $embedInset }}) !important;
            width: 100% !important;
            flex: none !important;
        }

        /* The frame itself is transparent, all the way up to <html>, so the themed page
           behind it shows through — background, gradient, starfield and all. It used to
           paint its own grey here, which showed a framed page as a slab of unrelated colour
           over the theme. Unthemed, the interface's own background shows through, so no
           fallback is needed here. */
        html,
        body,
        .fi-body,
        .fi-layout,
        .fi-main-ctn,
        .fi-page {
            background: transparent !important;
        }

        html,
        body {
            /* The frame is sized to its content and the page around it scrolls, so any
               scrollbar in here is a second one on top of that. */
            overflow: hidden;
        }

        /* Filament's layout is sized against the viewport. Inside a frame the viewport IS
           the frame, so reporting the document height back grows the frame, which grows the
           viewport, which grows the document — it never settles, and the page ends up with
           a huge empty run below the content. Sizing the layout to its content instead is
           what stops that. */
        html,
        body,
        .fi-body,
        .fi-layout,
        .fi-main-ctn,
        .fi-main,
        .fi-page {
            min-height: 0 !important;
            height: auto !important;
        }

        /* ---- match the interface's density ---------------------------------
           Filament sizes itself for a full page with a sidebar. Inside the frame that
           is oversized here, so sections and tables are brought down to the density the
           rest of the interface uses.

           Only spacing and shape are set here. Colour was forced too, with !important,
           which beat any panel theme that had already made these surfaces transparent —
           so a framed page showed grey panels while the page around it showed the theme.
           Colour is left to the theme, exactly as it is on the panel itself. */
        :root {
            --ptero-muted: #9aa5b1;
        }

        .fi-section,
        .fi-section-content-ctn,
        .fi-fo-component-ctn,
        .fi-wi,
        .fi-ta-ctn {
            border-radius: 0.25rem !important;
        }

        .fi-section-header,
        .fi-ta-header {
            padding: 0.75rem !important;
        }

        .fi-section-content {
            padding: 0.75rem !important;
        }

        /* ---- tables --------------------------------------------------------
           Filament tables assume a full-width page. Constrain the box and let the table
           scroll inside it so a wide table never stretches the frame. */
        .fi-ta-ctn {
            max-width: 100% !important;
            overflow: visible !important;
        }

        .fi-ta-content {
            overflow-x: auto !important;
            max-width: 100% !important;
        }

        .fi-ta-table {
            font-size: 0.8rem !important;
            width: 100% !important;
            table-layout: auto !important;
        }

        .fi-ta-header-cell,
        .fi-ta-cell {
            padding-block: 0.3rem !important;
            padding-inline: 0.5rem !important;
            line-height: 1.3 !important;
        }

        /* Cells wrap. Holding them on one line is what pushed the table past the frame and
           got the right-hand columns clipped. */
        .fi-ta-cell,
        .fi-ta-cell .fi-ta-text-item-label {
            white-space: normal !important;
            overflow-wrap: anywhere;
        }

        .fi-ta-header-cell {
            white-space: nowrap;
        }

        /* Row controls are sized for a full page and drive the row height. */
        .fi-ta-cell .fi-btn,
        .fi-ta-actions .fi-btn,
        .fi-ta-cell .fi-icon-btn {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.75rem !important;
        }

        /* Icon-only row actions carry no text, so shrinking them with the text buttons made
           them harder to hit than they need to be. Sized on their own instead. */
        .fi-ta-cell .fi-icon-btn svg,
        .fi-ta-actions .fi-icon-btn svg {
            width: 1.25rem !important;
            height: 1.25rem !important;
        }

        /* Row artwork — a mod or plugin's icon in a listing. 25% up from the compact size
           the tables were first trimmed to. */
        .fi-ta-cell .fi-avatar,
        .fi-ta-cell img {
            max-height: 1.875rem !important;
        }

        .fi-ta-header-cell {
            font-size: 0.75rem !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Empty-state blocks reserve a lot of vertical space for a full page. */
        .fi-ta-empty-state {
            padding-block: 1.5rem !important;
        }

        /* ---- typography and controls --------------------------------------- */
        .fi-header-heading {
            font-size: 1.125rem !important;
        }

        .fi-header {
            margin-bottom: 0.75rem !important;
        }

        .fi-btn {
            font-size: 0.8125rem !important;
        }

        /* ---- tabs ----------------------------------------------------------
           Restyled to match the server sub-navigation: flat, muted labels with a cyan
           underline on the active one, rather than Filament's pill treatment. */
        .fi-tabs {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            gap: 0 !important;
            border-bottom: 1px solid rgb(255 255 255 / 0.08);
            overflow-x: auto;
            scrollbar-width: none;
        }

        .fi-tabs::-webkit-scrollbar {
            display: none;
        }

        .fi-tabs-item {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 0.75rem 1rem !important;
            white-space: nowrap;
        }

        .fi-tabs-item .fi-tabs-item-label {
            color: var(--ptero-muted) !important;
            font-size: 0.875rem !important;
            font-weight: 400 !important;
        }

        .fi-tabs-item:hover .fi-tabs-item-label {
            color: #e4e7eb !important;
        }

        .fi-tabs-item[aria-selected='true'],
        .fi-tabs-item.fi-active,
        .fi-tabs-item-active {
            background: transparent !important;
            box-shadow: inset 0 -2px #14919b !important;
        }

        .fi-tabs-item[aria-selected='true'] .fi-tabs-item-label,
        .fi-tabs-item.fi-active .fi-tabs-item-label,
        .fi-tabs-item-active .fi-tabs-item-label {
            color: #fff !important;
        }
    </style>

    <script>
        (function () {
            // Match the frame's colour scheme; the interface is dark only.
            document.documentElement.classList.add('dark');
            try {
                localStorage.setItem('theme', 'dark');
            } catch (error) {
                /* storage unavailable in private mode */
            }

            /*
             * Themes attach full-viewport decoration to the body — starfields, gradients,
             * video layers. The page around this frame is themed too and already draws
             * them, so a second copy here stacks on the first as a band of unrelated
             * background below the content.
             *
             * They can't be removed with CSS: they're elements, not a background, and their
             * names differ per theme. Measuring them is what identifies them — anything
             * pinned to the viewport, about as tall as it, and not part of the panel's own
             * layout is decoration that belongs to the page outside.
             */
            var stripDecoration = function () {
                var removed = false;

                Array.prototype.forEach.call(document.body.children, function (element) {
                    var name = element.tagName;

                    if (name === 'SCRIPT' || name === 'STYLE' || name === 'TEMPLATE' || name === 'NOSCRIPT') {
                        return;
                    }

                    if (String(element.className || '').indexOf('fi-') === 0) {
                        return;
                    }

                    var style = window.getComputedStyle(element);

                    if (style.position !== 'fixed' && style.position !== 'absolute') {
                        return;
                    }

                    if (element.offsetHeight >= window.innerHeight * 0.6 || style.height === '100vh') {
                        element.style.setProperty('display', 'none', 'important');
                        removed = true;
                    }
                });

                // Those layers hold the page open, so the frame has to be re-measured once
                // they are gone or it keeps the taller size they forced.
                if (removed && typeof report === 'function') {
                    report();
                }
            };

            document.addEventListener('DOMContentLoaded', stripDecoration);
            window.addEventListener('load', stripDecoration);
            // Themes that build their layers from a script may not have run yet.
            setTimeout(stripDecoration, 400);
            setTimeout(stripDecoration, 1500);

            var last = 0;

            var report = function () {
                // Measure the content box rather than the document. Document height is
                // influenced by the frame's own height, which feeds back and grows the page
                // without bound.
                var content = document.querySelector('.fi-main') || document.querySelector('.fi-page') || document.body;
                var height = content ? Math.ceil(content.getBoundingClientRect().height) : 0;

                if (!height || Math.abs(height - last) < 2) {
                    return;
                }

                last = height;

                if (window.parent !== window) {
                    window.parent.postMessage(
                        { source: 'ptero-ui-embed', height: height, href: location.href },
                        window.location.origin
                    );
                }
            };

            window.addEventListener('load', report);
            document.addEventListener('DOMContentLoaded', report);

            if (typeof ResizeObserver !== 'undefined') {
                document.addEventListener('DOMContentLoaded', function () {
                    new ResizeObserver(report).observe(document.body);
                });
            }

            // Livewire swaps large parts of the DOM without changing its size immediately.
            document.addEventListener('livewire:navigated', report);
            setInterval(report, 1000);
        })();
    </script>
@endif
