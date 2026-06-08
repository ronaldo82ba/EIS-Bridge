import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Bar, Line, Pie } from 'react-chartjs-2';
import { Link } from 'react-router-dom';
import { toastError } from '../../components/Toast';
import { useRealtimeAnalytics } from '../../hooks/useRealtimeAnalytics';
import { invoiceService } from '../../services/invoiceService';
import './chartSetup';

export default function InvoiceAnalytics() {
    const [range, setRange] = useState('7d');
    const [data, setData] = useState(null);

    const { data: fetchedData, isLoading, isError, refetch } = useQuery({
        queryKey: ['invoices', 'analytics', range],
        queryFn: async () => (await invoiceService.getAnalytics(range)).data.data,
    });

    useEffect(() => {
        setData(null);
    }, [range]);

    useEffect(() => {
        if (fetchedData) {
            setData(fetchedData);
        }
    }, [fetchedData]);

    useRealtimeAnalytics({
        data,
        setData,
        onRetryFailed: () => toastError('An invoice entered retry_failed status.'),
    });

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
                <h1 className="text-xl font-semibold text-slate-900">Invoice Analytics</h1>
                <p className="text-sm text-red-600">Failed to load analytics. Please try again.</p>
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

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-slate-900">Invoice Analytics</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Volume trends, merchant performance, EIS acknowledgment, and error patterns.
                    </p>
                </div>
                <Link to="/invoices" className="text-sm text-slate-600 hover:text-slate-800">
                    ← Back to invoices
                </Link>
            </div>

            <div className="flex gap-3 rounded-lg border bg-white p-4">
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
                <KPI label="Total Invoices" value={data.kpi.total} />
                <KPI label="Acknowledged" value={data.kpi.ack} />
                <KPI label="Rejected" value={data.kpi.rejected} />
                <KPI label="Error Rate" value={`${data.kpi.error_rate}%`} />
                <KPI label="EIS Ack Rate" value={`${data.eis_ack_rate}%`} />
            </div>

            {data.kpi.avg_latency_ms != null && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <KPI label="Avg Transmission Latency" value={`${data.kpi.avg_latency_ms} ms`} />
                    <KPI label="Retry Failed" value={data.retry_pressure.retry_failed} />
                    <KPI label="Transmission Failed" value={data.retry_pressure.transmission_failed} />
                </div>
            )}

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
                <ChartCard title="Status Breakdown">
                    <Pie
                        data={{
                            labels: Object.keys(data.status_breakdown),
                            datasets: [
                                {
                                    data: Object.values(data.status_breakdown),
                                    backgroundColor: ['#22c55e', '#2563eb', '#eab308', '#ef4444'],
                                },
                            ],
                        }}
                        options={{
                            responsive: true,
                            plugins: { legend: { position: 'bottom' } },
                        }}
                    />
                </ChartCard>

                <ChartCard title="Top Merchants by Volume">
                    <Bar
                        data={{
                            labels: data.top_merchants.map((m) => m.name),
                            datasets: [
                                {
                                    label: 'Invoices',
                                    data: data.top_merchants.map((m) => m.count),
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
                            {data.errors.map((e) => (
                                <tr key={e.error} className="border-b">
                                    <td className="px-3 py-2">{e.error}</td>
                                    <td className="px-3 py-2">{e.count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

function KPI({ label, value }) {
    return (
        <div className="rounded-lg border bg-slate-50 p-4">
            <div className="mb-1 text-xs uppercase tracking-wide text-slate-500">{label}</div>
            <div className="text-2xl font-semibold text-slate-900">{value}</div>
        </div>
    );
}

function ChartCard({ title, children }) {
    return (
        <div className="rounded-lg border bg-white p-6">
            <h2 className="mb-3 font-medium text-slate-900">{title}</h2>
            {children}
        </div>
    );
}
