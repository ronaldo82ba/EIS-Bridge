import { NavLink } from 'react-router-dom';

const navItems = [
    { to: '/dashboard', label: 'Dashboard' },
    { to: '/vendors', label: 'Vendors' },
    { to: '/vendors/analytics', label: 'Vendor Analytics' },
    { to: '/vendors/health', label: 'Vendor Health' },
    { to: '/merchants', label: 'Merchants' },
    { to: '/merchants/analytics', label: 'Merchant Analytics' },
    { to: '/merchants/health', label: 'Merchant Health' },
    { to: '/merchants/new', label: 'New Merchant' },
    { to: '/invoices', label: 'Invoices' },
    { to: '/invoices/search', label: 'Invoice Search' },
    { to: '/invoices/analytics', label: 'Invoice Analytics' },
    { to: '/queues', label: 'Queues' },
    { to: '/monitoring/queues', label: 'Queue Monitor' },
    { to: '/alerts', label: 'Alerts' },
];

function linkClassName({ isActive }) {
    return [
        'block px-5 py-2.5 text-sm transition-colors',
        isActive
            ? 'bg-slate-800 text-white font-medium'
            : 'text-slate-300 hover:bg-slate-800 hover:text-white',
    ].join(' ');
}

export default function Sidebar() {
    return (
        <aside className="flex w-56 shrink-0 flex-col bg-slate-900 text-white">
            <div className="border-b border-slate-700 px-5 py-4 text-lg font-semibold">EIS Bridge</div>
            <nav className="flex-1 py-3">
                {navItems.map((item) => (
                    <NavLink key={item.to} to={item.to} className={linkClassName} end={item.to === '/dashboard'}>
                        {item.label}
                    </NavLink>
                ))}
            </nav>
        </aside>
    );
}
