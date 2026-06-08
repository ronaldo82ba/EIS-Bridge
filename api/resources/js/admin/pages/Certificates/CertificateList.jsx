import { useQuery } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import DataTable from '../../components/DataTable';
import Pagination from '../../components/Pagination';
import SearchBar from '../../components/SearchBar';
import StatusBadge from '../../components/StatusBadge';
import { usePagination } from '../../hooks/usePagination';
import { certificateService } from '../../services/certificateService';
import { extractPaginated } from '../../utils/pagination';
import { filterRows } from '../../utils/tableHelpers';

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString();
}

function getCertificateStatus(expiresAt) {
    if (!expiresAt) {
        return { status: 'valid', label: 'Valid' };
    }

    const expiry = new Date(expiresAt);
    const now = new Date();

    if (expiry < now) {
        return { status: 'expired', label: 'Expired' };
    }

    const daysUntilExpiry = (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

    if (daysUntilExpiry < 30) {
        return { status: 'expiring', label: 'Expiring' };
    }

    return { status: 'valid', label: 'Valid' };
}

export default function CertificateList() {
    const navigate = useNavigate();
    const { page, perPage, params, setPage } = usePagination();
    const [search, setSearch] = useState('');

    const queryParams = useMemo(() => ({ ...params }), [params]);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['certificates', queryParams],
        queryFn: async () => extractPaginated(await certificateService.list(queryParams)),
    });

    const filteredRows = useMemo(
        () =>
            filterRows(data?.data ?? [], search, [
                'id',
                'filename',
                'merchant.name',
                'merchant.merchant_code',
            ]),
        [data?.data, search],
    );

    const columns = [
        {
            key: 'merchant',
            label: 'Merchant',
            render: (_, row) => row.merchant?.name ?? row.merchant_name ?? '—',
        },
        {
            key: 'id',
            label: 'Certificate ID',
            render: (value) => value ?? '—',
        },
        {
            key: 'created_at',
            label: 'Valid from',
            render: (value, row) => formatDate(row.parsed_at ?? row.created_at ?? value),
        },
        {
            key: 'expires_at',
            label: 'Valid to',
            render: (value) => formatDate(value),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: false,
            render: (_, row) => {
                const { status, label } = getCertificateStatus(row.expires_at);
                return <StatusBadge status={status} label={label} />;
            },
        },
        {
            key: 'actions',
            label: 'Actions',
            sortable: false,
            render: (_, row) => (
                <Link
                    to={`/certificates/${row.id}`}
                    className="font-medium text-blue-600 hover:text-blue-800"
                    onClick={(event) => event.stopPropagation()}
                >
                    View
                </Link>
            ),
        },
    ];

    return (
        <div className="rounded-lg border border-slate-200 bg-white p-6">
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-800">Certificates</h1>
                <p className="mt-1 text-sm text-slate-500">Merchant signing certificates and expiry status.</p>
            </div>

            <div className="mb-4">
                <SearchBar
                    value={search}
                    onChange={(value) => {
                        setSearch(value);
                        setPage(1);
                    }}
                    placeholder="Search certificates…"
                />
            </div>

            {isError && (
                <p className="mb-4 text-sm text-red-600">
                    Failed to load certificates. Please try again.
                </p>
            )}

            <DataTable
                columns={columns}
                data={filteredRows}
                loading={isLoading}
                rowKey="id"
                onRow={(record) => ({
                    onClick: () => navigate(`/certificates/${record.id}`),
                    style: { cursor: 'pointer' },
                })}
            />

            <Pagination
                page={page}
                perPage={perPage}
                total={data?.pagination?.total ?? 0}
                lastPage={data?.pagination?.lastPage}
                onPageChange={setPage}
            />
        </div>
    );
}
