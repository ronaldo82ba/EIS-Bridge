import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { onboardingApi } from '../../hooks/useOnboarding';
import { getApiErrorMessage } from '../../utils/apiErrors';

export default function MerchantPTT() {
    const { id: merchantId } = useParams();
    const navigate = useNavigate();
    const [form, setForm] = useState({
        ptt_number: '',
        valid_from: '',
        valid_to: '',
    });

    const saveMutation = useMutation({
        mutationFn: (values) =>
            onboardingApi.upsertPtt(merchantId, {
                ptt_number: values.ptt_number,
                valid_from: values.valid_from,
                valid_to: values.valid_to,
            }),
    });

    const handleChange = (event) => {
        const { name, value } = event.target;
        setForm((prev) => ({ ...prev, [name]: value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        saveMutation.mutate(form);
    };

    return (
        <form onSubmit={handleSubmit} className="card max-w-xl space-y-4">
            <h2 className="text-sm font-semibold text-slate-800">Step 5 — PTT registration</h2>
            <p className="text-sm text-slate-500">Enter the merchant&apos;s Permit to Transmit (PTT) details from BIR.</p>

            {saveMutation.isError && (
                <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {getApiErrorMessage(saveMutation.error, 'Failed to save PTT.')}
                </div>
            )}

            {saveMutation.isSuccess && (
                <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    PTT saved successfully.
                </div>
            )}

            <div>
                <label htmlFor="ptt_number" className="form-label">
                    PTT number
                </label>
                <input
                    id="ptt_number"
                    name="ptt_number"
                    required
                    value={form.ptt_number}
                    onChange={handleChange}
                    className="input"
                />
            </div>

            <div>
                <label htmlFor="valid_from" className="form-label">
                    Valid from <span className="text-red-500">*</span>
                </label>
                <input
                    id="valid_from"
                    name="valid_from"
                    type="date"
                    required
                    value={form.valid_from}
                    onChange={handleChange}
                    className="input"
                />
            </div>

            <div>
                <label htmlFor="valid_to" className="form-label">
                    Valid to <span className="text-red-500">*</span>
                </label>
                <input
                    id="valid_to"
                    name="valid_to"
                    type="date"
                    required
                    value={form.valid_to}
                    onChange={handleChange}
                    className="input"
                />
            </div>

            <div className="flex flex-wrap gap-3">
                <button type="submit" disabled={saveMutation.isPending} className="btn-primary">
                    {saveMutation.isPending ? 'Saving…' : 'Save PTT'}
                </button>
                <button type="button" onClick={() => navigate(`/merchants/${merchantId}/readiness`)} className="btn-secondary">
                    Check readiness
                </button>
                <Link to={`/merchants/${merchantId}/certificate`} className="btn-secondary">
                    Back
                </Link>
            </div>
        </form>
    );
}
