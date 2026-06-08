import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import OnboardingSteps from '../../components/OnboardingSteps';
import { onboardingApi } from '../../hooks/useOnboarding';
import { getApiErrorMessage } from '../../utils/apiErrors';

function slugify(value) {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 24);
}

export default function MerchantCreate() {
    const navigate = useNavigate();
    const [vendors, setVendors] = useState([]);
    const [loadingVendors, setLoadingVendors] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        name: '',
        tin: '',
        address: '',
        vendor_id: '',
        status: 'active',
    });

    useEffect(() => {
        onboardingApi
            .listVendors()
            .then((result) => setVendors(result.data ?? []))
            .catch(() => setVendors([]))
            .finally(() => setLoadingVendors(false));
    }, []);

    const handleChange = (event) => {
        const { name, value } = event.target;
        setForm((prev) => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        setError('');
        setSubmitting(true);

        try {
            const merchant = await onboardingApi.createMerchant({
                name: form.name,
                tin: form.tin,
                address: form.address,
                vendor_id: Number(form.vendor_id),
                status: form.status,
                merchant_code: `${slugify(form.name || 'merchant')}-${Date.now().toString(36)}`,
            });

            navigate(`/merchants/${merchant.id}/branches`);
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to create merchant.'));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div>
            <div className="mb-2 flex items-center justify-between">
                <h1 className="text-2xl font-semibold text-slate-800">Create Merchant</h1>
                <Link to="/merchants" className="text-sm text-slate-500 hover:text-slate-700">
                    Back to list
                </Link>
            </div>
            <p className="mb-6 text-sm text-slate-500">Step 1 of 6 — enter merchant information to begin onboarding.</p>

            <OnboardingSteps current="info" />

            <form onSubmit={handleSubmit} className="card max-w-xl space-y-4">
                {error && (
                    <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {error}
                    </div>
                )}

                <div>
                    <label htmlFor="name" className="form-label">
                        Merchant name <span className="text-red-500">*</span>
                    </label>
                    <input id="name" name="name" required value={form.name} onChange={handleChange} className="input" />
                </div>

                <div>
                    <label htmlFor="tin" className="form-label">
                        TIN <span className="text-red-500">*</span>
                    </label>
                    <input id="tin" name="tin" required value={form.tin} onChange={handleChange} className="input" />
                </div>

                <div>
                    <label htmlFor="address" className="form-label">
                        Address <span className="text-red-500">*</span>
                    </label>
                    <textarea id="address" name="address" rows={3} required value={form.address} onChange={handleChange} className="input" />
                </div>

                <div>
                    <label htmlFor="vendor_id" className="form-label">
                        Vendor <span className="text-red-500">*</span>
                    </label>
                    <select
                        id="vendor_id"
                        name="vendor_id"
                        required
                        value={form.vendor_id}
                        onChange={handleChange}
                        disabled={loadingVendors}
                        className="input"
                    >
                        <option value="">Select vendor</option>
                        {vendors.map((vendor) => (
                            <option key={vendor.id} value={vendor.id}>
                                {vendor.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="status" className="form-label">
                        Status
                    </label>
                    <select id="status" name="status" value={form.status} onChange={handleChange} className="input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" disabled={submitting} className="btn-primary">
                    {submitting ? 'Creating…' : 'Create & continue to branches'}
                </button>
            </form>
        </div>
    );
}
