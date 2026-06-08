const STATUS_STYLES = {
    active: 'bg-green-100 text-green-800',
    acknowledged: 'bg-green-100 text-green-800',
    sent: 'bg-green-100 text-green-800',
    success: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    queued: 'bg-yellow-100 text-yellow-800',
    suspended: 'bg-slate-100 text-slate-600',
    inactive: 'bg-slate-100 text-slate-600',
    failed: 'bg-red-100 text-red-800',
    rejected: 'bg-red-100 text-red-800',
    resolved: 'bg-slate-100 text-slate-600',
};

export default function StatusBadge({ status, label }) {
    const normalized = String(status ?? 'unknown').toLowerCase();
    const style = STATUS_STYLES[normalized] ?? 'bg-slate-100 text-slate-600';
    const text = label ?? status ?? 'Unknown';

    return (
        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${style}`}>
            {text}
        </span>
    );
}
