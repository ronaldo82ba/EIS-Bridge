import { useQuery } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api';
import DataTable from '../../components/DataTable';
import Pagination from '../../components/Pagination';
import SearchBar from '../../components/SearchBar';
import StatusBadge from '../../components/StatusBadge';
import { usePagination } from '../../hooks/usePagination';
import { extractPaginated } from '../../utils/pagination';
import { filterRows } from '../../utils/tableHelpers';

export default function VendorList() {
    const { page, perPage, params, setPage } = usePagination();
    const [search, setSearch] = useState('');

    const queryParams = useMemo(
        () => ({
            ...params,
            ...(search.trim() ? { search: search.trim() } : {}),
        }),
        [params, search],
    );

    const { data, isLoading, isError } = useQuery({
        queryKey: ['vendors', queryParams],
        queryFn: async () => extractPaginated(await api.get('/vendors', { params: queryParams })),
    });

    const filteredRows = useMemo(
        () => filterRows(data?.data ?? [], search, ['name', 'id', 'status']),
        [data?.data, search],
    );

    const columns = [
        { key: 'name', label: 'Name' },
        {
            key: 'code',
            label: 'Code',
            render: (_, row) => row.code ?? row.id,
        },
        {
            key: 'status',
            label: 'Status',
            render: (status) => <StatusBadge status={status ?? 'active'} />,
        },
        {
            key: 'actions',
            label: 'Actions',
            sortable: false,
            render: (_, row) => (
                <div className="flex items-center gap-3">
                    <Link
                        to={`/vendors/${row.id}`}
                        className="font-medium text-blue-600 hover:text-blue-800"
                        onClick={(event) => event.stopPropagation()}
                    >
                        View
                    </Link>
                    <Link
                        to={`/vendors/${row.id}`}
                        className="text-slate-600 hover:text-slate-800"
                        onClick={(event) => event.stopPropagation()}
                    >
                        Edit
                    </Link>
                </div>
            ),
        },
    ];

    return (
        <div className="bg-white p-6 rounded-lg border border-slate-200">
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-2xl font-semibold text-slate-800">Vendors</h1>
                <Link
                    to="/vendors/create"
                    className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                >
                    Create Vendor
                </Link>
            </div>

            <div className="mb-4">
                <SearchBar
                    value={search}
                    onChange={(value) => {
                        setSearch(value);
                        setPage(1);
                    }}
                    placeholder="Search vendors…"
                />
            </div>

            {isError && (
                <p className="mb-4 text-sm text-red-600">Failed to load vendors. Please try again.</p>
            )}

            <DataTable columns={columns} data={filteredRows} loading={isLoading} rowKey="id" />

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
