import { useQuery } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import DataTable from '../../components/DataTable';
import Pagination from '../../components/Pagination';
import StatusBadge from '../../components/StatusBadge';
import { usePagination } from '../../hooks/usePagination';
import { vendorService } from '../../services/vendorService';
import { webhookService } from '../../services/webhookService';
import { extractPaginated } from '../../utils/pagination';

function formatTimestamp(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

function truncateResponse(body, maxLength = 80) {
    if (!body) {
        return '—';
    }

    const text = String(body);
    return text.length > maxLength ? `${text.slice(0, maxLength)}…` : text;
}

function deliveryStatus(row) {
    if (row.success === true) {
        return 'success';
    }
    if (row.success === false) {
        return 'failed';
    }
    if (row.status_code >= 200 && row.status_code < 300) {
        return 'success';
    }
    if (row.status_code) {
        return 'failed';
    }
    return 'pending';
}

export default function WebhooksPage() {
    const navigate = useNavigate();
    const { page, perPage, params, setPage } = usePagination();
    const [vendorFilter, setVendorFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [merchantFilter, setMerchantFilter] = useState('');

    const queryParams = useMemo(
        () => ({
            ...params,
            ...(vendorFilter ? { vendor_id: vendorFilter } : {}),
            ...(statusFilter === 'success' ? { success: 1 } : {}),
            ...(statusFilter === 'failed' ? { success: 0 } : {}),
        }),
        [params, vendorFilter, statusFilter],
    );

    const { data, isLoading, isError } = useQuery({
        queryKey: ['webhooks', 'deliveries', queryParams],
        queryFn: async () =>
            extractPaginated(await webhookService.listDeliveries(queryParams)),
    });

    const { data: vendorsData } = useQuery({
        queryKey: ['vendors', 'filter-options'],
        queryFn: async () => (await vendorService.list({ per_page: 100 })).data,
    });

    const vendorOptions = vendorsData?.data ?? [];

    const filteredRows = useMemo(() => {
        const rows = data?.data ?? [];
        if (!merchantFilter.trim()) {
            return rows;
        }

        const needle = merchantFilter.trim().toLowerCase();
        return rows.filter((row) => {
            const merchant =
                row.invoice?.merchant_code ??
                row.merchant_code ??
                row.merchant?.name ??
                row.merchant?.merchant_code ??
                '';
            return String(merchant).toLowerCase().includes(needle);
        });
    }, [data?.data, merchantFilter]);

    const columns = [
        { key: 'id', label: 'ID' },
        {
            key: 'merchant',
            label: 'Merchant',
            sortable: false,
            render: (_, row) =>
                row.invoice?.merchant_code ??
                row.merchant?.name ??
                row.merchant?.merchant_code ??
                (row.invoice_id ? `Invoice #${row.invoice_id}` : '—'),
        },
        {
            key: 'vendor',
            label: 'Vendor',
            render: (_, row) => row.vendor?.name ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            render: (_, row) => (
                <StatusBadge
                    status={deliveryStatus(row)}
                    label={
                        row.status_code
                            ? `${deliveryStatus(row)} (${row.status_code})`
                            : deliveryStatus(row)
                    }
                />
            ),
        },
        { key: 'attempt', label: 'Attempts' },
        {
            key: 'response_body',
            label: 'Last response',
            sortable: false,
            render: (value, row) => truncateResponse(value ?? row.response_body),
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
            render: (_, row) =>
                row.vendor_id ? (
                    <Link
                        to={`/vendors/${row.vendor_id}/webhooks`}
                        className="font-medium text-blue-600 hover:text-blue-800"
                        onClick={(event) => event.stopPropagation()}
                    >
                        Config
                    </Link>
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <div className="rounded-lg border border-slate-200 bg-white p-6">
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-800">Webhooks</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Delivery history across vendors. Click a row to open vendor webhook config.
                </p>
            </div>

            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                <label className="flex flex-col gap-1 text-sm text-slate-600">
                    Vendor
                    <select
                        value={vendorFilter}
                        onChange={(event) => {
                            setVendorFilter(event.target.value);
                            setPage(1);
                        }}
                        className="min-w-[180px] rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                    >
                        <option value="">All vendors</option>
                        {vendorOptions.map((vendor) => (
                            <option key={vendor.id} value={vendor.id}>
                                {vendor.name}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="flex flex-col gap-1 text-sm text-slate-600">
                    Status
                    <select
                        value={statusFilter}
                        onChange={(event) => {
                            setStatusFilter(event.target.value);
                            setPage(1);
                        }}
                        className="min-w-[140px] rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                    >
                        <option value="">All statuses</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                    </select>
                </label>

                <label className="flex flex-col gap-1 text-sm text-slate-600">
                    Merchant
                    <input
                        type="search"
                        value={merchantFilter}
                        onChange={(event) => setMerchantFilter(event.target.value)}
                        placeholder="Filter by merchant…"
                        className="min-w-[200px] rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                    />
                </label>
            </div>

            {isError && (
                <p className="mb-4 text-sm text-red-600">
                    Failed to load webhook deliveries. Please try again.
                </p>
            )}

            <DataTable
                columns={columns}
                data={filteredRows}
                loading={isLoading}
                rowKey="id"
                emptyMessage="No webhook deliveries found"
                onRow={(record) =>
                    record.vendor_id
                        ? {
                              onClick: () => navigate(`/vendors/${record.vendor_id}/webhooks`),
                              style: { cursor: 'pointer' },
                          }
                        : {}
                }
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
