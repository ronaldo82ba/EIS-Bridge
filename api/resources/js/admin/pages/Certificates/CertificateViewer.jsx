import { useMutation, useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { toastError, toastSuccess } from '../../components/Toast';
import { certificateService } from '../../services/certificateService';

function formatTimestamp(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

function getExpiryBadge(expiresAt) {
    if (!expiresAt) {
        return { label: 'Valid', className: 'bg-emerald-100 text-emerald-800' };
    }

    const expiry = new Date(expiresAt);
    const now = new Date();

    if (expiry < now) {
        return { label: 'Expired', className: 'bg-red-100 text-red-800' };
    }

    const daysUntilExpiry = (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

    if (daysUntilExpiry < 30) {
        return { label: 'Expiring soon', className: 'bg-amber-100 text-amber-800' };
    }

    return { label: 'Valid', className: 'bg-emerald-100 text-emerald-800' };
}

const ALERT_LEVEL_LABELS = {
    expired: 'Expired',
    expiring_7: 'Expiring in 7 days',
    expiring_30: 'Expiring in 30 days',
};

function DetailRow({ label, children }) {
    return (
        <div className="grid gap-1 border-b border-slate-100 py-3 sm:grid-cols-3 sm:gap-4">
            <dt className="text-sm font-medium text-slate-500">{label}</dt>
            <dd className="text-sm text-slate-800 sm:col-span-2">{children}</dd>
        </div>
    );
}

export default function CertificateViewer() {
    const { id: certificateId } = useParams();

    const { data: certificate, isLoading, isError } = useQuery({
        queryKey: ['certificates', certificateId],
        queryFn: async () => (await certificateService.get(certificateId)).data?.data,
        enabled: Boolean(certificateId),
    });

    const testSigningMutation = useMutation({
        mutationFn: () => certificateService.testSigning(certificateId),
        onSuccess: (response) => {
            const hash = response.data?.signature_hash;
            toastSuccess(
                hash
                    ? `Test signing succeeded (${response.data.algorithm}). Hash: ${hash.slice(0, 16)}…`
                    : 'Test signing succeeded.',
            );
        },
        onError: (error) =>
            toastError(error.response?.data?.message ?? 'Test signing failed. Check certificate and password.'),
    });

    if (isLoading) {
        return <div className="text-sm text-slate-500">Loading certificate…</div>;
    }

    if (isError || !certificate) {
        return (
            <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Failed to load certificate. Please try again.
            </div>
        );
    }

    const expiryBadge = getExpiryBadge(certificate.expires_at);
    const displayPath = certificate.storage_path_display ?? certificate.file_path ?? certificate.filename;
    const merchant = certificate.merchant;
    const alerts = certificate.alerts ?? [];

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-sm text-slate-500">
                        <Link to="/certificates" className="text-blue-600 hover:underline">
                            Certificates
                        </Link>
                        <span className="mx-2">/</span>
                        <span>Certificate #{certificate.id}</span>
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold text-slate-900">{certificate.filename}</h1>
                </div>
                <span
                    className={`inline-flex rounded-full px-3 py-1 text-xs font-medium ${expiryBadge.className}`}
                >
                    {expiryBadge.label}
                </span>
            </div>

            <div className="card">
                <h2 className="mb-2 text-sm font-semibold text-slate-800">Certificate details</h2>
                <dl>
                    <DetailRow label="Merchant">
                        {merchant ? (
                            <Link
                                to={`/merchants/${merchant.id}`}
                                className="text-blue-600 hover:underline"
                            >
                                {merchant.name ?? merchant.merchant_code}
                            </Link>
                        ) : (
                            '—'
                        )}
                    </DetailRow>
                    <DetailRow label="File name">{certificate.filename ?? '—'}</DetailRow>
                    <DetailRow label="Storage path">
                        <code className="rounded bg-slate-100 px-2 py-0.5 text-xs">{displayPath}</code>
                    </DetailRow>
                    <DetailRow label="Password">
                        <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                            {certificate.password_status === 'encrypted' ? 'Encrypted' : 'Unknown'}
                        </span>
                    </DetailRow>
                    <DetailRow label="Expires">{formatTimestamp(certificate.expires_at)}</DetailRow>
                    <DetailRow label="Uploaded">{formatTimestamp(certificate.created_at)}</DetailRow>
                    <DetailRow label="Structure validated">{formatTimestamp(certificate.parsed_at)}</DetailRow>
                </dl>
            </div>

            <div className="card">
                <h2 className="mb-2 text-sm font-semibold text-slate-800">Alert history</h2>
                {alerts.length === 0 ? (
                    <p className="text-sm text-slate-500">No expiry alerts recorded for this certificate.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {alerts.map((alert) => (
                            <li key={alert.id} className="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div className="text-sm font-medium text-slate-800">
                                        {ALERT_LEVEL_LABELS[alert.level] ?? alert.level}
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        Created {formatTimestamp(alert.created_at)}
                                    </div>
                                </div>
                                <div className="text-xs text-slate-500">
                                    Admin {alert.notified_admin ? 'notified' : 'pending'}
                                    {' · '}
                                    Vendor {alert.notified_vendor ? 'notified' : 'pending'}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="card max-w-xl space-y-4">
                <h2 className="text-sm font-semibold text-slate-800">Test signing</h2>
                <p className="text-sm text-slate-600">
                    Sign a sample BIR JSON payload with this certificate to verify the private key and password
                    work before going live.
                </p>
                <button
                    type="button"
                    onClick={() => testSigningMutation.mutate()}
                    disabled={testSigningMutation.isPending}
                    className="btn-primary"
                >
                    {testSigningMutation.isPending ? 'Signing…' : 'Run test signing'}
                </button>
            </div>
        </div>
    );
}
