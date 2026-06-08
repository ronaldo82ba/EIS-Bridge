import { useQuery } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api';
import DataTable from '../../components/DataTable';
import Pagination from '../../components/Pagination';
import SearchBar from '../../components/SearchBar';
import { usePagination } from '../../hooks/usePagination';
import { extractPaginated } from '../../utils/pagination';
import { filterRows } from '../../utils/tableHelpers';

export default function MerchantList() {
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
        queryKey: ['merchants', queryParams],
        queryFn: async () => extractPaginated(await api.get('/merchants', { params: queryParams })),
    });

    const filteredRows = useMemo(
        () =>
            filterRows(data?.data ?? [], search, [
                'name',
                'tin',
                'address',
                'merchant_code',
                'vendor.name',
                'vendor_name',
            ]),
        [data?.data, search],
    );

    const columns = [
        { key: 'name', label: 'Name' },
        { key: 'tin', label: 'TIN' },
        { key: 'address', label: 'Address' },
        {
            key: 'vendor',
            label: 'Vendor',
            render: (_, row) => row.vendor?.name ?? row.vendor_name ?? '—',
        },
        {
            key: 'actions',
            label: 'Actions',
            sortable: false,
            render: (_, row) => (
                <div className="flex items-center gap-3">
                    <Link
                        to={`/merchants/${row.id}`}
                        className="font-medium text-blue-600 hover:text-blue-800"
                        onClick={(event) => event.stopPropagation()}
                    >
                        View
                    </Link>
                    <Link
                        to={`/merchants/${row.id}/branches`}
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
                <h1 className="text-2xl font-semibold text-slate-800">Merchants</h1>
                <Link
                    to="/merchants/new"
                    className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                >
                    New Merchant
                </Link>
            </div>

            <div className="mb-4">
                <SearchBar
                    value={search}
                    onChange={(value) => {
                        setSearch(value);
                        setPage(1);
                    }}
                    placeholder="Search merchants…"
                />
            </div>

            {isError && (
                <p className="mb-4 text-sm text-red-600">Failed to load merchants. Please try again.</p>
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
