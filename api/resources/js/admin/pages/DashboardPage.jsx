import StatCard from '../components/StatCard';

const mockStats = {
    todayTotal: 128,
    todayAck: 120,
    todayRejected: 8,
    queueDepth: 23,
};

const mockRecentErrors = [
    {
        id: 1,
        invoice: 'INV-2026-00421',
        merchant: 'Acme Retail',
        error: 'BIR schema validation failed: missing buyer TIN',
        time: '10:32 AM',
    },
    {
        id: 2,
        invoice: 'INV-2026-00418',
        merchant: 'Metro Grocer',
        error: 'EIS transmission timeout after 30s',
        time: '10:18 AM',
    },
    {
        id: 3,
        invoice: 'INV-2026-00415',
        merchant: 'QuickMart',
        error: 'Certificate expired for signing',
        time: '09:55 AM',
    },
    {
        id: 4,
        invoice: 'INV-2026-00411',
        merchant: 'Acme Retail',
        error: 'Duplicate invoice number in batch',
        time: '09:41 AM',
    },
];

export default function DashboardPage() {
    return (
        <div>
            <h1 className="mb-6 text-2xl font-semibold text-slate-800">Dashboard</h1>

            <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard title="Today's Total" value={mockStats.todayTotal} accent="blue" />
                <StatCard title="Today's Acknowledged" value={mockStats.todayAck} accent="green" />
                <StatCard title="Today's Rejected" value={mockStats.todayRejected} accent="red" />
                <StatCard title="Queue Depth" value={mockStats.queueDepth} accent="amber" />
            </div>

            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 px-5 py-4">
                    <h2 className="text-lg font-medium text-slate-800">Recent Errors</h2>
                    <p className="text-sm text-slate-500">Mock data for Phase 1 UI shell</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-5 py-3 text-left font-medium text-slate-600">Invoice</th>
                                <th className="px-5 py-3 text-left font-medium text-slate-600">Merchant</th>
                                <th className="px-5 py-3 text-left font-medium text-slate-600">Error</th>
                                <th className="px-5 py-3 text-left font-medium text-slate-600">Time</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {mockRecentErrors.map((row) => (
                                <tr key={row.id} className="hover:bg-slate-50">
                                    <td className="whitespace-nowrap px-5 py-3 font-medium text-slate-800">
                                        {row.invoice}
                                    </td>
                                    <td className="whitespace-nowrap px-5 py-3 text-slate-600">{row.merchant}</td>
                                    <td className="px-5 py-3 text-slate-600">{row.error}</td>
                                    <td className="whitespace-nowrap px-5 py-3 text-slate-500">{row.time}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
