import { useQuery } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import DataTable from '../../components/DataTable';
import Pagination from '../../components/Pagination';
import SearchBar from '../../components/SearchBar';
import StatusBadge from '../../components/StatusBadge';
import { usePagination } from '../../hooks/usePagination';
import { branchService } from '../../services/branchService';
import { extractPaginated } from '../../utils/pagination';
import { filterRows } from '../../utils/tableHelpers';

function formatTimestamp(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

export default function BranchList() {
    const navigate = useNavigate();
    const { page, perPage, params, setPage } = usePagination();
    const [search, setSearch] = useState('');

    const queryParams = useMemo(() => ({ ...params }), [params]);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['branches', queryParams],
        queryFn: async () => extractPaginated(await branchService.list(queryParams)),
    });

    const filteredRows = useMemo(
        () =>
            filterRows(data?.data ?? [], search, [
                'name',
                'branch_code',
                'merchant.name',
                'merchant.merchant_code',
                'status',
            ]),
        [data?.data, search],
    );

    const columns = [
        { key: 'branch_code', label: 'Branch code' },
        { key: 'name', label: 'Name' },
        {
            key: 'merchant',
            label: 'Merchant',
            render: (_, row) => row.merchant?.name ?? row.merchant_name ?? '—',
        },
        {
            key: 'status',
            label: 'Active',
            render: (status) => (
                <StatusBadge
                    status={status === 'inactive' ? 'inactive' : 'active'}
                    label={status === 'inactive' ? 'Inactive' : 'Active'}
                />
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            render: (value) => formatTimestamp(value),
        },
        {
            key: 'actions',
            label: 'Actions',
            sortable: false,
            render: (_, row) => (
                <Link
                    to={`/branches/${row.id}`}
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
                <h1 className="text-2xl font-semibold text-slate-800">Branches</h1>
                <p className="mt-1 text-sm text-slate-500">All branches across merchants.</p>
            </div>

            <div className="mb-4">
                <SearchBar
                    value={search}
                    onChange={(value) => {
                        setSearch(value);
                        setPage(1);
                    }}
                    placeholder="Search branches…"
                />
            </div>

            {isError && (
                <p className="mb-4 text-sm text-red-600">Failed to load branches. Please try again.</p>
            )}

            <DataTable
                columns={columns}
                data={filteredRows}
                loading={isLoading}
                rowKey="id"
                onRow={(record) => ({
                    onClick: () => navigate(`/branches/${record.id}`),
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
