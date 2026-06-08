import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pie } from 'react-chartjs-2';
import { Link, useSearchParams } from 'react-router-dom';
import ChartCard from '../../components/analytics/ChartCard';
import { toastError } from '../../components/Toast';
import '../../components/analytics/chartSetup';
import { isEchoEnabled, subscribeToChannel } from '../../echo';
import { useAuthStore } from '../../store/authStore';
import { vendorService } from '../../services/vendorService';

const GRADE_STYLES = {
    healthy: {
        label: 'Healthy',
        scoreClass: 'text-emerald-600',
        ringClass: 'border-emerald-500 bg-emerald-50',
        badgeClass: 'bg-emerald-100 text-emerald-800',
    },
    at_risk: {
        label: 'At Risk',
        scoreClass: 'text-amber-600',
        ringClass: 'border-amber-500 bg-amber-50',
        badgeClass: 'bg-amber-100 text-amber-800',
    },
    critical: {
        label: 'Critical',
        scoreClass: 'text-red-600',
        ringClass: 'border-red-500 bg-red-50',
        badgeClass: 'bg-red-100 text-red-800',
    },
};

const TREND_LABELS = {
    up: { label: 'Trending up', className: 'text-emerald-600' },
    down: { label: 'Trending down', className: 'text-red-600' },
    stable: { label: 'Stable', className: 'text-slate-500' },
};

const PILLAR_LABELS = {
    eis_success_rate: 'EIS Success',
    error_rate: 'Error Rate',
    retry_pressure: 'Retry Pressure',
    certificate_health: 'Certificate Health',
    webhook_success: 'Webhook Success',
    merchant_coverage_health: 'Merchant Coverage',
};

const PILLAR_COLORS = ['#22c55e', '#ef4444', '#f97316', '#6366f1', '#0ea5e9', '#a855f7'];

function pillarContribution(key, value) {
    const weights = {
        eis_success_rate: 0.35,
        error_rate: 0.20,
        retry_pressure: 0.15,
        certificate_health: 0.15,
        webhook_success: 0.10,
        merchant_coverage_health: 0.05,
    };

    const weight = weights[key] ?? 0;
    const normalized = key === 'error_rate' || key === 'retry_pressure'
        ? 100 - value
        : value;

    return Math.round(normalized * weight);
}

export default function VendorHealth() {
    const user = useAuthStore((state) => state.user);
    const isVendorAdmin = user?.role === 'vendor_admin';
    const ownVendorId = isVendorAdmin ? String(user.vendor_id ?? '') : '';

    const [searchParams] = useSearchParams();
    const initialVendorId = searchParams.get('vendor') ?? ownVendorId;

    const [vendorId, setVendorId] = useState(initialVendorId);
    const [range, setRange] = useState('30d');

    const { data: vendors = [], isLoading: vendorsLoading } = useQuery({
        queryKey: ['vendors', 'health-selector'],
        queryFn: async () => {
            const response = await vendorService.list({ per_page: 100 });
            return response.data?.data ?? response.data ?? [];
        },
        enabled: !isVendorAdmin,
    });

    useEffect(() => {
        if (initialVendorId) {
            setVendorId(initialVendorId);
        }
    }, [initialVendorId]);

    useEffect(() => {
        if (isVendorAdmin && ownVendorId) {
            setVendorId(ownVendorId);
        }
    }, [isVendorAdmin, ownVendorId]);

    const {
        data,
        isLoading,
        isError,
        refetch,
    } = useQuery({
        queryKey: ['vendors', 'health', vendorId, range],
        queryFn: async () => (await vendorService.getHealth(vendorId, range)).data.data,
        enabled: Boolean(vendorId),
    });

    useEffect(() => {
        if (!isEchoEnabled() || !vendorId) {
            return undefined;
        }

        return subscribeToChannel('analytics', '.analytics.updated', (payload) => {
            if (String(payload.vendor_id) !== String(vendorId)) {
                return;
            }

            refetch();

            if (payload.processing_status === 'retry_failed') {
                toastError('An invoice entered retry_failed status.');
            }
        });
    }, [vendorId, refetch]);

    const selectedVendorName = useMemo(() => {
        if (isVendorAdmin) {
            return user?.vendor?.name ?? 'Your vendor';
        }

        const match = vendors.find((vendor) => String(vendor.id) === String(vendorId));
        return match?.name ?? '';
    }, [isVendorAdmin, user, vendors, vendorId]);

    const gradeStyle = GRADE_STYLES[data?.grade] ?? GRADE_STYLES.at_risk;
    const trend = TREND_LABELS[data?.trend] ?? TREND_LABELS.stable;

    const pillarChart = useMemo(() => {
        if (!data?.pillars) {
            return null;
        }

        const entries = Object.entries(data.pillars).map(([key, value]) => ({
            key,
            label: PILLAR_LABELS[key] ?? key,
            contribution: pillarContribution(key, value),
        }));

        return {
            labels: entries.map((entry) => entry.label),
            values: entries.map((entry) => entry.contribution),
        };
    }, [data]);

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
                <p className="text-sm text-slate-500">Select a vendor to view health score.</p>
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
                <p className="text-sm text-red-600">Failed to load vendor health score. Please try again.</p>
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

                {selectedVendorName && !isVendorAdmin && (
                    <div className="text-sm text-slate-500">{selectedVendorName}</div>
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

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className={`rounded-lg border-2 p-8 text-center ${gradeStyle.ringClass}`}>
                    <div className="text-xs uppercase tracking-wide text-slate-500">Health Score</div>
                    <div className={`mt-2 text-5xl font-bold ${gradeStyle.scoreClass}`}>{data.score}</div>
                    <div className={`mt-3 inline-block rounded-full px-3 py-1 text-sm font-medium ${gradeStyle.badgeClass}`}>
                        {gradeStyle.label}
                    </div>
                    <div className={`mt-3 text-sm ${trend.className}`}>{trend.label}</div>
                    <div className="mt-4 space-y-1 text-sm text-slate-600">
                        <div>{data.merchant_count ?? 0} merchants</div>
                        {(data.at_risk_merchants ?? 0) > 0 && (
                            <div className="text-amber-700">
                                {data.at_risk_merchants} at risk
                            </div>
                        )}
                    </div>
                </div>

                <div className="lg:col-span-2">
                    <ChartCard title="Score Contribution by Pillar">
                        {pillarChart ? (
                            <Pie
                                data={{
                                    labels: pillarChart.labels,
                                    datasets: [
                                        {
                                            data: pillarChart.values,
                                            backgroundColor: PILLAR_COLORS,
                                        },
                                    ],
                                }}
                                options={{
                                    responsive: true,
                                    plugins: { legend: { position: 'bottom' } },
                                }}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">No pillar data available.</p>
                        )}
                    </ChartCard>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {Object.entries(data.pillars ?? {}).map(([key, value]) => (
                    <div key={key} className="rounded-lg border bg-white p-4">
                        <div className="text-xs uppercase tracking-wide text-slate-500">
                            {PILLAR_LABELS[key] ?? key}
                        </div>
                        <div className="mt-1 text-2xl font-semibold text-slate-900">{value}%</div>
                        <div className="mt-1 text-xs text-slate-500">
                            Weighted contribution: {pillarContribution(key, value)} pts
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Header() {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 className="text-xl font-semibold text-slate-900">Vendor Health</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Composite health score from EIS outcomes, errors, retries, certificates, webhooks, and merchant coverage.
                </p>
            </div>
            <Link to="/vendors" className="text-sm text-slate-600 hover:text-slate-800">
                ← Back to vendors
            </Link>
        </div>
    );
}
