export default function KpiCard({ label, value }) {
    return (
        <div className="rounded-lg border bg-slate-50 p-4">
            <div className="mb-1 text-xs uppercase tracking-wide text-slate-500">{label}</div>
            <div className="text-2xl font-semibold text-slate-900">{value}</div>
        </div>
    );
}
