import { Outlet } from 'react-router-dom';
import Sidebar from '../components/Sidebar';
import Topbar from '../components/Topbar';

export default function AdminLayout() {
    return (
        <div className="flex h-screen bg-slate-100">
            <Sidebar />
            <div className="flex min-w-0 flex-1 flex-col">
                <Topbar />
                <main className="flex-1 overflow-auto p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
