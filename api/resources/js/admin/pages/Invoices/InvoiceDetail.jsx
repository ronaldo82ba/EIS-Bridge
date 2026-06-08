import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api from '../../api';
import StatusBadge from '../../components/StatusBadge';
import { maskSensitiveJson } from '../../utils/maskJson';
import InvoiceRetryModal from './InvoiceRetryModal';

const echoEnabled = Boolean(import.meta.env.VITE_PUSHER_APP_KEY);

const TABS = [
    { key: 'pos', label: 'POS JSON' },
    { key: 'bir', label: 'BIR JSON' },
    { key: 'signed', label: 'Signed JSON' },
    { key: 'logs', label: 'Transmission Logs' },
];

function normalizeLogs(invoice) {
    return invoice?.logs ?? invoice?.transmission_logs ?? [];
}

function findLogTimestamp(logs, event) {
    const match = logs.find((log) => log.event === event);
    return match?.timestamp ?? null;
}

function countRetryEvents(logs) {
    return logs.filter((log) => String(log.event ?? '').toLowerCase().includes('retry')).length;
}

function buildTimeline(invoice, logs) {
    const retryCount = invoice?.retry_count ?? countRetryEvents(logs);
    const transmittedAt =
        findLogTimestamp(logs, 'sent_to_eis') ?? findLogTimestamp(logs, 'transmitting');

    return [
        { label: 'Received', timestamp: invoice?.created_at, done: Boolean(invoice?.created_at) },
        { label: 'Mapped', timestamp: findLogTimestamp(logs, 'mapped'), done: Boolean(invoice?.bir_json) },
        { label: 'Signed', timestamp: findLogTimestamp(logs, 'signed'), done: Boolean(invoice?.signed_json) },
        { label: 'Transmitted', timestamp: transmittedAt, done: Boolean(transmittedAt) },
        {
            label: 'Retries',
            timestamp: retryCount > 0 ? `${retryCount} attempt${retryCount === 1 ? '' : 's'}` : null,
            done: retryCount > 0,
            muted: retryCount === 0,
        },
        {
            label: 'EIS status',
            timestamp: invoice?.eis_status ?? 'pending',
            done: Boolean(invoice?.eis_status),
            isStatus: true,
        },
    ];
}

function JsonBlock({ data }) {
    if (data == null) {
        return <p className="text-sm text-slate-500">No data available.</p>;
    }

    return (
        <pre className="max-h-[32rem] overflow-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100">
            {JSON.stringify(data, null, 2)}
        </pre>
    );
}

function formatTimestamp(value) {
    if (!value) {
        return '—';
    }

    if (typeof value === 'string' && !value.includes('T') && Number.isNaN(Date.parse(value))) {
        return value;
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

export default function InvoiceDetail() {
    const { id: invoiceId } = useParams();
    const queryClient = useQueryClient();
    const [activeTab, setActiveTab] = useState('pos');
    const [retryOpen, setRetryOpen] = useState(false);

    const { data: invoice, isLoading, isError, error } = useQuery({
        queryKey: ['invoices', invoiceId],
        queryFn: async () => (await api.get(`/invoices/${invoiceId}`)).data.data,
        enabled: Boolean(invoiceId),
    });

    const retryMutation = useMutation({
        mutationFn: () => api.post(`/invoices/${invoiceId}/retry`),
        onSuccess: () => {
            setRetryOpen(false);
            queryClient.invalidateQueries({ queryKey: ['invoices', invoiceId] });
        },
    });

    useEffect(() => {
        if (!echoEnabled || !invoiceId) {
            return undefined;
        }

        let unsubscribe = () => {};

        import('../../echo').then(({ subscribeToChannel }) => {
            unsubscribe = subscribeToChannel(`invoices.${invoiceId}`, '.invoice.updated', (payload) => {
                queryClient.setQueryData(['invoices', invoiceId], (previous) => {
                    if (!previous) {
                        return previous;
                    }

                    return {
                        ...previous,
                        processing_status: payload.processing_status ?? previous.processing_status,
                        eis_status: payload.eis_status ?? previous.eis_status,
                        eis_reference_no: payload.eis_reference_no ?? previous.eis_reference_no,
                        updated_at: payload.updated_at ?? previous.updated_at,
                    };
                });

                queryClient.invalidateQueries({ queryKey: ['invoices', invoiceId] });
            });
        });

        return () => unsubscribe();
    }, [invoiceId, queryClient]);

    const logs = useMemo(() => normalizeLogs(invoice), [invoice]);
    const timeline = useMemo(
        () => (invoice ? buildTimeline(invoice, logs) : []),
        [invoice, logs],
    );

    const canRetry =
        invoice?.processing_status === 'rejected' || invoice?.processing_status === 'failed';

    if (isLoading) {
        return (
            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <p className="text-sm text-slate-500">Loading invoice…</p>
            </div>
        );
    }

    if (isError || !invoice) {
        return (
            <div className="rounded-lg border border-red-200 bg-red-50 p-6">
                <p className="text-sm font-medium text-red-800">Failed to load invoice</p>
                <p className="mt-1 text-sm text-red-600">
                    {error?.response?.data?.message ?? error?.friendlyMessage ?? 'Invoice not found or access denied.'}
                </p>
                <Link to="/invoices" className="mt-4 inline-block text-sm text-slate-600 hover:text-slate-800">
                    ← Back to invoices
                </Link>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link to="/invoices" className="text-sm text-slate-500 hover:text-slate-700">
                        ← Back to invoices
                    </Link>
                    <h1 className="mt-2 font-mono text-xl font-semibold text-slate-900">
                        {invoice.bridge_transaction_id}
                    </h1>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <StatusBadge status={invoice.processing_status} label={`Processing: ${invoice.processing_status}`} />
                        <StatusBadge
                            status={invoice.eis_status ?? 'pending'}
                            label={`EIS: ${invoice.eis_status ?? 'pending'}`}
                        />
                    </div>
                    <dl className="mt-3 grid gap-1 text-sm text-slate-600 sm:grid-cols-2">
                        <div>
                            <span className="text-slate-500">POS TX ID:</span> {invoice.transaction_id}
                        </div>
                        <div>
                            <span className="text-slate-500">Merchant:</span> {invoice.merchant_code}
                        </div>
                        <div>
                            <span className="text-slate-500">Branch:</span> {invoice.branch_code}
                        </div>
                        <div>
                            <span className="text-slate-500">Device:</span> {invoice.pos_device_id}
                        </div>
                        {invoice.eis_reference_no && (
                            <div className="sm:col-span-2">
                                <span className="text-slate-500">EIS reference:</span> {invoice.eis_reference_no}
                            </div>
                        )}
                    </dl>
                </div>

                {canRetry && (
                    <button
                        type="button"
                        onClick={() => setRetryOpen(true)}
                        className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        Retry
                    </button>
                )}
            </div>

            <section className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Processing timeline
                </h2>
                <ol className="relative border-l border-slate-200 pl-6">
                    {timeline.map((step) => (
                        <li key={step.label} className="mb-6 last:mb-0">
                            <span
                                className={`absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-white ${
                                    step.done && !step.muted ? 'bg-green-500' : step.muted ? 'bg-slate-300' : 'bg-slate-400'
                                }`}
                            />
                            <p className="text-sm font-medium text-slate-800">{step.label}</p>
                            <p className="text-sm text-slate-500">
                                {step.isStatus ? (
                                    <StatusBadge status={step.timestamp} />
                                ) : (
                                    formatTimestamp(step.timestamp)
                                )}
                            </p>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="rounded-lg border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-4">
                    <nav className="-mb-px flex gap-4 overflow-x-auto">
                        {TABS.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
                                className={`whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium ${
                                    activeTab === tab.key
                                        ? 'border-blue-600 text-blue-600'
                                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </nav>
                </div>

                <div className="p-6">
                    {activeTab === 'pos' && <JsonBlock data={invoice.raw_pos_json} />}
                    {activeTab === 'bir' && <JsonBlock data={invoice.bir_json} />}
                    {activeTab === 'signed' && <JsonBlock data={maskSensitiveJson(invoice.signed_json)} />}
                    {activeTab === 'logs' &&
                        (logs.length > 0 ? (
                            <ul className="space-y-4">
                                {logs.map((log) => (
                                    <li
                                        key={log.id ?? `${log.event}-${log.timestamp}`}
                                        className="rounded-lg border border-slate-200 p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium text-slate-800">{log.event}</span>
                                            <span className="text-sm text-slate-500">{formatTimestamp(log.timestamp)}</span>
                                        </div>
                                        {log.metadata && (
                                            <pre className="mt-3 max-h-48 overflow-auto rounded bg-slate-900 p-3 text-xs text-slate-100">
                                                {JSON.stringify(log.metadata, null, 2)}
                                            </pre>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-slate-500">No transmission logs.</p>
                        ))}
                </div>
            </section>

            <InvoiceRetryModal
                open={retryOpen}
                invoiceId={invoice.bridge_transaction_id ?? invoiceId}
                loading={retryMutation.isPending}
                onCancel={() => setRetryOpen(false)}
                onConfirm={() => retryMutation.mutate()}
            />
        </div>
    );
}
