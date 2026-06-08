import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { onboardingApi } from '../../hooks/useOnboarding';
import { branchService } from '../../services/branchService';
import { getApiErrorMessage } from '../../utils/apiErrors';
import { extractPaginated } from '../../utils/pagination';

export default function MerchantDevices() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        branch_id: '',
        pos_device_id: '',
        status: 'active',
    });

    const { data: branches } = useQuery({
        queryKey: ['branches', { merchant_id: id }],
        queryFn: async () =>
            extractPaginated(await branchService.list({ merchant_id: id, per_page: 50 })),
    });

    const branchOptions = branches?.data ?? [];

    const { data: devices, isLoading } = useQuery({
        queryKey: ['devices', { merchant_id: id }],
        queryFn: () => onboardingApi.listDevices(id),
        enabled: branchOptions.length > 0,
    });

    const createMutation = useMutation({
        mutationFn: (values) =>
            onboardingApi.createDevice(Number(values.branch_id), {
                pos_device_id: values.pos_device_id,
                name: values.pos_device_id,
                status: values.status,
                merchant_id: Number(id),
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['devices', { merchant_id: id }] });
            setForm((prev) => ({ ...prev, pos_device_id: '' }));
            setError('');
        },
        onError: (err) => {
            setError(getApiErrorMessage(err, 'Failed to register device.'));
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

    const deviceList = Array.isArray(devices) ? devices : [];

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <form onSubmit={handleSubmit} className="card space-y-4">
                <h2 className="text-sm font-semibold text-slate-800">Step 3 — Register device</h2>

                {error && (
                    <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {branchOptions.length === 0 ? (
                    <p className="text-sm text-amber-700">
                        Add a branch first.{' '}
                        <Link to={`/merchants/${id}/branches`} className="underline">
                            Go to branches
                        </Link>
                    </p>
                ) : (
                    <>
                        <div>
                            <label htmlFor="branch_id" className="form-label">
                                Branch
                            </label>
                            <select
                                id="branch_id"
                                name="branch_id"
                                required
                                value={form.branch_id || String(branchOptions[0]?.id ?? '')}
                                onChange={handleChange}
                                className="input"
                            >
                                {branchOptions.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name} ({branch.branch_code})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label htmlFor="pos_device_id" className="form-label">
                                POS device ID
                            </label>
                            <input
                                id="pos_device_id"
                                name="pos_device_id"
                                required
                                value={form.pos_device_id}
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
                                <option value="locked">Locked</option>
                            </select>
                        </div>

                        <button type="submit" disabled={createMutation.isPending} className="btn-primary">
                            {createMutation.isPending ? 'Saving…' : 'Register device'}
                        </button>
                    </>
                )}
            </form>

            <div className="card">
                <h2 className="mb-4 text-sm font-semibold text-slate-800">Registered devices</h2>
                {isLoading ? (
                    <p className="text-sm text-slate-500">Loading…</p>
                ) : deviceList.length === 0 ? (
                    <p className="text-sm text-slate-500">No devices yet.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {deviceList.map((device) => (
                            <li key={device.id} className="py-3 text-sm">
                                <p className="font-medium text-slate-800">{device.pos_device_id}</p>
                                <p className="text-slate-500">
                                    Branch: {device.branch?.name ?? device.branch_id} · {device.status}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}

                <button
                    type="button"
                    onClick={() => navigate(`/merchants/${id}/certificate`)}
                    disabled={deviceList.length === 0}
                    className="btn-primary mt-6"
                >
                    Next: Certificate
                </button>
            </div>
        </div>
    );
}
