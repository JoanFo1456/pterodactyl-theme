<?php

namespace JoanFo\PterodactylUi\Http\Responses;

use App\Enums\CustomizationKey;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use JoanFo\PterodactylUi\Support\Paths;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Filament still owns the login screen, but its idea of "home" is the panel it was moved
 * to. Send freshly authenticated users to the React app instead, keeping the existing
 * "redirect admins to the admin area" preference intact.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        /** @var ?User $user */
        $user = Filament::auth()->user();

        if ($user?->getCustomization(CustomizationKey::RedirectToAdmin) && $user->canAccessPanel(Filament::getPanel('admin'))) {
            return redirect()->intended(Filament::getPanel('admin')->getUrl());
        }

        return redirect()->intended(url(Paths::uiBase()));
    }
}
