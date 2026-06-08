import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Bar, Line, Pie } from 'react-chartjs-2';
import { Link, useSearchParams } from 'react-router-dom';
import ChartCard from '../../components/analytics/ChartCard';
import KpiCard from '../../components/analytics/KpiCard';
import { toastError } from '../../components/Toast';
import '../../components/analytics/chartSetup';
import { useRealtimeAnalytics } from '../../hooks/useRealtimeAnalytics';
import { merchantService } from '../../services/merchantService';

const CERT_STATUS_LABELS = {
    valid: 'Valid',
    expiring_30: 'Expiring within 30 days',
    expiring_7: 'Expiring within 7 days',
    expired: 'Expired',
    missing: 'Missing certificate',
};

export default function MerchantAnalytics() {
    const [searchParams] = useSearchParams();
    const initialMerchantId = searchParams.get('merchant') ?? '';

    const [merchantId, setMerchantId] = useState(initialMerchantId);
    const [range, setRange] = useState('7d');
    const [data, setData] = useState(null);

    const { data: merchants = [], isLoading: merchantsLoading } = useQuery({
        queryKey: ['merchants', 'analytics-selector'],
        queryFn: async () => {
            const response = await merchantService.list({ per_page: 100 });
            return response.data?.data ?? response.data ?? [];
        },
    });

    useEffect(() => {
        if (initialMerchantId) {
            setMerchantId(initialMerchantId);
        }
    }, [initialMerchantId]);

    const {
        data: fetchedData,
        isLoading,
        isError,
        refetch,
    } = useQuery({
        queryKey: ['merchants', 'analytics', merchantId, range],
        queryFn: async () => (await merchantService.getAnalytics(merchantId, range)).data.data,
        enabled: Boolean(merchantId),
    });

    useEffect(() => {
        setData(null);
    }, [merchantId, range]);

    useEffect(() => {
        if (fetchedData) {
            setData(fetchedData);
        }
    }, [fetchedData]);

    useRealtimeAnalytics({
        data,
        setData,
        filters: { merchantId },
        onRetryFailed: () => toastError('An invoice entered retry_failed status.'),
    });

    const selectedMerchantName = useMemo(() => {
        const match = merchants.find((merchant) => String(merchant.id) === String(merchantId));
        return match?.name ?? '';
    }, [merchants, merchantId]);

    if (!merchantId) {
        return (
            <div className="space-y-6">
                <Header />
                <div className="rounded-lg border bg-white p-4">
                    {merchantsLoading ? (
                        <p className="text-sm text-slate-500">Loading merchants…</p>
                    ) : (
                        <select
                            className="w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm"
                            value={merchantId}
                            onChange={(e) => setMerchantId(e.target.value)}
                        >
                            <option value="">Select merchant</option>
                            {merchants.map((merchant) => (
                                <option key={merchant.id} value={merchant.id}>
                                    {merchant.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>
                <p className="text-sm text-slate-500">Select a merchant to view analytics.</p>
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
                <p className="text-sm text-red-600">Failed to load merchant analytics. Please try again.</p>
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
    const eisBreakdown = data.eis_breakdown ?? {};

    return (
        <div className="space-y-6">
            <Header />

            <div className="flex flex-wrap items-center gap-3 rounded-lg border bg-white p-4">
                <select
                    className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                    value={merchantId}
                    onChange={(e) => setMerchantId(e.target.value)}
                >
                    <option value="">Select merchant</option>
                    {merchants.map((merchant) => (
                        <option key={merchant.id} value={merchant.id}>
                            {merchant.name}
                        </option>
                    ))}
                </select>

                {selectedMerchantName && (
                    <div className="text-sm text-slate-500">{selectedMerchantName}</div>
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

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                <KpiCard label="Total Invoices" value={data.kpi.total} />
                <KpiCard label="Acknowledged" value={data.kpi.ack} />
                <KpiCard label="Rejected" value={data.kpi.rejected} />
                <KpiCard label="Retry Failed" value={data.kpi.retry_failed} />
                <KpiCard label="Error Rate" value={`${data.kpi.error_rate}%`} />
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <KpiCard label="Retry Failed (pressure)" value={data.retry_pressure.retry_failed} />
                <KpiCard label="Transmission Failed" value={data.retry_pressure.transmission_failed} />
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
                <ChartCard title="EIS Status Breakdown">
                    <Pie
                        data={{
                            labels: ['Acknowledged', 'Rejected', 'Pending'],
                            datasets: [
                                {
                                    data: [eisBreakdown.ack ?? 0, eisBreakdown.rejected ?? 0, eisBreakdown.pending ?? 0],
                                    backgroundColor: ['#22c55e', '#ef4444', '#eab308'],
                                },
                            ],
                        }}
                        options={{
                            responsive: true,
                            plugins: { legend: { position: 'bottom' } },
                        }}
                    />
                </ChartCard>

                <ChartCard title="Certificate Health">
                    <div className="rounded-lg border bg-slate-50 p-4 text-center">
                        <div className="text-xs uppercase tracking-wide text-slate-500">Status</div>
                        <div className="mt-1 text-lg font-semibold text-slate-900">
                            {CERT_STATUS_LABELS[certHealth.status] ?? certHealth.status ?? '—'}
                        </div>
                    </div>
                </ChartCard>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <ChartCard title="Branch Volume">
                    {data.branch_volume.length === 0 ? (
                        <p className="text-sm text-slate-500">No branch volume in this period.</p>
                    ) : (
                        <Bar
                            data={{
                                labels: data.branch_volume.map((branch) => branch.name),
                                datasets: [
                                    {
                                        label: 'Invoices',
                                        data: data.branch_volume.map((branch) => branch.count),
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
                    )}
                </ChartCard>

                <ChartCard title="Top Devices by Volume">
                    {data.device_volume.length === 0 ? (
                        <p className="text-sm text-slate-500">No device volume in this period.</p>
                    ) : (
                        <Bar
                            data={{
                                labels: data.device_volume.map((device) => device.pos_device_id),
                                datasets: [
                                    {
                                        label: 'Invoices',
                                        data: data.device_volume.map((device) => device.count),
                                        backgroundColor: '#0ea5e9',
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
                    )}
                </ChartCard>
            </div>

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
                <h1 className="text-xl font-semibold text-slate-900">Merchant Analytics</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Branch and device volume, EIS outcomes, certificate health, and error patterns.
                </p>
            </div>
            <Link to="/merchants" className="text-sm text-slate-600 hover:text-slate-800">
                ← Back to merchants
            </Link>
        </div>
    );
}
