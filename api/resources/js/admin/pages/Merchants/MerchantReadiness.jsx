import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { onboardingApi } from '../../hooks/useOnboarding';
import { getApiErrorMessage } from '../../utils/apiErrors';

const CHECK_LABELS = {
    merchant_info: 'Merchant info complete',
    branches: 'At least one branch',
    devices: 'At least one device',
    certificate: 'Certificate uploaded & valid',
    ptt: 'PTT valid',
    signing_test: 'Test signing successful',
    mapping_test: 'Test mapping successful',
};

const LEGACY_MOCK_KEYS = {
    merchant: 'merchant_info',
};

function normalizeChecks(checks = {}) {
    if (!checks) {
        return {};
    }

    const firstKey = Object.keys(checks)[0];
    if (firstKey && typeof checks[firstKey] === 'object' && 'pass' in checks[firstKey]) {
        return Object.fromEntries(
            Object.entries(checks).map(([key, value]) => [LEGACY_MOCK_KEYS[key] ?? key, value.pass]),
        );
    }

    return checks;
}

function CheckIcon({ passed }) {
    if (passed) {
        return (
            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                ✓
            </span>
        );
    }

    return (
        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-red-100 text-red-700">
            ✕
        </span>
    );
}

export default function MerchantReadiness() {
    const { id } = useParams();

    const {
        data: report,
        isLoading,
        isError,
        error,
        refetch,
        isFetching,
    } = useQuery({
        queryKey: ['merchants', id, 'readiness'],
        queryFn: () => onboardingApi.getReadiness(id),
    });

    const checks = normalizeChecks(report?.checks);
    const ready = report?.ready ?? false;

    return (
        <div className="card max-w-2xl">
            <h2 className="mb-1 text-sm font-semibold text-slate-800">Step 6 — Readiness check</h2>
            <p className="mb-6 text-sm text-slate-500">
                Verify signing, mapping, certificate, and PTT before going live.
            </p>

            {isError && (
                <p className="mb-4 text-sm text-red-600">
                    {getApiErrorMessage(error, 'Failed to load readiness report.')}
                </p>
            )}

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <p className="text-sm text-slate-500">Merchant</p>
                    <p className="text-lg font-semibold text-slate-800">{report?.merchant ?? `Merchant #${id}`}</p>
                </div>
                <span
                    className={[
                        'rounded-full px-4 py-1 text-sm font-semibold',
                        ready ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800',
                    ].join(' ')}
                >
                    {ready ? 'EIS Ready' : 'Not Ready'}
                </span>
            </div>

            {isLoading ? (
                <p className="text-sm text-slate-500">Running readiness checks…</p>
            ) : (
                <ul className="space-y-3">
                    {Object.entries(CHECK_LABELS).map(([key, label]) => (
                        <li key={key} className="flex items-center gap-3 rounded-md border border-slate-100 px-4 py-3">
                            <CheckIcon passed={checks[key]} />
                            <span className="text-sm text-slate-700">{label}</span>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-6 flex flex-wrap gap-3">
                <button
                    type="button"
                    onClick={() => refetch()}
                    disabled={isFetching}
                    className="btn-secondary"
                >
                    {isFetching ? 'Re-running…' : 'Re-run checks'}
                </button>
                <Link to={`/merchants/${id}/ptt`} className="btn-secondary">
                    Back to PTT
                </Link>
                {ready && (
                    <Link to="/merchants" className="btn-primary">
                        Finish onboarding
                    </Link>
                )}
            </div>
        </div>
    );
}
