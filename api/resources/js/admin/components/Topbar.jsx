import { Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { PHASE1_MOCK } from '../config/phase1';
import { useAuth } from '../hooks/useAuth';
import { alertService } from '../services/alertService';
import { authService } from '../services/authService';

export default function Topbar() {
    const navigate = useNavigate();
    const { user, logout } = useAuth();

    const { data: alertSummary } = useQuery({
        queryKey: ['alerts', 'summary'],
        queryFn: async () => (await alertService.summary()).data,
        enabled: !PHASE1_MOCK,
        refetchInterval: 60_000,
    });

    const alertCount =
        (alertSummary?.by_severity?.critical ?? 0) + (alertSummary?.by_severity?.warning ?? 0);

    const handleLogout = async () => {
        if (!PHASE1_MOCK) {
            try {
                await authService.logout();
            } catch {
                // Clear local session even if the API call fails.
            }
        }
        logout();
        navigate('/login');
    };

    return (
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
            <span className="font-medium text-slate-700">EIS Bridge Console</span>
            <div className="flex items-center gap-4">
                {!PHASE1_MOCK && (
                    <Link
                        to="/alerts"
                        className="relative rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition-colors hover:bg-slate-50"
                        aria-label="View alerts"
                    >
                        Alerts
                        {alertCount > 0 && (
                            <span className="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-medium text-white">
                                {alertCount}
                            </span>
                        )}
                    </Link>
                )}
                <span className="text-sm text-slate-500">{user?.email ?? 'admin@eis-bridge.test'}</span>
                <button
                    type="button"
                    onClick={handleLogout}
                    className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition-colors hover:bg-slate-50"
                >
                    Logout
                </button>
            </div>
        </header>
    );
}
