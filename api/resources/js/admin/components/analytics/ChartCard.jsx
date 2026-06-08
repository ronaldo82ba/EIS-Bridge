export default function ChartCard({ title, children }) {
    return (
        <div className="rounded-lg border bg-white p-6">
            <h2 className="mb-3 font-medium text-slate-900">{title}</h2>
            {children}
        </div>
    );
}
