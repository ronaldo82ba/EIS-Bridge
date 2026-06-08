import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import StatusBadge from '../../components/StatusBadge';
import StatCard from '../../components/StatCard';
import VendorLicenses from '../../components/VendorLicenses';
import { toastError, toastSuccess } from '../../components/Toast';
import { vendorService } from '../../services/vendorService';

const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function VendorDetail() {
    const { id: vendorId } = useParams();
    const queryClient = useQueryClient();
    const [webhook, setWebhook] = useState({ url: '', secret: '' });
    const [showKey, setShowKey] = useState(false);
    const [rotatedKey, setRotatedKey] = useState(null);

    const { data: vendor, isLoading, isError } = useQuery({
        queryKey: ['vendors', vendorId],
        queryFn: async () => (await vendorService.get(vendorId)).data?.data,
        enabled: Boolean(vendorId),
    });

    useEffect(() => {
        if (vendor) {
            setWebhook({
                url: vendor.webhook_url ?? '',
                secret: '',
            });
        }
    }, [vendor]);

    const webhookMutation = useMutation({
        mutationFn: (values) =>
            vendorService.updateWebhook(vendorId, {
                webhook_url: values.url,
                ...(values.secret ? { webhook_secret: values.secret } : {}),
            }),
        onSuccess: () => {
            toastSuccess('Webhook settings saved');
            queryClient.invalidateQueries({ queryKey: ['vendors', vendorId] });
        },
        onError: (error) => toastError(error.response?.data?.message ?? 'Failed to save webhook settings'),
    });

    const rotateKeyMutation = useMutation({
        mutationFn: () => vendorService.rotateApiKey(vendorId),
        onSuccess: (response) => {
            const newKey = response.data?.api_key;
            if (newKey) {
                setRotatedKey(newKey);
                setShowKey(true);
            }
            toastSuccess('API key rotated. Copy the new key now — it will not be shown again.');
            queryClient.invalidateQueries({ queryKey: ['vendors', vendorId] });
        },
        onError: (error) => toastError(error.response?.data?.message ?? 'Failed to rotate API key'),
    });

    if (isLoading) {
        return <div className="text-sm text-slate-500">Loading…</div>;
    }

    if (isError || !vendor) {
        return (
            <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Failed to load vendor. Please try again.
            </div>
        );
    }

    const displayKey = rotatedKey ?? vendor.api_key_masked ?? 'Not configured';
    const deliveries = vendor.webhook_deliveries ?? [];
    const merchants = vendor.merchants ?? [];
    const ipWhitelist = vendor.ip_whitelists ?? [];
    const stats = vendor.stats ?? {};

    const saveWebhook = () => {
        webhookMutation.mutate(webhook);
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-800">{vendor.name}</h1>
                    <div className="mt-1 flex items-center gap-3 text-sm text-slate-500">
                        <span>Code: {vendor.code ?? vendor.id}</span>
                        <StatusBadge status={vendor.status ?? 'active'} />
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    <Link
                        to={`/vendors/health?vendor=${vendorId}`}
                        className="text-sm font-medium text-blue-600 hover:text-blue-800"
                    >
                        Health
                    </Link>
                    <Link to="/vendors" className="text-sm text-slate-500 hover:text-slate-700">
                        Back to list
                    </Link>
                </div>
            </div>

            <div className="space-y-3 rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="font-medium text-slate-800">API Key</h2>
                {rotatedKey && (
                    <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        New API key generated. Copy it now — it will not be shown again after you leave this page.
                    </p>
                )}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        className={inputClass}
                        type={showKey ? 'text' : 'password'}
                        value={displayKey}
                        readOnly
                    />
                    <div className="flex gap-2">
                        <button
                            type="button"
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                            onClick={() => setShowKey((current) => !current)}
                        >
                            {showKey ? 'Hide' : 'Show'}
                        </button>
                        <button
                            type="button"
                            className="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50"
                            onClick={() => rotateKeyMutation.mutate()}
                            disabled={rotateKeyMutation.isPending}
                        >
                            {rotateKeyMutation.isPending ? 'Rotating…' : 'Rotate Key'}
                        </button>
                    </div>
                </div>
                {!rotatedKey && (
                    <p className="text-xs text-slate-500">
                        Only a masked preview is shown. Rotate the key to receive a new full key once.
                    </p>
                )}
            </div>

            <div className="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="font-medium text-slate-800">Webhook Configuration</h2>

                <div>
                    <label className="mb-1 block text-sm text-slate-600">Webhook URL</label>
                    <input
                        className={inputClass}
                        value={webhook.url}
                        onChange={(event) => setWebhook({ ...webhook, url: event.target.value })}
                    />
                </div>

                <div>
                    <label className="mb-1 block text-sm text-slate-600">Webhook Secret</label>
                    <input
                        className={inputClass}
                        type="password"
                        value={webhook.secret}
                        placeholder={vendor.webhook_url ? '•••••••• (leave blank to keep current)' : 'Enter secret'}
                        onChange={(event) => setWebhook({ ...webhook, secret: event.target.value })}
                    />
                </div>

                <button
                    type="button"
                    className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                    onClick={saveWebhook}
                    disabled={webhookMutation.isPending}
                >
                    {webhookMutation.isPending ? 'Saving…' : 'Save Webhook Settings'}
                </button>
            </div>

            {ipWhitelist.length > 0 && (
                <div className="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 className="mb-3 font-medium text-slate-800">IP Whitelist</h2>
                    <ul className="space-y-2 text-sm">
                        {ipWhitelist.map((entry) => (
                            <li
                                key={entry.id}
                                className="flex items-center justify-between rounded border border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <span className="font-mono text-slate-800">{entry.ip_address}</span>
                                {entry.label && <span className="text-slate-500">{entry.label}</span>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Merchants</h2>

                {merchants.length === 0 ? (
                    <div className="text-sm text-slate-500">No merchants yet</div>
                ) : (
                    <ul className="space-y-2">
                        {merchants.map((merchant) => (
                            <li key={merchant.id} className="rounded border border-slate-200 bg-slate-50 p-3">
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <div className="font-medium text-slate-800">{merchant.name}</div>
                                        <div className="text-xs text-slate-500">TIN: {merchant.tin ?? '—'}</div>
                                    </div>
                                    <Link
                                        to={`/merchants/${merchant.id}`}
                                        className="text-sm font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        View
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Recent Webhook Deliveries</h2>

                {deliveries.length === 0 ? (
                    <div className="text-sm text-slate-500">No webhook events yet</div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-slate-50">
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Event</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Status</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Attempt</th>
                                    <th className="px-3 py-2 text-left font-medium text-slate-600">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                {deliveries.map((delivery) => (
                                    <tr key={delivery.id} className="border-b border-slate-100">
                                        <td className="px-3 py-2">{delivery.event}</td>
                                        <td className="px-3 py-2">{delivery.status_code ?? '—'}</td>
                                        <td className="px-3 py-2">{delivery.attempt}</td>
                                        <td className="px-3 py-2 text-slate-500">
                                            {delivery.created_at
                                                ? new Date(delivery.created_at).toLocaleString()
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Vendor Stats</h2>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard title="Invoices Today" value={stats.today_total ?? 0} accent="blue" />
                    <StatCard title="Acknowledged" value={stats.today_ack ?? 0} accent="green" />
                    <StatCard title="Rejected" value={stats.today_rejected ?? 0} accent="red" />
                    <StatCard title="Webhook Failures" value={stats.webhook_failures ?? 0} accent="amber" />
                </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <VendorLicenses vendorId={vendorId} />
            </div>
        </div>
    );
}
