<?php

namespace JoanFo\PterodactylUi\Http\Controllers;

use App\Enums\CustomizationKey;
use App\Extensions\OAuth\OAuthService;
use App\Models\User;
use App\Services\Helpers\LanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Passkeys\Passkey;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Account settings the interface has no screen for.
 *
 * The interface covers what Pterodactyl covers — password, email, two-factor, API keys,
 * SSH keys, activity. Pelican additionally has passkeys, an interface language, linked
 * OAuth accounts and a handful of customization flags. These endpoints back native screens
 * for those, so they don't require dropping into the panel's own profile page.
 *
 * Passkey registration and removal are not here on purpose: Laravel Passkeys already
 * exposes routes for the WebAuthn ceremony, and the interface talks to those directly.
 */
class AccountController extends Controller
{
    /**
     * Customization values that mean something in this interface.
     *
     * The console settings are wired into the interface's own terminal and graphs, so they
     * apply here rather than only to the panel's Filament console. The remaining keys —
     * dashboard layout, top navigation, servers per page, button style — describe the
     * Filament UI this plugin replaces, so offering them would be controls that do nothing.
     */
    private const SupportedCustomization = [
        CustomizationKey::RedirectToAdmin,
        CustomizationKey::ConsoleRows,
        CustomizationKey::ConsoleFont,
        CustomizationKey::ConsoleFontSize,
        CustomizationKey::ConsoleGraphPeriod,
    ];

    public function preferences(Request $request, LanguageService $languages): JsonResponse
    {
        $user = $this->user($request);

        $customization = [];

        foreach (self::SupportedCustomization as $key) {
            $customization[$key->value] = $user->getCustomization($key);
        }

        return new JsonResponse([
            'language' => $user->language,
            'languages' => $languages->getAvailableLanguages(),
            'customization' => $customization,
            'is_admin' => (bool) $user->isAdmin(),
        ]);
    }

    public function updatePreferences(Request $request, LanguageService $languages): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'language' => ['sometimes', 'string', Rule::in(array_keys($languages->getAvailableLanguages()))],
            'customization' => ['sometimes', 'array'],
            'customization.' . CustomizationKey::RedirectToAdmin->value => ['sometimes', 'boolean'],
            'customization.' . CustomizationKey::ConsoleRows->value => ['sometimes', 'integer', 'min:5', 'max:200'],
            'customization.' . CustomizationKey::ConsoleFont->value => ['sometimes', 'string', 'max:100'],
            'customization.' . CustomizationKey::ConsoleFontSize->value => ['sometimes', 'integer', 'min:8', 'max:32'],
            'customization.' . CustomizationKey::ConsoleGraphPeriod->value => ['sometimes', 'integer', 'min:5', 'max:120'],
        ]);

        if (array_key_exists('language', $data)) {
            $user->language = $data['language'];
        }

        if (array_key_exists('customization', $data)) {
            // Merge rather than replace: the panel's own UI stores its settings in the same
            // column, and this screen only owns a couple of the keys.
            $customization = $user->customization ?? [];

            foreach (self::SupportedCustomization as $key) {
                if (array_key_exists($key->value, $data['customization'])) {
                    $customization[$key->value] = $data['customization'][$key->value];
                }
            }

            $user->customization = $customization;
        }

        $user->save();

        return $this->preferences($request, $languages);
    }

    /**
     * Passkeys registered to the account. Creating and deleting them goes through Laravel
     * Passkeys' own routes, which handle the WebAuthn ceremony.
     */
    public function passkeys(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $passkeys = Passkey::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Passkey $passkey) => [
                'id' => $passkey->getKey(),
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'last_used_at' => $passkey->last_used_at?->toAtomString(),
                'created_at' => $passkey->created_at?->toAtomString(),
            ])
            ->values()
            ->all();

        return new JsonResponse(['data' => $passkeys]);
    }

    /**
     * Sign-in providers this panel has configured, and which of them the account is linked
     * to. Linking is a redirect the browser has to follow, so only the URL is returned.
     */
    public function oauth(Request $request, OAuthService $oauth): JsonResponse
    {
        $user = $this->user($request);
        $linked = $user->oauth ?? [];

        $providers = [];

        foreach ($oauth->getEnabled() as $schema) {
            $id = $schema->getId();

            $providers[] = [
                'id' => $id,
                'name' => $schema->getName(),
                'linked' => array_key_exists($id, $linked),
                'link_url' => url('/auth/oauth/redirect/' . $id),
            ];
        }

        return new JsonResponse(['data' => $providers]);
    }

    public function unlinkOauth(Request $request, OAuthService $oauth, string $driver): JsonResponse
    {
        $user = $this->user($request);
        $schema = $oauth->get($driver);

        throw_unless($schema !== null, new NotFoundHttpException('Unknown sign-in provider.'));

        try {
            $oauth->unlinkUser($user, $schema);
        } catch (Throwable) {
            // Fall back to editing the column directly so unlinking always succeeds.
            $linked = $user->oauth ?? [];
            unset($linked[$driver]);

            $user->update(['oauth' => $linked]);
        }

        return $this->oauth($request, $oauth);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        throw_unless($user instanceof User, new NotFoundHttpException());

        return $user;
    }
}
