import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import StatCard from '../../components/StatCard';
import StatusBadge from '../../components/StatusBadge';
import { merchantService } from '../../services/merchantService';

const CHECK_LABELS = {
    merchant_info: 'Merchant info',
    branches: 'Branches',
    devices: 'Devices',
    certificate: 'Certificate',
    ptt: 'PTT',
    signing_test: 'Signing test',
    mapping_test: 'Mapping test',
};

const EXPIRY_ALERT_MESSAGES = {
    expired: 'This merchant certificate has expired. Upload a new certificate to resume signing.',
    expiring_7: 'This merchant certificate expires within 7 days.',
    expiring_30: 'This merchant certificate expires within 30 days.',
};

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function firstIncompleteStep(checks = {}) {
    const order = ['branches', 'devices', 'certificate', 'ptt', 'readiness'];
    const paths = {
        branches: 'branches',
        devices: 'devices',
        certificate: 'certificate',
        ptt: 'ptt',
        readiness: 'readiness',
    };

    for (const key of order) {
        if (key === 'readiness') {
            return paths.readiness;
        }

        if (!checks[key]) {
            return paths[key];
        }
    }

    return 'branches';
}

export default function MerchantDetailDashboard() {
    const { id: merchantId } = useParams();

    const {
        data: merchant,
        isLoading: merchantLoading,
        isError: merchantError,
    } = useQuery({
        queryKey: ['merchants', merchantId],
        queryFn: () => merchantService.getData(merchantId),
        enabled: Boolean(merchantId),
    });

    const {
        data: readiness,
        isLoading: readinessLoading,
        isError: readinessError,
    } = useQuery({
        queryKey: ['merchants', merchantId, 'readiness'],
        queryFn: async () => {
            const response = await merchantService.getReadiness(merchantId);
            return response.data?.data ?? response.data;
        },
        enabled: Boolean(merchantId),
    });

    if (merchantLoading || readinessLoading) {
        return <div className="text-sm text-slate-500">Loading…</div>;
    }

    if (merchantError || !merchant) {
        return (
            <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Failed to load merchant. Please try again.
            </div>
        );
    }

    const branches = merchant.branches ?? [];
    const hasDevices = branches.some((branch) => (branch.devices ?? []).length > 0);
    const stats = merchant.stats ?? {};
    const checks = readiness?.checks ?? {};
    const ready = readiness?.ready ?? false;
    const continuePath = firstIncompleteStep(checks);
    const expiryAlert = merchant.certificate?.expiry_alert;

    return (
        <div className="space-y-6">
            {expiryAlert && (
                <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <div className="font-medium">Certificate expiry alert</div>
                    <p className="mt-1">{EXPIRY_ALERT_MESSAGES[expiryAlert] ?? 'Certificate expiry requires attention.'}</p>
                    {merchant.certificate?.id && (
                        <Link
                            to={`/certificates/${merchant.certificate.id}`}
                            className="mt-2 inline-block text-amber-900 underline hover:text-amber-950"
                        >
                            View certificate details
                        </Link>
                    )}
                </div>
            )}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-800">{merchant.name}</h1>
                    <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                        <span>TIN: {merchant.tin ?? '—'}</span>
                        {merchant.merchant_code && <span>Code: {merchant.merchant_code}</span>}
                        {merchant.vendor?.name && <span>Vendor: {merchant.vendor.name}</span>}
                        <StatusBadge status={merchant.status ?? 'active'} />
                    </div>
                </div>
                <Link to="/merchants" className="text-sm text-slate-500 hover:text-slate-700">
                    Back to list
                </Link>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Quick actions</h2>
                <div className="flex flex-wrap gap-3">
                    <Link to={`/merchants/${merchantId}/branches`} className="btn-secondary">
                        Edit onboarding steps
                    </Link>
                    <Link to={`/merchants/${merchantId}/readiness`} className="btn-secondary">
                        View readiness
                    </Link>
                    <Link
                        to={`/invoices?merchant_code=${encodeURIComponent(merchant.merchant_code ?? '')}`}
                        className="btn-secondary"
                    >
                        View invoices
                    </Link>
                    <Link to={`/merchants/${merchantId}/activity`} className="btn-secondary">
                        Activity
                    </Link>
                    <Link
                        to={`/merchants/analytics?merchant=${merchantId}`}
                        className="btn-secondary"
                    >
                        Analytics
                    </Link>
                    <Link
                        to={`/merchants/health?merchant=${merchantId}`}
                        className="btn-secondary"
                    >
                        Health
                    </Link>
                    {!ready && (
                        <Link to={`/merchants/${merchantId}/${continuePath}`} className="btn-primary">
                            Continue onboarding
                        </Link>
                    )}
                </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Onboarding Readiness</h2>

                {readinessError ? (
                    <p className="text-sm text-red-600">Failed to load readiness report.</p>
                ) : (
                    <>
                        <ul className="space-y-2 text-sm">
                            {Object.entries(CHECK_LABELS).map(([key, label]) => (
                                <li key={key} className="flex justify-between border-b border-slate-100 py-2">
                                    <span>{label}</span>
                                    <span className={checks[key] ? 'text-emerald-600' : 'text-rose-600'}>
                                        {checks[key] ? '✔ Ready' : '✖ Missing'}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-3 text-sm">
                            Overall status:{' '}
                            <span className={ready ? 'font-medium text-emerald-600' : 'font-medium text-rose-600'}>
                                {ready ? 'Ready for EIS' : 'Not Ready'}
                            </span>
                        </div>
                    </>
                )}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Branches</h2>

                {branches.length === 0 ? (
                    <div className="text-sm text-slate-500">No branches yet</div>
                ) : (
                    <ul className="space-y-2">
                        {branches.map((branch) => (
                            <li key={branch.id} className="rounded border border-slate-200 bg-slate-50 p-3">
                                <div className="flex justify-between">
                                    <div>
                                        <div className="font-medium text-slate-800">{branch.name}</div>
                                        <div className="text-xs text-slate-500">
                                            Code: {branch.branch_code ?? branch.code ?? '—'}
                                        </div>
                                    </div>
                                    <Link
                                        to={`/branches/${branch.id}`}
                                        className="text-sm text-blue-600 hover:text-blue-800 hover:underline"
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
                <h2 className="mb-3 font-medium text-slate-800">POS Devices</h2>

                {!hasDevices ? (
                    <div className="text-sm text-slate-500">No devices yet</div>
                ) : (
                    branches.map((branch) => {
                        const devices = branch.devices ?? [];
                        if (devices.length === 0) {
                            return null;
                        }

                        return (
                            <div key={branch.id} className="mb-4 last:mb-0">
                                <div className="mb-1 font-medium text-slate-800">{branch.name}</div>
                                <ul className="space-y-1">
                                    {devices.map((device) => (
                                        <li
                                            key={device.id}
                                            className="rounded border border-slate-200 bg-slate-50 p-2 text-sm text-slate-700"
                                        >
                                            {device.pos_device_id}
                                            {device.status && (
                                                <span className="ml-2 text-xs text-slate-500">({device.status})</span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        );
                    })
                )}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Certificate</h2>

                {merchant.certificate ? (
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-sm text-slate-700">
                                Expires: {formatDate(merchant.certificate.expires_at)}
                            </div>
                            <div className="text-xs text-slate-500">
                                Uploaded: {formatDate(merchant.certificate.created_at)}
                            </div>
                        </div>

                        <Link
                            to={`/certificates/${merchant.certificate.id}`}
                            className="text-sm text-blue-600 hover:text-blue-800 hover:underline"
                        >
                            View certificate
                        </Link>
                    </div>
                ) : (
                    <div className="text-sm text-slate-500">No certificate uploaded</div>
                )}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">PTT</h2>

                {merchant.ptt ? (
                    <div className="space-y-1 text-sm text-slate-700">
                        <div>PTT Number: {merchant.ptt.ptt_number ?? '—'}</div>
                        <div>Valid From: {formatDate(merchant.ptt.valid_from)}</div>
                        <div>Valid To: {formatDate(merchant.ptt.valid_to)}</div>
                        {merchant.ptt.status && (
                            <div className="pt-1">
                                <StatusBadge status={merchant.ptt.status} />
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="text-sm text-slate-500">No PTT configured</div>
                )}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                <h2 className="mb-3 font-medium text-slate-800">Merchant Stats</h2>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard title="Invoices Today" value={stats.today_total ?? 0} accent="blue" />
                    <StatCard title="Acknowledged" value={stats.today_ack ?? 0} accent="green" />
                    <StatCard title="Rejected" value={stats.today_rejected ?? 0} accent="red" />
                    <StatCard title="Transmission Failures" value={stats.failures ?? 0} accent="amber" />
                </div>
            </div>
        </div>
    );
}
