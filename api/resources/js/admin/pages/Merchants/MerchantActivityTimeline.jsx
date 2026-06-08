import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Pagination from '../../components/Pagination';
import StatusBadge from '../../components/StatusBadge';
import { merchantService } from '../../services/merchantService';

const TYPE_FILTERS = [
    { value: 'all', label: 'All events' },
    { value: 'transaction', label: 'Transactions' },
    { value: 'mapping', label: 'Mapping' },
    { value: 'signing', label: 'Signing' },
    { value: 'transmission', label: 'Transmission' },
    { value: 'retry', label: 'Retries' },
    { value: 'webhook', label: 'Webhooks' },
    { value: 'certificate', label: 'Certificates' },
];

const EVENT_LABELS = {
    transaction_received: 'Transaction received',
    mapping_completed: 'Mapping completed',
    signing_completed: 'Signing completed',
    transmission_attempt: 'Transmission attempt',
    eis_acknowledged: 'EIS acknowledged',
    eis_rejected: 'EIS rejected',
    retry_scheduled: 'Retry scheduled',
    webhook_delivery: 'Webhook delivery',
    certificate_alert: 'Certificate alert',
};

const echoEnabled = Boolean(import.meta.env.VITE_PUSHER_APP_KEY);

const EVENT_ACCENTS = {
    transaction_received: 'border-blue-200 bg-blue-50 text-blue-800',
    mapping_completed: 'border-indigo-200 bg-indigo-50 text-indigo-800',
    signing_completed: 'border-violet-200 bg-violet-50 text-violet-800',
    transmission_attempt: 'border-amber-200 bg-amber-50 text-amber-800',
    eis_acknowledged: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    eis_rejected: 'border-rose-200 bg-rose-50 text-rose-800',
    retry_scheduled: 'border-orange-200 bg-orange-50 text-orange-800',
    webhook_delivery: 'border-cyan-200 bg-cyan-50 text-cyan-800',
    certificate_alert: 'border-yellow-200 bg-yellow-50 text-yellow-800',
};

function formatTimestamp(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function summarizeDetails(event) {
    const details = event.details ?? {};

    switch (event.type) {
        case 'transaction_received':
            return details.bridge_transaction_id ?? details.transaction_id ?? 'POS transaction ingested';
        case 'mapping_completed':
            return details.source_event === 'mapped'
                ? 'POS payload mapped to BIR JSON'
                : `Mapping event: ${details.source_event}`;
        case 'signing_completed':
            return details.source_event === 'signed'
                ? 'Invoice signed with merchant certificate'
                : `Signing event: ${details.source_event}`;
        case 'transmission_attempt':
            return details.metadata?.endpoint
                ? `Attempt to ${details.metadata.endpoint}`
                : 'EIS transmission attempt';
        case 'eis_acknowledged':
            return details.metadata?.eis_reference_no
                ? `Reference ${details.metadata.eis_reference_no}`
                : 'Transmission acknowledged by EIS';
        case 'eis_rejected':
            return details.metadata?.error ?? details.metadata?.eis_status ?? 'EIS rejected transmission';
        case 'retry_scheduled':
            return details.metadata?.message ?? 'Retry attempt recorded';
        case 'webhook_delivery':
            return `${details.event ?? 'webhook'} · HTTP ${details.status_code ?? '—'} · ${details.success ? 'success' : 'failed'}`;
        case 'certificate_alert':
            return `Certificate ${details.level ?? 'alert'}`;
        default:
            return JSON.stringify(details);
    }
}

export default function MerchantActivityTimeline() {
    const queryClient = useQueryClient();
    const { id: merchantId } = useParams();
    const [type, setType] = useState('all');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');

    const queryParams = useMemo(
        () => ({
            type,
            page,
            per_page: perPage,
            ...(from ? { from } : {}),
            ...(to ? { to } : {}),
        }),
        [type, page, perPage, from, to],
    );

    const { data: merchant, isLoading: merchantLoading } = useQuery({
        queryKey: ['merchants', merchantId],
        queryFn: () => merchantService.getData(merchantId),
        enabled: Boolean(merchantId),
    });

    const {
        data: activity,
        isLoading: activityLoading,
        isError: activityError,
    } = useQuery({
        queryKey: ['merchants', merchantId, 'activity', queryParams],
        queryFn: () => merchantService.getActivity(merchantId, queryParams),
        enabled: Boolean(merchantId),
    });

    const events = activity?.data ?? [];

    useEffect(() => {
        if (!echoEnabled || !merchantId || page !== 1) {
            return undefined;
        }

        let unsubscribe = () => {};

        import('../../echo').then(({ subscribeToChannel }) => {
            unsubscribe = subscribeToChannel(
                `merchants.${merchantId}.activity`,
                '.merchant.activity',
                (payload) => {
                    const event = payload?.event;

                    if (!event) {
                        return;
                    }

                    if (type !== 'all') {
                        const allowed = {
                            transaction: ['transaction_received'],
                            mapping: ['mapping_completed'],
                            signing: ['signing_completed'],
                            transmission: ['transmission_attempt', 'eis_acknowledged', 'eis_rejected'],
                            retry: ['retry_scheduled'],
                            webhook: ['webhook_delivery'],
                            certificate: ['certificate_alert'],
                        }[type] ?? [];

                        if (!allowed.includes(event.type)) {
                            return;
                        }
                    }

                    queryClient.setQueryData(
                        ['merchants', merchantId, 'activity', queryParams],
                        (previous) => {
                            if (!previous?.data) {
                                return previous;
                            }

                            const key = `${event.type}-${event.created_at}-${event.invoice_id ?? ''}`;

                            if (
                                previous.data.some(
                                    (item) =>
                                        `${item.type}-${item.created_at}-${item.invoice_id ?? ''}` === key,
                                )
                            ) {
                                return previous;
                            }

                            return {
                                ...previous,
                                data: [event, ...previous.data].slice(0, perPage),
                                total: (previous.total ?? 0) + 1,
                            };
                        },
                    );
                },
            );
        });

        return () => unsubscribe();
    }, [merchantId, page, perPage, queryClient, queryParams, type]);

    const applyFilters = (event) => {
        event.preventDefault();
        setPage(1);
    };

    if (merchantLoading) {
        return <div className="text-sm text-slate-500">Loading merchant…</div>;
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-800">Activity timeline</h1>
                    <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                        {merchant?.name && <span>{merchant.name}</span>}
                        {merchant?.merchant_code && <span>Code: {merchant.merchant_code}</span>}
                        {merchant?.status && <StatusBadge status={merchant.status} />}
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    <Link to={`/merchants/${merchantId}`} className="text-sm text-blue-600 hover:text-blue-800">
                        Merchant dashboard
                    </Link>
                    <Link to="/merchants" className="text-sm text-slate-500 hover:text-slate-700">
                        Back to list
                    </Link>
                </div>
            </div>

            <form
                onSubmit={applyFilters}
                className="rounded-lg border border-slate-200 bg-white p-4"
            >
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <label className="block text-sm">
                        <span className="mb-1 block text-slate-600">Event type</span>
                        <select
                            value={type}
                            onChange={(event) => {
                                setType(event.target.value);
                                setPage(1);
                            }}
                            className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                        >
                            {TYPE_FILTERS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1 block text-slate-600">From</span>
                        <input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1 block text-slate-600">To</span>
                        <input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                        />
                    </label>

                    <div className="flex items-end">
                        <button type="submit" className="btn-secondary">
                            Apply filters
                        </button>
                    </div>
                </div>
            </form>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                {activityLoading ? (
                    <p className="text-sm text-slate-500">Loading activity…</p>
                ) : activityError ? (
                    <p className="text-sm text-red-600">Failed to load activity timeline.</p>
                ) : events.length === 0 ? (
                    <p className="text-sm text-slate-500">No activity recorded for this merchant yet.</p>
                ) : (
                    <ol className="relative space-y-0 border-l border-slate-200 pl-6">
                        {events.map((event, index) => (
                            <li key={`${event.type}-${event.created_at}-${event.invoice_id ?? index}`} className="mb-8 last:mb-0">
                                <span className="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-white bg-slate-400" />
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <span
                                            className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${
                                                EVENT_ACCENTS[event.type] ?? 'border-slate-200 bg-slate-50 text-slate-700'
                                            }`}
                                        >
                                            {EVENT_LABELS[event.type] ?? event.type}
                                        </span>
                                        <p className="mt-2 text-sm text-slate-700">{summarizeDetails(event)}</p>
                                        {event.invoice_id && (
                                            <Link
                                                to={`/invoices/${event.invoice_id}`}
                                                className="mt-1 inline-block text-sm text-blue-600 hover:text-blue-800 hover:underline"
                                            >
                                                View invoice #{event.invoice_id}
                                            </Link>
                                        )}
                                    </div>
                                    <time className="shrink-0 text-xs text-slate-500">
                                        {formatTimestamp(event.created_at)}
                                    </time>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}

                <Pagination
                    current={activity?.current_page ?? page}
                    pageSize={perPage}
                    total={activity?.total ?? 0}
                    lastPage={activity?.last_page}
                    onChange={(nextPage, nextSize) => {
                        setPage(nextPage);
                        setPerPage(nextSize);
                    }}
                    showSizeChanger
                />
            </div>
        </div>
    );
}
