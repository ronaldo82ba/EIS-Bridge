import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../../api';
import DataTable from '../../components/DataTable';
import InvoiceBulkToolbar from '../../components/InvoiceBulkToolbar';
import Pagination from '../../components/Pagination';
import SearchBar from '../../components/SearchBar';
import StatusBadge from '../../components/StatusBadge';
import { useInvoiceBulkSelection } from '../../hooks/useInvoiceBulkSelection';
import { usePagination } from '../../hooks/usePagination';
import { extractPaginated } from '../../utils/pagination';
import { filterRows } from '../../utils/tableHelpers';
import InvoiceDetailDrawer from './InvoiceDetailDrawer';

export default function InvoiceList() {
    const queryClient = useQueryClient();
    const { page, perPage, params, setPage } = usePagination(1, 25);
    const { selectedIds, isSelected, toggleSelect, toggleSelectAll, clear } = useInvoiceBulkSelection();
    const [searchParams] = useSearchParams();
    const merchantCodeFilter = searchParams.get('merchant_code') ?? '';
    const [search, setSearch] = useState('');
    const [selectedId, setSelectedId] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);

    const queryParams = useMemo(
        () => ({
            ...params,
            ...(merchantCodeFilter ? { merchant_code: merchantCodeFilter } : {}),
            ...(search.trim() ? { search: search.trim() } : {}),
        }),
        [params, merchantCodeFilter, search],
    );

    const { data, isLoading, isError, refetch } = useQuery({
        queryKey: ['invoices', queryParams],
        queryFn: async () => extractPaginated(await api.get('/invoices', { params: queryParams })),
    });

    const filteredRows = useMemo(
        () =>
            filterRows(data?.data ?? [], search, [
                'bridge_transaction_id',
                'transaction_id',
                'merchant_code',
                'branch_code',
                'processing_status',
                'eis_status',
            ]),
        [data?.data, search],
    );

    const rowIds = useMemo(() => filteredRows.map((row) => row.id), [filteredRows]);
    const allPageSelected = rowIds.length > 0 && rowIds.every((id) => isSelected(id));

    const openDrawer = (record) => {
        setSelectedId(record.id);
        setDrawerOpen(true);
    };

    const columns = [
        {
            key: 'select',
            label: (
                <input
                    type="checkbox"
                    aria-label="Select all on page"
                    checked={allPageSelected}
                    onChange={() => toggleSelectAll(rowIds)}
                    onClick={(event) => event.stopPropagation()}
                />
            ),
            sortable: false,
            render: (_, row) => (
                <input
                    type="checkbox"
                    aria-label={`Select invoice ${row.id}`}
                    checked={isSelected(row.id)}
                    onChange={() => toggleSelect(row.id)}
                    onClick={(event) => event.stopPropagation()}
                />
            ),
        },
        { key: 'bridge_transaction_id', label: 'Bridge TX ID' },
        { key: 'transaction_id', label: 'POS TX ID' },
        { key: 'merchant_code', label: 'Merchant' },
        { key: 'branch_code', label: 'Branch' },
        {
            key: 'processing_status',
            label: 'Processing',
            render: (status) => <StatusBadge status={status} />,
        },
        {
            key: 'eis_status',
            label: 'EIS Status',
            render: (status) => <StatusBadge status={status ?? 'pending'} />,
        },
        {
            key: 'actions',
            label: 'Actions',
            sortable: false,
            render: (_, row) => (
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        className="font-medium text-blue-600 hover:text-blue-800"
                        onClick={(event) => {
                            event.stopPropagation();
                            openDrawer(row);
                        }}
                    >
                        View
                    </button>
                    <Link
                        to={`/invoices/${row.id}`}
                        className="text-slate-600 hover:text-slate-800"
                        onClick={(event) => event.stopPropagation()}
                    >
                        Detail
                    </Link>
                </div>
            ),
        },
    ];

    return (
        <div className="bg-white p-6 rounded-lg border border-slate-200">
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-800">Invoices</h1>
                    {merchantCodeFilter && (
                        <p className="mt-1 text-sm text-slate-500">
                            Filtered by merchant: {merchantCodeFilter}
                        </p>
                    )}
                </div>
                <Link
                    to="/invoices/search"
                    className="text-sm font-medium text-blue-600 hover:text-blue-800"
                >
                    Advanced Search
                </Link>
            </div>

            <div className="mb-4">
                <SearchBar
                    value={search}
                    onChange={(value) => {
                        setSearch(value);
                        setPage(1);
                    }}
                    placeholder="Search invoices…"
                />
            </div>

            {isError && (
                <p className="mb-4 text-sm text-red-600">Failed to load invoices. Please try again.</p>
            )}

            <InvoiceBulkToolbar
                selectedIds={selectedIds}
                onClear={clear}
                onSuccess={() => {
                    queryClient.invalidateQueries({ queryKey: ['invoices'] });
                }}
            />

            <DataTable
                columns={columns}
                data={filteredRows}
                loading={isLoading}
                rowKey="id"
                onRow={(record) => ({
                    onClick: () => openDrawer(record),
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

            {drawerOpen && (
                <div className="fixed inset-0 z-50 flex justify-end">
                    <button
                        type="button"
                        className="absolute inset-0 bg-slate-900/40"
                        aria-label="Close invoice detail"
                        onClick={() => setDrawerOpen(false)}
                    />
                    <div className="relative flex h-full w-full max-w-xl flex-col bg-white shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <h2 className="text-lg font-semibold text-slate-800">
                                {selectedId ? `Invoice #${selectedId}` : 'Invoice'}
                            </h2>
                            <button
                                type="button"
                                onClick={() => setDrawerOpen(false)}
                                className="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                            >
                                ✕
                            </button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            {selectedId && (
                                <InvoiceDetailDrawer
                                    invoiceId={selectedId}
                                    onRetrySuccess={() => refetch()}
                                />
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
