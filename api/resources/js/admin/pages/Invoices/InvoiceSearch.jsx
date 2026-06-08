import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '../../components/DataTable';
import InvoiceBulkToolbar from '../../components/InvoiceBulkToolbar';
import Pagination from '../../components/Pagination';
import SearchBar from '../../components/SearchBar';
import StatusBadge from '../../components/StatusBadge';
import { useInvoiceBulkSelection } from '../../hooks/useInvoiceBulkSelection';
import { usePagination } from '../../hooks/usePagination';
import { invoiceService } from '../../services/invoiceService';
import { merchantService } from '../../services/merchantService';
import { extractPaginated } from '../../utils/pagination';

const STATUS_OPTIONS = [
    { value: '', label: 'All Status' },
    { value: 'pending', label: 'Pending' },
    { value: 'mapped', label: 'Mapped' },
    { value: 'signed', label: 'Signed' },
    { value: 'transmitted', label: 'Transmitted' },
    { value: 'sent', label: 'Sent' },
    { value: 'acknowledged', label: 'Acknowledged' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'retry_failed', label: 'Retry Failed' },
    { value: 'transmission_failed', label: 'Transmission Failed' },
];

const STUCK_OPTIONS = [
    { value: '', label: 'Any stage' },
    { value: 'mapping', label: 'Stuck in mapping' },
    { value: 'signing', label: 'Stuck in signing' },
    { value: 'transmission', label: 'Stuck in transmission' },
];

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export default function InvoiceSearch() {
    const queryClient = useQueryClient();
    const { page, perPage, params, setPage } = usePagination(1, 25);
    const { selectedIds, isSelected, toggleSelect, toggleSelectAll, clear } = useInvoiceBulkSelection();
    const [query, setQuery] = useState('');
    const [debouncedQuery, setDebouncedQuery] = useState('');
    const [filters, setFilters] = useState({
        merchant_id: '',
        status: '',
        date_from: '',
        date_to: '',
        stuck_in: '',
        has_errors: false,
        webhook_failed: false,
    });

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setDebouncedQuery(query.trim());
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [query, setPage]);

    const searchParams = useMemo(() => {
        const next = {
            ...params,
            ...(debouncedQuery ? { q: debouncedQuery } : {}),
            ...(filters.merchant_id ? { merchant_id: filters.merchant_id } : {}),
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.date_from ? { date_from: filters.date_from } : {}),
            ...(filters.date_to ? { date_to: filters.date_to } : {}),
            ...(filters.stuck_in ? { stuck_in: filters.stuck_in } : {}),
            ...(filters.has_errors ? { has_errors: 1 } : {}),
            ...(filters.webhook_failed ? { webhook_failed: 1 } : {}),
        };

        return next;
    }, [params, debouncedQuery, filters]);

    const { data: merchantsData } = useQuery({
        queryKey: ['merchants', 'invoice-search'],
        queryFn: async () => extractPaginated(await merchantService.list({ per_page: 100 })),
    });

    const { data, isLoading, isError } = useQuery({
        queryKey: ['invoices', 'search', searchParams],
        queryFn: async () => extractPaginated(await invoiceService.search(searchParams)),
    });

    const merchants = merchantsData?.data ?? [];
    const rows = data?.data ?? [];
    const rowIds = useMemo(() => rows.map((row) => row.id), [rows]);
    const allPageSelected = rowIds.length > 0 && rowIds.every((id) => isSelected(id));

    const updateFilter = (key, value) => {
        setFilters((current) => ({ ...current, [key]: value }));
        setPage(1);
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
        { key: 'bridge_transaction_id', label: 'Bridge Txn ID' },
        { key: 'transaction_id', label: 'POS Txn ID' },
        {
            key: 'merchant',
            label: 'Merchant',
            render: (_, row) => row.merchant?.name ?? row.merchant_code ?? '—',
        },
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
            key: 'created_at',
            label: 'Created',
            render: (value) => formatDateTime(value),
        },
        {
            key: 'actions',
            label: '',
            sortable: false,
            render: (_, row) => (
                <Link to={`/invoices/${row.id}`} className="font-medium text-blue-600 hover:text-blue-800">
                    View
                </Link>
            ),
        },
    ];

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-800">Invoice Search</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Find invoices by transaction ID, merchant, status, or error state.
                    </p>
                </div>
                <Link to="/invoices" className="text-sm text-slate-600 hover:text-slate-800">
                    ← Back to invoice list
                </Link>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4">
                <SearchBar
                    value={query}
                    onChange={setQuery}
                    placeholder="Search by POS Txn ID, Bridge Txn ID, merchant code, or name…"
                />
            </div>

            <div className="grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-4">
                <label className="block text-sm">
                    <span className="mb-1 block text-slate-600">Merchant</span>
                    <select
                        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        value={filters.merchant_id}
                        onChange={(event) => updateFilter('merchant_id', event.target.value)}
                    >
                        <option value="">All merchants</option>
                        {merchants.map((merchant) => (
                            <option key={merchant.id} value={merchant.id}>
                                {merchant.name} ({merchant.merchant_code})
                            </option>
                        ))}
                    </select>
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block text-slate-600">Status</span>
                    <select
                        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        value={filters.status}
                        onChange={(event) => updateFilter('status', event.target.value)}
                    >
                        {STATUS_OPTIONS.map((option) => (
                            <option key={option.value || 'all'} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block text-slate-600">Date from</span>
                    <input
                        type="date"
                        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        value={filters.date_from}
                        onChange={(event) => updateFilter('date_from', event.target.value)}
                    />
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block text-slate-600">Date to</span>
                    <input
                        type="date"
                        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        value={filters.date_to}
                        onChange={(event) => updateFilter('date_to', event.target.value)}
                    />
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block text-slate-600">Pipeline stage</span>
                    <select
                        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        value={filters.stuck_in}
                        onChange={(event) => updateFilter('stuck_in', event.target.value)}
                    >
                        {STUCK_OPTIONS.map((option) => (
                            <option key={option.value || 'any'} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="flex items-center gap-2 self-end text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={filters.has_errors}
                        onChange={(event) => updateFilter('has_errors', event.target.checked)}
                    />
                    Has errors
                </label>

                <label className="flex items-center gap-2 self-end text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={filters.webhook_failed}
                        onChange={(event) => updateFilter('webhook_failed', event.target.checked)}
                    />
                    Webhook failed
                </label>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
                {isError && (
                    <p className="mb-4 text-sm text-red-600">Failed to search invoices. Please try again.</p>
                )}

                <InvoiceBulkToolbar
                    selectedIds={selectedIds}
                    onClear={clear}
                    onSuccess={() => {
                        queryClient.invalidateQueries({ queryKey: ['invoices', 'search'] });
                    }}
                />

                <DataTable
                    columns={columns}
                    data={rows}
                    loading={isLoading}
                    rowKey="id"
                    emptyMessage="No invoices found"
                />

                <Pagination
                    page={page}
                    perPage={perPage}
                    total={data?.pagination?.total ?? 0}
                    lastPage={data?.pagination?.lastPage}
                    onPageChange={setPage}
                />
            </div>
        </div>
    );
}
