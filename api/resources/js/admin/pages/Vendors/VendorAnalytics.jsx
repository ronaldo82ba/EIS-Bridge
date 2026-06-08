import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Bar, Line, Pie } from 'react-chartjs-2';
import { Link } from 'react-router-dom';
import ChartCard from '../../components/analytics/ChartCard';
import KpiCard from '../../components/analytics/KpiCard';
import { toastError } from '../../components/Toast';
import '../../components/analytics/chartSetup';
import { useRealtimeAnalytics } from '../../hooks/useRealtimeAnalytics';
import { useAuthStore } from '../../store/authStore';
import { vendorService } from '../../services/vendorService';

export default function VendorAnalytics() {
    const user = useAuthStore((state) => state.user);
    const isVendorAdmin = user?.role === 'vendor_admin';
    const ownVendorId = isVendorAdmin ? String(user.vendor_id ?? '') : '';

    const [vendorId, setVendorId] = useState(ownVendorId);
    const [range, setRange] = useState('30d');
    const [data, setData] = useState(null);

    const { data: vendors = [], isLoading: vendorsLoading } = useQuery({
        queryKey: ['vendors', 'analytics-selector'],
        queryFn: async () => {
            const response = await vendorService.list({ per_page: 100 });
            return response.data?.data ?? response.data ?? [];
        },
        enabled: !isVendorAdmin,
    });

    useEffect(() => {
        if (isVendorAdmin && ownVendorId) {
            setVendorId(ownVendorId);
        }
    }, [isVendorAdmin, ownVendorId]);

    const {
        data: fetchedData,
        isLoading,
        isError,
        refetch,
    } = useQuery({
        queryKey: ['vendors', 'analytics', vendorId, range],
        queryFn: async () => (await vendorService.getAnalytics(vendorId, range)).data.data,
        enabled: Boolean(vendorId),
    });

    useEffect(() => {
        setData(null);
    }, [vendorId, range]);

    useEffect(() => {
        if (fetchedData) {
            setData(fetchedData);
        }
    }, [fetchedData]);

    useRealtimeAnalytics({
        data,
        setData,
        filters: { vendorId },
        onRetryFailed: () => toastError('An invoice entered retry_failed status.'),
    });

    const selectedVendorName = useMemo(() => {
        if (isVendorAdmin) {
            return user?.vendor?.name ?? 'Your vendor';
        }

        const match = vendors.find((vendor) => String(vendor.id) === String(vendorId));
        return match?.name ?? '';
    }, [isVendorAdmin, user, vendors, vendorId]);

    if (!vendorId && !isVendorAdmin) {
        return (
            <div className="space-y-6">
                <Header />
                <div className="rounded-lg border bg-white p-4">
                    {vendorsLoading ? (
                        <p className="text-sm text-slate-500">Loading vendors…</p>
                    ) : (
                        <select
                            className="w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm"
                            value={vendorId}
                            onChange={(e) => setVendorId(e.target.value)}
                        >
                            <option value="">Select vendor</option>
                            {vendors.map((vendor) => (
                                <option key={vendor.id} value={vendor.id}>
                                    {vendor.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>
                <p className="text-sm text-slate-500">Select a vendor to view analytics.</p>
            </div>
        );
    }

    if (isLoading) {
        return (
            <div className="flex items-center justify-center p-12">
                <p className="text-sm text-slate-500">Loading…</p>
            </div>
        );
    }

    if (isError || !data) {
        return (
            <div className="space-y-4">
                <Header />
                <p className="text-sm text-red-600">Failed to load vendor analytics. Please try again.</p>
                <button
                    type="button"
                    onClick={() => refetch()}
                    className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                >
                    Retry
                </button>
            </div>
        );
    }

    const certHealth = data.certificate_health ?? {};

    return (
        <div className="space-y-6">
            <Header />

            <div className="flex flex-wrap items-center gap-3 rounded-lg border bg-white p-4">
                {!isVendorAdmin ? (
                    <select
                        className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        value={vendorId}
                        onChange={(e) => setVendorId(e.target.value)}
                    >
                        <option value="">Select vendor</option>
                        {vendors.map((vendor) => (
                            <option key={vendor.id} value={vendor.id}>
                                {vendor.name}
                            </option>
                        ))}
                    </select>
                ) : (
                    <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        {selectedVendorName}
                    </div>
                )}

                <select
                    className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={range}
                    onChange={(e) => setRange(e.target.value)}
                >
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                    <option value="90d">Last 90 Days</option>
                </select>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <KpiCard label="Total Invoices" value={data.kpi.total} />
                <KpiCard label="Acknowledged" value={data.kpi.ack} />
                <KpiCard label="Rejected" value={data.kpi.rejected} />
                <KpiCard label="Webhook Failures" value={data.kpi.webhook_failures} />
                <KpiCard label="Error Rate" value={`${data.kpi.error_rate}%`} />
                <KpiCard label="EIS Ack Rate" value={`${data.kpi.eis_ack_rate}%`} />
            </div>

            <ChartCard title="Daily Invoice Volume">
                <Line
                    data={{
                        labels: data.daily.labels,
                        datasets: [
                            {
                                label: 'Invoices',
                                data: data.daily.values,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.2)',
                                fill: true,
                                tension: 0.3,
                            },
                        ],
                    }}
                    options={{
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: { legend: { display: false } },
                    }}
                />
            </ChartCard>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <ChartCard title="Top Merchants by Volume">
                    <Bar
                        data={{
                            labels: data.top_merchants.map((merchant) => merchant.name),
                            datasets: [
                                {
                                    label: 'Invoices',
                                    data: data.top_merchants.map((merchant) => merchant.count),
                                    backgroundColor: '#6366f1',
                                },
                            ],
                        }}
                        options={{
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { ticks: { maxRotation: 45, minRotation: 0 } },
                            },
                        }}
                    />
                </ChartCard>

                <ChartCard title="Webhook Delivery Success Rate">
                    <Pie
                        data={{
                            labels: ['Success', 'Failed'],
                            datasets: [
                                {
                                    data: [data.webhooks.success, data.webhooks.failed],
                                    backgroundColor: ['#22c55e', '#ef4444'],
                                },
                            ],
                        }}
                        options={{
                            responsive: true,
                            plugins: { legend: { position: 'bottom' } },
                        }}
                    />
                    <p className="mt-3 text-sm text-slate-500">
                        Success rate: {data.webhooks.success_rate}%
                    </p>
                </ChartCard>
            </div>

            <ChartCard title="Certificate Health">
                <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                    <CertStat label="Valid" value={certHealth.valid ?? 0} accent="text-emerald-700" />
                    <CertStat label="Expiring (30d)" value={certHealth.expiring_30 ?? 0} accent="text-amber-700" />
                    <CertStat label="Expiring (7d)" value={certHealth.expiring_7 ?? 0} accent="text-orange-700" />
                    <CertStat label="Expired" value={certHealth.expired ?? 0} accent="text-red-700" />
                    <CertStat label="Missing" value={certHealth.missing ?? 0} accent="text-slate-700" />
                </div>
                <Bar
                    className="mt-6"
                    data={{
                        labels: ['Valid', 'Expiring 30d', 'Expiring 7d', 'Expired', 'Missing'],
                        datasets: [
                            {
                                label: 'Merchants',
                                data: [
                                    certHealth.valid ?? 0,
                                    certHealth.expiring_30 ?? 0,
                                    certHealth.expiring_7 ?? 0,
                                    certHealth.expired ?? 0,
                                    certHealth.missing ?? 0,
                                ],
                                backgroundColor: ['#22c55e', '#eab308', '#f97316', '#ef4444', '#94a3b8'],
                            },
                        ],
                    }}
                    options={{
                        responsive: true,
                        plugins: { legend: { display: false } },
                    }}
                />
            </ChartCard>

            <div className="rounded-lg border bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-900">Error Breakdown</h2>

                {data.errors.length === 0 ? (
                    <p className="text-sm text-slate-500">No errors recorded in this period.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-slate-50">
                                <th className="px-3 py-2 text-left">Error</th>
                                <th className="px-3 py-2 text-left">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.errors.map((entry) => (
                                <tr key={entry.error} className="border-b">
                                    <td className="px-3 py-2">{entry.error}</td>
                                    <td className="px-3 py-2">{entry.count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

function Header() {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 className="text-xl font-semibold text-slate-900">Vendor Analytics</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Merchant performance, EIS acknowledgment, webhooks, and certificate health.
                </p>
            </div>
            <Link to="/vendors" className="text-sm text-slate-600 hover:text-slate-800">
                ← Back to vendors
            </Link>
        </div>
    );
}

function CertStat({ label, value, accent }) {
    return (
        <div className="rounded-lg border bg-slate-50 p-4 text-center">
            <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold ${accent}`}>{value}</div>
        </div>
    );
}
