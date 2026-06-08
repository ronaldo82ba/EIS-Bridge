import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { PHASE1_MOCK } from '../config/phase1';
import { useAuth } from '../hooks/useAuth';

export default function ProtectedRoute() {
    const { isAuthenticated, isLoading, token } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-100">
                <p className="text-sm text-slate-500">Loading…</p>
            </div>
        );
    }

    const authed = PHASE1_MOCK ? isAuthenticated : isAuthenticated && token;

    if (!authed) {
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    return <Outlet />;
}
