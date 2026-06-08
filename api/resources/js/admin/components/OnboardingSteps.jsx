import { Link, useLocation, useParams } from 'react-router-dom';

const STEPS = [
    { key: 'info', label: 'Merchant', path: () => '/merchants/new', match: (p) => p === '/merchants/new' || p === '/merchants/create' },
    { key: 'branches', label: 'Branches', path: (id) => `/merchants/${id}/branches`, match: (p, id) => p.endsWith(`/merchants/${id}/branches`) },
    { key: 'devices', label: 'Devices', path: (id) => `/merchants/${id}/devices`, match: (p, id) => p.endsWith(`/merchants/${id}/devices`) },
    { key: 'certificate', label: 'Certificate', path: (id) => `/merchants/${id}/certificate`, match: (p, id) => p.endsWith(`/merchants/${id}/certificate`) },
    { key: 'ptt', label: 'PTT', path: (id) => `/merchants/${id}/ptt`, match: (p, id) => p.endsWith(`/merchants/${id}/ptt`) },
    { key: 'readiness', label: 'Readiness', path: (id) => `/merchants/${id}/readiness`, match: (p, id) => p.endsWith(`/merchants/${id}/readiness`) },
];

function detectCurrent(pathname, id) {
    const step = STEPS.find((s) => s.match(pathname, id));
    return step?.key ?? 'info';
}

export default function OnboardingSteps({ current: currentOverride }) {
    const { id } = useParams();
    const { pathname } = useLocation();
    const current = currentOverride ?? detectCurrent(pathname, id);

    return (
        <nav aria-label="Onboarding progress" className="mb-8 flex flex-wrap gap-2">
            {STEPS.map((step, index) => {
                const isActive = step.key === current;
                const isDisabled = !id && step.key !== 'info';
                const isComplete = id && STEPS.findIndex((s) => s.key === current) > index;

                const className = [
                    'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                    isActive
                        ? 'bg-slate-900 text-white'
                        : isComplete
                          ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200'
                          : isDisabled
                            ? 'bg-slate-100 text-slate-400'
                            : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                ].join(' ');

                const badge = (
                    <span
                        className={[
                            'flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold',
                            isActive ? 'bg-white text-slate-900' : 'bg-slate-200 text-slate-700',
                        ].join(' ')}
                    >
                        {isComplete && !isActive ? '✓' : index + 1}
                    </span>
                );

                if (isDisabled) {
                    return (
                        <span key={step.key} className={className}>
                            {badge}
                            {step.label}
                        </span>
                    );
                }

                return (
                    <Link key={step.key} to={step.path(id)} className={className}>
                        {badge}
                        {step.label}
                    </Link>
                );
            })}
        </nav>
    );
}
