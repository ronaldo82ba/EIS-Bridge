import { NavLink } from 'react-router-dom';

const navItems = [
    { to: '/dashboard', label: 'Dashboard' },
    { to: '/vendors', label: 'Vendors' },
    { to: '/merchants', label: 'Merchants' },
    { to: '/branches', label: 'Branches' },
    { to: '/invoices', label: 'Invoices' },
    { to: '/alerts', label: 'Alerts' },
    { to: '/queues', label: 'Queues' },
    { to: '/certificates', label: 'Certificates' },
    { to: '/webhooks', label: 'Webhooks' },
    { to: '/billing', label: 'Billing' },
    { to: '/logs', label: 'Logs' },
    { to: '/monitoring', label: 'Monitoring' },
    { to: '/settings', label: 'Settings' },
];

function linkClassName({ isActive }) {
    return [
        'block px-5 py-2.5 text-sm transition-colors',
        isActive
            ? 'bg-slate-800 text-white font-medium'
            : 'text-slate-300 hover:bg-slate-800 hover:text-white',
    ].join(' ');
}

function isNavEnd(path) {
    return path === '/dashboard';
}

export default function Sidebar() {
    return (
        <aside className="flex w-56 shrink-0 flex-col bg-slate-900 text-white">
            <div className="border-b border-slate-700 px-5 py-4 text-lg font-semibold">EIS Bridge</div>
            <nav className="flex-1 py-3">
                {navItems.map((item) => (
                    <NavLink key={item.to} to={item.to} className={linkClassName} end={isNavEnd(item.to)}>
                        {item.label}
                    </NavLink>
                ))}
            </nav>
        </aside>
    );
}
