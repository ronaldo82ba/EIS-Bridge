import { useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { authService } from '../services/authService';
import { getApiErrorMessage } from '../utils/apiErrors';

export default function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { setAuth, isAuthenticated } = useAuth();
    const from = location.state?.from?.pathname ?? '/dashboard';

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    if (isAuthenticated) {
        return <Navigate to={from} replace />;
    }

    const handleSubmit = async (event) => {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            const response = await authService.login({ email, password });
            const { user, token } = response.data;
            setAuth(user, token ?? null);
            navigate(from, { replace: true });
        } catch (err) {
            setError(getApiErrorMessage(err, 'Invalid credentials or admin auth API not available.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-100 px-4">
            <div className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <h1 className="text-2xl font-semibold text-slate-800">EIS Bridge Console</h1>
                <p className="mt-2 text-sm text-slate-500">
                    Sign in to manage vendors, merchants, and invoice pipeline health.
                </p>

                {error && (
                    <div className="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {error}
                    </div>
                )}

                <form className="mt-6 space-y-4" onSubmit={handleSubmit}>
                    <div>
                        <label htmlFor="email" className="mb-1 block text-sm font-medium text-slate-700">
                            Email
                        </label>
                        <input
                            id="email"
                            type="email"
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                            placeholder="super_admin@eis-bridge.test"
                            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label htmlFor="password" className="mb-1 block text-sm font-medium text-slate-700">
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            placeholder="••••••••"
                            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full rounded-md bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-60"
                    >
                        {loading ? 'Signing in…' : 'Sign in'}
                    </button>
                </form>
            </div>
        </div>
    );
}
