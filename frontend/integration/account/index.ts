import { registerAccountPage } from '@ui/extensions';
import { Preferences } from '@ui/account/Preferences';
import { Passkeys } from '@ui/account/Passkeys';
import { LinkedAccounts } from '@ui/account/LinkedAccounts';

/**
 * Account screens for the settings this panel has and Pterodactyl does not. Registered
 * through the same mechanism plugins use, so they sit alongside the built-in account tabs
 * rather than being special-cased.
 */
export const installAccountPages = (): void => {
    registerAccountPage({ id: 'preferences', path: '/preferences', label: 'Preferences', sort: 40, render: Preferences });
    registerAccountPage({ id: 'passkeys', path: '/passkeys', label: 'Passkeys', sort: 50, render: Passkeys });
    registerAccountPage({ id: 'linked', path: '/linked', label: 'Linked Accounts', sort: 60, render: LinkedAccounts });
};
