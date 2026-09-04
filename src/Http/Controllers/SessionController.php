<?php

namespace JoanFo\PterodactylUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The interface signs out by posting to /auth/logout, which is where Pterodactyl's own
 * endpoint lives. Filament owns the login screen here, so only the logout half is needed;
 * it ends the session the same way Filament's own logout does.
 */
class SessionController extends Controller
{
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new JsonResponse([], 204);
    }
}
