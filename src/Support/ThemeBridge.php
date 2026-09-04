<?php

namespace JoanFo\PterodactylUi\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Makes the panel's theme plugins reach this interface.
 *
 * A theme styles the panel by registering render hooks on a Filament panel, which Filament
 * collects when that panel boots. This interface is served from its own routes, so no panel
 * ever boots and none of that ran — themes had no effect here at all.
 *
 * Booting the panel and rendering the same hooks fixes the delivery half. Both ends of the
 * document matter: themes put stylesheets in the head, but the ones that draw something —
 * an animated background, an overlay — attach it from a body hook. Rendering only the head
 * loads a theme's CSS while never creating the element it styles.
 *
 * The other half is handled at build time. Themes are written against Filament's markup,
 * and upstream's DOM is styled-components with generated class names, so .fi-* rules had
 * nothing to match. The equivalent components here are given the Filament names as well —
 * see integration/upstream-patches.js — so a theme that styles .fi-topbar, .fi-section or
 * .fi-main styles this interface too, unmodified. Anything expressed as a custom property
 * carries across on top of that, which is why the shell also maps a documented set of them
 * onto this interface's own surfaces.
 */
class ThemeBridge
{
    /**
     * Hooks rendered into the document head.
     */
    private const HeadHooks = [
        'panels::head.start',
        'panels::head.end',
    ];

    /**
     * Hooks rendered at the top of the body — where themes attach backgrounds and overlays.
     */
    private const BodyStartHooks = [
        'panels::body.start',
    ];

    /**
     * Hooks rendered at the end of the body.
     */
    private const BodyEndHooks = [
        'panels::body.end',
    ];

    public static function head(): HtmlString
    {
        return self::render(self::HeadHooks);
    }

    public static function bodyStart(): HtmlString
    {
        return self::render(self::BodyStartHooks);
    }

    public static function bodyEnd(): HtmlString
    {
        return self::render(self::BodyEndHooks);
    }

    /**
     * @param  array<int, string>  $hooks
     */
    private static function render(array $hooks): HtmlString
    {
        $panel = self::panel();

        if ($panel === null) {
            return new HtmlString('');
        }

        $previous = Filament::getCurrentPanel();

        Filament::setCurrentPanel($panel);

        try {
            $output = '';

            foreach ($hooks as $hook) {
                try {
                    $output .= (string) FilamentView::renderHook($hook);
                } catch (Throwable) {
                    // One theme failing shouldn't cost the others their styling, so each
                    // hook is rendered on its own.
                    continue;
                }
            }

            return new HtmlString($output);
        } finally {
            Filament::setCurrentPanel($previous);
        }
    }

    /**
     * The app panel, booted so its render hooks are registered. Booting is what every
     * Filament request does implicitly; here it has to be asked for.
     */
    private static function panel(): ?Panel
    {
        try {
            $panel = Filament::getPanel('app', isStrict: false);

            $panel?->boot();

            return $panel;
        } catch (Throwable) {
            return null;
        }
    }
}
