import * as React from 'react';
import { useEffect, useState } from 'react';
import http from '@/api/http';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';

interface Preferences {
    language: string;
    languages: Record<string, string>;
    customization: Record<string, string | number | boolean>;
    is_admin: boolean;
}

const FONTS = ['monospace', 'IBM Plex Mono', 'JetBrains Mono', 'Fira Code', 'Source Code Pro', 'Consolas', 'Menlo'];

const row: React.CSSProperties = { display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' };
const hint: React.CSSProperties = { fontSize: '0.75rem', color: '#9aa5b1', marginTop: '0.5rem' };

const control: React.CSSProperties = {
    width: '100%',
    padding: '0.5rem 0.75rem',
    background: '#52606d',
    border: '2px solid #616e7c',
    borderRadius: '0.25rem',
    color: '#cbd2d9',
};

const field: React.CSSProperties = { flex: '1 1 10rem', minWidth: 0 };

const label: React.CSSProperties = {
    display: 'block',
    fontSize: '0.75rem',
    color: '#9aa5b1',
    marginBottom: '0.35rem',
};

/**
 * Interface language and the sign-in redirect.
 *
 * Both apply immediately — they are a select and a checkbox with nothing destructive
 * behind them, so an explicit save button would only add a step.
 */
export const Preferences = () => {
    const [data, setData] = useState<Preferences | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        http.get('/api/pterodactyl-ui/account/preferences')
            .then(({ data }) => setData(data))
            .catch(() => setError('Could not load your preferences.'));
    }, []);

    const save = (payload: Record<string, unknown>) => {
        setSaving(true);
        setError(null);

        http.put('/api/pterodactyl-ui/account/preferences', payload)
            .then(({ data }) => setData(data))
            .catch(() => setError('Could not save that change.'))
            .then(() => setSaving(false));
    };

    if (!data) {
        return (
            <PageContentBlock title={'Preferences'}>
                {error ? <p style={{ color: '#f86a6a' }}>{error}</p> : <Spinner size={'large'} centered />}
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={'Preferences'}>
            {error && <p style={{ color: '#f86a6a', marginBottom: '1rem' }}>{error}</p>}

            <TitledGreyBox title={'Language'}>
                <div style={row}>
                    <select
                        value={data.language}
                        disabled={saving}
                        onChange={(event) => save({ language: event.target.value })}
                        style={{
                            width: '100%',
                            padding: '0.5rem 0.75rem',
                            background: '#52606d',
                            border: '2px solid #616e7c',
                            borderRadius: '0.25rem',
                            color: '#cbd2d9',
                        }}
                    >
                        {Object.entries(data.languages).map(([code, label]) => (
                            <option key={code} value={code}>
                                {label}
                            </option>
                        ))}
                    </select>
                </div>
            </TitledGreyBox>

            <div style={{ marginTop: '1rem' }}>
                <TitledGreyBox title={'Console'}>
                    <div style={{ ...row, alignItems: 'flex-end' }}>
                        <div style={field}>
                            <label style={label}>Font</label>
                            <select
                                style={control}
                                disabled={saving}
                                value={String(data.customization.console_font ?? 'monospace')}
                                onChange={(event) =>
                                    save({ customization: { console_font: event.target.value } })
                                }
                            >
                                {FONTS.map((font) => (
                                    <option key={font} value={font}>
                                        {font}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div style={field}>
                            <label style={label}>Font size</label>
                            <input
                                type={'number'}
                                min={8}
                                max={32}
                                style={control}
                                disabled={saving}
                                defaultValue={Number(data.customization.console_font_size ?? 14)}
                                onBlur={(event) =>
                                    save({ customization: { console_font_size: Number(event.target.value) } })
                                }
                            />
                        </div>

                        <div style={field}>
                            <label style={label}>Rows</label>
                            <input
                                type={'number'}
                                min={5}
                                max={200}
                                style={control}
                                disabled={saving}
                                defaultValue={Number(data.customization.console_rows ?? 30)}
                                onBlur={(event) =>
                                    save({ customization: { console_rows: Number(event.target.value) } })
                                }
                            />
                        </div>

                        <div style={field}>
                            <label style={label}>Graph points</label>
                            <input
                                type={'number'}
                                min={5}
                                max={120}
                                style={control}
                                disabled={saving}
                                defaultValue={Number(data.customization.console_graph_period ?? 30)}
                                onBlur={(event) =>
                                    save({ customization: { console_graph_period: Number(event.target.value) } })
                                }
                            />
                        </div>
                    </div>
                    <p style={hint}>Reload the console for these to take effect.</p>
                </TitledGreyBox>
            </div>

            {data.is_admin && (
                <div style={{ marginTop: '1rem' }}>
                <TitledGreyBox title={'Sign-in destination'}>
                    <label style={{ ...row, cursor: 'pointer' }}>
                        <input
                            type={'checkbox'}
                            checked={Boolean(data.customization.redirect_to_admin)}
                            disabled={saving}
                            onChange={(event) =>
                                save({ customization: { redirect_to_admin: event.target.checked } })
                            }
                        />
                        <span>Go straight to the admin area after signing in</span>
                    </label>
                    </TitledGreyBox>
                </div>
            )}
        </PageContentBlock>
    );
};
