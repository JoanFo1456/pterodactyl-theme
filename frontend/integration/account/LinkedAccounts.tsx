import * as React from 'react';
import { useCallback, useEffect, useState } from 'react';
import http from '@/api/http';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { Button } from '@/components/elements/button';

interface Provider {
    id: string;
    name: string;
    linked: boolean;
    link_url: string;
}

/**
 * Sign-in providers configured on this panel, and whether the account is linked to them.
 *
 * Linking has to leave the page — it is an OAuth redirect — so that action is a plain
 * navigation rather than a request.
 */
export const LinkedAccounts = () => {
    const [providers, setProviders] = useState<Provider[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const load = useCallback(() => {
        http.get('/api/pterodactyl-ui/account/oauth')
            .then(({ data }) => setProviders(data.data))
            .catch(() => setError('Could not load your linked accounts.'));
    }, []);

    useEffect(load, [load]);

    const unlink = async (provider: Provider) => {
        setBusy(true);

        try {
            const { data } = await http.delete(`/api/pterodactyl-ui/account/oauth/${provider.id}`);
            setProviders(data.data);
        } catch (failure) {
            setError('Could not unlink that account.');
        }

        setBusy(false);
    };

    if (!providers) {
        return (
            <PageContentBlock title={'Linked Accounts'}>
                {error ? <p style={{ color: '#f86a6a' }}>{error}</p> : <Spinner size={'large'} centered />}
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={'Linked Accounts'}>
            {error && <p style={{ color: '#f86a6a', marginBottom: '1rem' }}>{error}</p>}

            <TitledGreyBox title={'Linked accounts'}>
                {providers.length === 0 ? (
                    <p style={{ color: '#9aa5b1', textAlign: 'center', fontSize: '0.875rem' }}>
                        This panel has no sign-in providers configured.
                    </p>
                ) : (
                    providers.map((provider) => (
                        <div
                            key={provider.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: '1rem',
                                padding: '0.5rem 0',
                                borderTop: '1px solid rgba(255,255,255,.06)',
                            }}
                        >
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div>{provider.name}</div>
                                <div style={{ fontSize: '0.75rem', color: '#9aa5b1' }}>
                                    {provider.linked ? 'Linked' : 'Not linked'}
                                </div>
                            </div>

                            {provider.linked ? (
                                <Button.Danger
                                    size={Button.Sizes.Small}
                                    disabled={busy}
                                    onClick={() => unlink(provider)}
                                >
                                    Unlink
                                </Button.Danger>
                            ) : (
                                <Button
                                    size={Button.Sizes.Small}
                                    disabled={busy}
                                    onClick={() => {
                                        window.location.href = provider.link_url;
                                    }}
                                >
                                    Link
                                </Button>
                            )}
                        </div>
                    ))
                )}
            </TitledGreyBox>
        </PageContentBlock>
    );
};
