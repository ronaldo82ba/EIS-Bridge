const accentClasses = {
    blue: 'border-l-blue-500',
    green: 'border-l-emerald-500',
    red: 'border-l-red-500',
    amber: 'border-l-amber-500',
};

export default function StatCard({ title, value, accent = 'blue' }) {
    return (
        <div
            className={`rounded-lg border border-slate-200 border-l-4 bg-white p-5 shadow-sm ${accentClasses[accent] ?? accentClasses.blue}`}
        >
            <p className="text-sm text-slate-500">{title}</p>
            <p className="mt-1 text-3xl font-bold text-slate-800">{value}</p>
        </div>
    );
}
