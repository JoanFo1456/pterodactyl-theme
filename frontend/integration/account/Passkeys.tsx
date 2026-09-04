import * as React from 'react';
import { useCallback, useEffect, useState } from 'react';
import { startRegistration } from '@simplewebauthn/browser';
import http from '@/api/http';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { Button } from '@/components/elements/button';

interface Passkey {
    id: number;
    name: string;
    authenticator: string | null;
    last_used_at: string | null;
    created_at: string | null;
}

const when = (value: string | null) => (value ? new Date(value).toLocaleString() : 'Never');

/**
 * Passkeys registered to the account.
 *
 * Registration goes through Laravel Passkeys' own endpoints and the same WebAuthn library
 * the panel itself uses, so the payloads are whatever the server already expects rather
 * than something reimplemented here.
 */
export const Passkeys = () => {
    const [keys, setKeys] = useState<Passkey[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [name, setName] = useState('');

    const load = useCallback(() => {
        http.get('/api/pterodactyl-ui/account/passkeys')
            .then(({ data }) => setKeys(data.data))
            .catch(() => setError('Could not load your passkeys.'));
    }, []);

    useEffect(load, [load]);

    const add = async () => {
        setBusy(true);
        setError(null);

        try {
            const { data: options } = await http.get('/user/passkeys/options');
            const credential = await startRegistration({ optionsJSON: options });

            await http.post('/user/passkeys', { ...credential, name: name.trim() || 'Passkey' });

            setName('');
            load();
        } catch (failure: any) {
            setError(
                failure?.name === 'NotAllowedError'
                    ? 'That request was cancelled.'
                    : failure?.response?.data?.message || 'Could not register a passkey on this device.',
            );
        }

        setBusy(false);
    };

    const remove = async (passkey: Passkey) => {
        setBusy(true);

        try {
            await http.delete(`/user/passkeys/${passkey.id}`);
            load();
        } catch (failure) {
            setError('Could not remove that passkey.');
        }

        setBusy(false);
    };

    if (!keys) {
        return (
            <PageContentBlock title={'Passkeys'}>
                {error ? <p style={{ color: '#f86a6a' }}>{error}</p> : <Spinner size={'large'} centered />}
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={'Passkeys'}>
            {error && <p style={{ color: '#f86a6a', marginBottom: '1rem' }}>{error}</p>}

            <TitledGreyBox title={'Add a passkey'}>
                <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', alignItems: 'center' }}>
                    <input
                        value={name}
                        placeholder={'Name this device'}
                        onChange={(event) => setName(event.target.value)}
                        disabled={busy}
                        style={{
                            flex: '1 1 12rem',
                            padding: '0.5rem 0.75rem',
                            background: '#52606d',
                            border: '2px solid #616e7c',
                            borderRadius: '0.25rem',
                            color: '#cbd2d9',
                        }}
                    />
                    <Button disabled={busy} onClick={add}>
                        Register
                    </Button>
                </div>
                <p style={{ fontSize: '0.75rem', color: '#9aa5b1', marginTop: '0.5rem' }}>
                    Your browser will ask you to confirm with the device or password manager holding the key.
                </p>
            </TitledGreyBox>

            <div style={{ marginTop: '1rem' }}>
                <TitledGreyBox title={'Your passkeys'}>
                    {keys.length === 0 ? (
                        <p style={{ color: '#9aa5b1', textAlign: 'center', fontSize: '0.875rem' }}>
                            You have no passkeys.
                        </p>
                    ) : (
                        keys.map((passkey) => (
                            <div
                                key={passkey.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '1rem',
                                    padding: '0.5rem 0',
                                    borderTop: '1px solid rgba(255,255,255,.06)',
                                }}
                            >
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div>{passkey.name}</div>
                                    <div style={{ fontSize: '0.75rem', color: '#9aa5b1' }}>
                                        {passkey.authenticator || 'Unknown device'} &middot; last used{' '}
                                        {when(passkey.last_used_at)}
                                    </div>
                                </div>
                                <Button.Danger size={Button.Sizes.Small} disabled={busy} onClick={() => remove(passkey)}>
                                    Delete
                                </Button.Danger>
                            </div>
                        ))
                    )}
                </TitledGreyBox>
            </div>
        </PageContentBlock>
    );
};
