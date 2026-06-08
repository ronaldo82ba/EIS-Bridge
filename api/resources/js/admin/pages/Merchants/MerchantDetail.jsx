import { useQuery } from '@tanstack/react-query';
import { Link, Outlet, useLocation, useParams } from 'react-router-dom';
import OnboardingSteps from '../../components/OnboardingSteps';
import StatusBadge from '../../components/StatusBadge';
import { onboardingApi } from '../../hooks/useOnboarding';

export default function MerchantDetail() {
    const { id } = useParams();
    const { pathname } = useLocation();
    const isDashboard = pathname === `/merchants/${id}`;

    if (isDashboard) {
        return <Outlet />;
    }

    return <MerchantOnboardingLayout merchantId={id} />;
}

function MerchantOnboardingLayout({ merchantId }) {
    const { data: merchant, isLoading } = useQuery({
        queryKey: ['merchants', merchantId],
        queryFn: () => onboardingApi.getMerchant(merchantId),
    });

    return (
        <div>
            <div className="mb-2 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-800">
                        {isLoading ? 'Loading merchant…' : merchant?.name ?? `Merchant #${merchantId}`}
                    </h1>
                    {merchant && (
                        <div className="mt-1 flex items-center gap-3 text-sm text-slate-500">
                            <StatusBadge status={merchant.status ?? 'active'} />
                            {merchant.merchant_code && <span>Code: {merchant.merchant_code}</span>}
                        </div>
                    )}
                </div>
                <div className="flex items-center gap-4">
                    <Link
                        to={`/merchants/${merchantId}`}
                        className="text-sm text-blue-600 hover:text-blue-800"
                    >
                        Dashboard
                    </Link>
                    <Link to="/merchants" className="text-sm text-slate-500 hover:text-slate-700">
                        Back to list
                    </Link>
                </div>
            </div>

            <OnboardingSteps />

            <Outlet context={{ merchant }} />
        </div>
    );
}
