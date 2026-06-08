import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { onboardingApi } from '../../hooks/useOnboarding';
import { getApiErrorMessage } from '../../utils/apiErrors';

export default function MerchantCertificate() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [uploadedCertificateId, setUploadedCertificateId] = useState(null);
    const [file, setFile] = useState(null);
    const [password, setPassword] = useState('');

    const { data: latestCertificate } = useQuery({
        queryKey: ['merchant-certificates', id],
        queryFn: () => onboardingApi.listCertificates(id),
        enabled: Boolean(id),
    });

    const uploadMutation = useMutation({
        mutationFn: () => {
            const formData = new FormData();
            formData.append('merchant_id', id);
            formData.append('file', file);
            formData.append('password', password);
            return onboardingApi.uploadCertificate(id, formData);
        },
        onSuccess: (certificate) => {
            setUploadedCertificateId(certificate?.id ?? null);
            setSuccess('Certificate uploaded successfully.');
            setError('');
            setFile(null);
            setPassword('');
        },
        onError: (err) => {
            setError(getApiErrorMessage(err, 'Certificate upload failed.'));
            setSuccess('');
        },
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        if (!file) {
            setError('Please choose a certificate file.');
            return;
        }
        uploadMutation.mutate();
    };

    return (
        <form onSubmit={handleSubmit} className="card max-w-xl space-y-4">
            <h2 className="text-sm font-semibold text-slate-800">Step 4 — Upload certificate</h2>

            {(uploadedCertificateId ?? latestCertificate?.[0]?.id) && (
                <div className="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    A certificate is on file.{' '}
                    <Link
                        to={`/certificates/${uploadedCertificateId ?? latestCertificate[0].id}`}
                        className="font-medium underline"
                    >
                        View certificate
                    </Link>
                </div>
            )}

            {error && (
                <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}
            {success && (
                <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {success}
                </div>
            )}

            <div>
                <label htmlFor="file" className="form-label">
                    Certificate file (.pfx / .pem)
                </label>
                <input
                    id="file"
                    type="file"
                    accept=".pfx,.p12,.pem"
                    onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                    className="input file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-sm"
                />
            </div>

            <div>
                <label htmlFor="password" className="form-label">
                    Certificate password
                </label>
                <input
                    id="password"
                    type="password"
                    required
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    className="input"
                />
            </div>

            <div className="flex flex-wrap gap-3">
                <button type="submit" disabled={uploadMutation.isPending} className="btn-primary">
                    {uploadMutation.isPending ? 'Uploading…' : 'Upload certificate'}
                </button>
                <button type="button" onClick={() => navigate(`/merchants/${id}/ptt`)} className="btn-secondary">
                    Next: PTT
                </button>
            </div>
        </form>
    );
}
