import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../../api';
import Pagination from '../../components/Pagination';

const echoEnabled = Boolean(import.meta.env.VITE_PUSHER_APP_KEY);

const typeColor = (type) =>
    ({
        processing: 'bg-amber-100 text-amber-800',
        eis: 'bg-rose-100 text-rose-800',
        certificate: 'bg-indigo-100 text-indigo-800',
        webhook: 'bg-sky-100 text-sky-800',
        system: 'bg-slate-100 text-slate-800',
    }[type] || 'bg-slate-100 text-slate-800');

function alertMatchesFilters(alert, filters) {
    if (filters.type && filters.type !== 'all' && alert.type !== filters.type) {
        return false;
    }

    if (filters.status === 'open' && alert.status !== 'open') {
        return false;
    }

    if (filters.status === 'resolved' && alert.status !== 'resolved') {
        return false;
    }

    if (filters.merchant_id && alert.merchant?.id !== filters.merchant_id) {
        return false;
    }

    if (filters.vendor_id && alert.vendor?.id !== filters.vendor_id) {
        return false;
    }

    return true;
}

export default function AlertsCenter() {
    const queryClient = useQueryClient();
    const [filters, setFilters] = useState({ type: 'all', status: 'open' });
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);
    const [resolvingId, setResolvingId] = useState(null);

    const { data: merchantsData } = useQuery({
        queryKey: ['merchants', 'options'],
        queryFn: async () => (await api.get('/merchants', { params: { per_page: 200 } })).data,
    });

    const { data: vendorsData } = useQuery({
        queryKey: ['vendors', 'options'],
        queryFn: async () => (await api.get('/vendors', { params: { per_page: 200 } })).data,
    });

    const { data, isLoading, refetch, isFetching } = useQuery({
        queryKey: ['alerts', 'center', filters, page, perPage],
        queryFn: async () => {
            const params = {
                page,
                per_page: perPage,
            };

            if (filters.type && filters.type !== 'all') {
                params.type = filters.type;
            }

            if (filters.status && filters.status !== 'all') {
                params.status = filters.status;
            }

            if (filters.merchant_id) {
                params.merchant_id = filters.merchant_id;
            }

            if (filters.vendor_id) {
                params.vendor_id = filters.vendor_id;
            }

            return (await api.get('/alerts', { params })).data;
        },
        refetchInterval: 60_000,
    });

    useEffect(() => {
        setPage(1);
    }, [filters.type, filters.status, filters.merchant_id, filters.vendor_id]);

    useEffect(() => {
        if (!echoEnabled || page !== 1) {
            return undefined;
        }

        let unsubscribe = () => {};

        import('../../echo').then(({ subscribeToChannel }) => {
            unsubscribe = subscribeToChannel('alerts', '.alerts.created', (alert) => {
                if (!alertMatchesFilters(alert, filters)) {
                    return;
                }

                queryClient.setQueryData(['alerts', 'center', filters, page, perPage], (previous) => {
                    if (!previous?.data) {
                        return previous;
                    }

                    if (previous.data.some((item) => item.id === alert.id)) {
                        return previous;
                    }

                    return {
                        ...previous,
                        data: [alert, ...previous.data].slice(0, perPage),
                        total: (previous.total ?? 0) + 1,
                    };
                });
            });
        });

        return () => unsubscribe();
    }, [filters, page, perPage, queryClient]);

    const alerts = data?.data ?? [];
    const merchants = merchantsData?.data ?? [];
    const vendors = vendorsData?.data ?? [];

    const resolveAlert = async (id) => {
        setResolvingId(id);
        try {
            await api.post(`/alerts/${id}/resolve`);
            await refetch();
        } finally {
            setResolvingId(null);
        }
    };

    const loading = isLoading || isFetching;

    return (
        <div className="space-y-6">
            <h1 className="text-xl font-semibold">Global Alerts Center</h1>

            <div className="flex flex-wrap gap-4 rounded-lg border bg-white p-4">
                <select
                    className="rounded border border-slate-300 px-3 py-2 text-sm"
                    value={filters.type}
                    onChange={(e) => setFilters({ ...filters, type: e.target.value })}
                >
                    <option value="all">All Types</option>
                    <option value="processing">Processing</option>
                    <option value="eis">EIS</option>
                    <option value="certificate">Certificates</option>
                    <option value="webhook">Webhooks</option>
                    <option value="system">System</option>
                </select>

                <select
                    className="rounded border border-slate-300 px-3 py-2 text-sm"
                    value={filters.status}
                    onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                >
                    <option value="open">Open</option>
                    <option value="resolved">Resolved</option>
                    <option value="all">All</option>
                </select>

                <select
                    className="rounded border border-slate-300 px-3 py-2 text-sm"
                    value={filters.merchant_id ?? ''}
                    onChange={(e) =>
                        setFilters({
                            ...filters,
                            merchant_id: e.target.value ? Number(e.target.value) : undefined,
                        })
                    }
                >
                    <option value="">All Merchants</option>
                    {merchants.map((merchant) => (
                        <option key={merchant.id} value={merchant.id}>
                            {merchant.name}
                        </option>
                    ))}
                </select>

                <select
                    className="rounded border border-slate-300 px-3 py-2 text-sm"
                    value={filters.vendor_id ?? ''}
                    onChange={(e) =>
                        setFilters({
                            ...filters,
                            vendor_id: e.target.value ? Number(e.target.value) : undefined,
                        })
                    }
                >
                    <option value="">All Vendors</option>
                    {vendors.map((vendor) => (
                        <option key={vendor.id} value={vendor.id}>
                            {vendor.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="rounded-lg border bg-white p-6">
                {loading ? (
                    <div className="text-sm text-slate-500">Loading…</div>
                ) : alerts.length === 0 ? (
                    <div className="text-sm text-slate-500">No alerts</div>
                ) : (
                    <ul className="space-y-3">
                        {alerts.map((a) => (
                            <li key={a.id} className="rounded border bg-slate-50 p-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={`rounded px-2 py-0.5 text-xs ${typeColor(a.type)}`}
                                            >
                                                {a.type}
                                            </span>
                                            {a.severity && (
                                                <span className="text-xs uppercase text-slate-500">
                                                    {a.severity}
                                                </span>
                                            )}
                                            <span className="text-xs text-slate-500">{a.created_at}</span>
                                        </div>

                                        <div className="text-sm font-medium">{a.title}</div>

                                        {a.merchant && (
                                            <div className="text-xs text-slate-500">
                                                Merchant:{' '}
                                                <Link
                                                    to={`/merchants/${a.merchant.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {a.merchant.name}
                                                </Link>
                                            </div>
                                        )}

                                        {a.vendor && (
                                            <div className="text-xs text-slate-500">
                                                Vendor:{' '}
                                                <Link
                                                    to={`/vendors/${a.vendor.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {a.vendor.name}
                                                </Link>
                                            </div>
                                        )}

                                        {a.invoice && (
                                            <div className="text-xs text-slate-500">
                                                Invoice:{' '}
                                                <Link
                                                    to={`/invoices/${a.invoice.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {a.invoice.bridge_transaction_id}
                                                </Link>
                                            </div>
                                        )}

                                        {a.certificate && (
                                            <div className="text-xs text-slate-500">
                                                Certificate:{' '}
                                                <Link
                                                    to={`/certificates/${a.certificate.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    View
                                                </Link>
                                            </div>
                                        )}

                                        {a.type === 'system' && (
                                            <div className="text-xs text-slate-500">
                                                Queue:{' '}
                                                <Link
                                                    to="/monitoring/queues"
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    Queue Monitor
                                                </Link>
                                            </div>
                                        )}

                                        {a.details && Object.keys(a.details).length > 0 && (
                                            <pre className="mt-2 overflow-auto rounded bg-slate-900 p-2 text-xs text-slate-100">
                                                {JSON.stringify(a.details, null, 2)}
                                            </pre>
                                        )}
                                    </div>

                                    <div className="flex flex-col items-end gap-2">
                                        <span
                                            className={`rounded px-2 py-0.5 text-xs ${
                                                a.status === 'open'
                                                    ? 'bg-rose-100 text-rose-700'
                                                    : 'bg-emerald-100 text-emerald-700'
                                            }`}
                                        >
                                            {a.status}
                                        </span>

                                        {a.status === 'open' && (
                                            <button
                                                type="button"
                                                className="rounded border px-3 py-1 text-xs hover:bg-slate-100 disabled:opacity-50"
                                                onClick={() => resolveAlert(a.id)}
                                                disabled={resolvingId === a.id}
                                            >
                                                Mark as Resolved
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Pagination
                current={data?.current_page ?? page}
                pageSize={data?.per_page ?? perPage}
                total={data?.total ?? 0}
                onChange={(nextPage, nextSize) => {
                    setPage(nextPage);
                    setPerPage(nextSize);
                }}
            />
        </div>
    );
}
