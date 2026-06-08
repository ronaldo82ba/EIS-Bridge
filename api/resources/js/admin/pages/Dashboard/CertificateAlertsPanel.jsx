import { Link } from 'react-router-dom';

const LEVEL_LABELS = {
    expired: 'Expired',
    expiring_7: 'Expiring in 7 days',
    expiring_30: 'Expiring in 30 days',
};

const LEVEL_CLASSES = {
    expired: 'bg-red-100 text-red-800',
    expiring_7: 'bg-amber-100 text-amber-800',
    expiring_30: 'bg-yellow-100 text-yellow-800',
};

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function CertificateAlertsPanel({ summary, loading }) {
    const recent = summary?.recent ?? [];
    const count = summary?.count ?? 0;

    return (
        <div className="rounded-lg border border-slate-200 bg-white p-6">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="font-medium text-slate-800">Certificate Alerts</h2>
                <span className="text-sm text-slate-500">{count} total</span>
            </div>

            {loading ? (
                <p className="text-sm text-slate-500">Loading certificate alerts…</p>
            ) : recent.length === 0 ? (
                <p className="text-sm text-slate-500">No certificate expiry alerts yet.</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {recent.map((alert) => {
                        const merchant = alert.certificate?.merchant;
                        const levelClass = LEVEL_CLASSES[alert.level] ?? 'bg-slate-100 text-slate-700';

                        return (
                            <li key={alert.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${levelClass}`}>
                                            {LEVEL_LABELS[alert.level] ?? alert.level}
                                        </span>
                                        <span className="text-sm font-medium text-slate-800">
                                            {merchant?.name ?? 'Unknown merchant'}
                                        </span>
                                    </div>
                                    <div className="mt-1 text-xs text-slate-500">
                                        Expires {formatDate(alert.certificate?.expires_at)}
                                        {merchant?.merchant_code ? ` · ${merchant.merchant_code}` : ''}
                                    </div>
                                </div>
                                {alert.certificate?.id && (
                                    <Link
                                        to={`/certificates/${alert.certificate.id}`}
                                        className="text-sm text-blue-600 hover:underline"
                                    >
                                        View certificate
                                    </Link>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
