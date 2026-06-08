import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { onboardingApi } from '../../hooks/useOnboarding';
import { branchService } from '../../services/branchService';
import { getApiErrorMessage } from '../../utils/apiErrors';
import { extractPaginated } from '../../utils/pagination';

export default function MerchantBranches() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        branch_code: '',
        name: '',
        address: '',
        status: 'active',
    });

    const { data: branches, isLoading } = useQuery({
        queryKey: ['branches', { merchant_id: id }],
        queryFn: async () =>
            extractPaginated(await branchService.list({ merchant_id: id, per_page: 50 })),
    });

    const createMutation = useMutation({
        mutationFn: (values) =>
            onboardingApi.createBranch({ ...values, merchant_id: Number(id) }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['branches', { merchant_id: id }] });
            setForm({ branch_code: '', name: '', address: '', status: 'active' });
            setError('');
        },
        onError: (err) => {
            setError(getApiErrorMessage(err, 'Failed to create branch.'));
        },
    });

    const handleChange = (event) => {
        const { name, value } = event.target;
        setForm((prev) => ({ ...prev, [name]: value }));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        setError('');
        createMutation.mutate(form);
    };

    const branchList = branches?.data ?? [];

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <form onSubmit={handleSubmit} className="card space-y-4">
                <h2 className="text-sm font-semibold text-slate-800">Step 2 — Add branch</h2>

                {error && (
                    <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {['branch_code', 'name'].map((field) => (
                    <div key={field}>
                        <label htmlFor={field} className="form-label capitalize">
                            {field.replace('_', ' ')}
                        </label>
                        <input
                            id={field}
                            name={field}
                            required
                            value={form[field]}
                            onChange={handleChange}
                            className="input"
                        />
                    </div>
                ))}

                <div>
                    <label htmlFor="address" className="form-label">
                        Address
                    </label>
                    <textarea
                        id="address"
                        name="address"
                        rows={2}
                        value={form.address}
                        onChange={handleChange}
                        className="input"
                    />
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

                <button type="submit" disabled={createMutation.isPending} className="btn-primary">
                    {createMutation.isPending ? 'Saving…' : 'Add branch'}
                </button>
            </form>

            <div className="card">
                <h2 className="mb-4 text-sm font-semibold text-slate-800">Existing branches</h2>
                {isLoading ? (
                    <p className="text-sm text-slate-500">Loading…</p>
                ) : branchList.length === 0 ? (
                    <p className="text-sm text-slate-500">No branches yet.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {branchList.map((branch) => (
                            <li key={branch.id} className="py-3 text-sm">
                                <p className="font-medium text-slate-800">
                                    {branch.name} <span className="text-slate-400">({branch.branch_code})</span>
                                </p>
                                <p className="text-slate-500">{branch.address ?? 'No address'}</p>
                            </li>
                        ))}
                    </ul>
                )}

                <button
                    type="button"
                    onClick={() => navigate(`/merchants/${id}/devices`)}
                    disabled={branchList.length === 0}
                    className="btn-primary mt-6"
                >
                    Next: Devices
                </button>
            </div>
        </div>
    );
}
