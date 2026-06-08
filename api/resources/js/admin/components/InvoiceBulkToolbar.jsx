import { useMutation } from '@tanstack/react-query';
import { toastError, toastSuccess } from './Toast';
import { invoiceService } from '../services/invoiceService';

const BULK_ACTIONS = [
    { action: 'retry_mapping', label: 'Retry Mapping', variant: 'secondary' },
    { action: 'retry_signing', label: 'Retry Signing', variant: 'secondary' },
    { action: 'retry_transmission', label: 'Retry Transmission', variant: 'secondary' },
    { action: 'force_resign', label: 'Force Re-sign', variant: 'secondary' },
    { action: 'force_retransmit', label: 'Force Retransmit', variant: 'secondary' },
    { action: 'resolve', label: 'Mark Resolved', variant: 'primary' },
];

export default function InvoiceBulkToolbar({ selectedIds, onSuccess, onClear }) {
    const ids = [...selectedIds];

    const bulkMutation = useMutation({
        mutationFn: ({ action, invoiceIds }) => invoiceService.bulk(action, invoiceIds),
        onSuccess: (response, variables) => {
            const queued = response?.data?.data?.queued ?? 0;
            const label = BULK_ACTIONS.find((item) => item.action === variables.action)?.label ?? 'Action';
            toastSuccess(`${label}: ${queued} invoice${queued === 1 ? '' : 's'} queued.`);
            onClear?.();
            onSuccess?.();
        },
        onError: () => {
            toastError('Bulk action failed. Please try again.');
        },
    });

    if (ids.length === 0) {
        return null;
    }

    const runAction = (action) => {
        bulkMutation.mutate({ action, invoiceIds: ids });
    };

    return (
        <div className="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <span className="text-sm font-medium text-slate-700">
                {ids.length} selected
            </span>
            <div className="flex flex-wrap gap-2">
                {BULK_ACTIONS.map(({ action, label, variant }) => (
                    <button
                        key={action}
                        type="button"
                        disabled={bulkMutation.isPending}
                        className={variant === 'primary' ? 'btn-primary' : 'btn-secondary'}
                        onClick={() => runAction(action)}
                    >
                        {label}
                    </button>
                ))}
            </div>
            <button
                type="button"
                className="ml-auto text-sm text-slate-600 hover:text-slate-800"
                onClick={onClear}
                disabled={bulkMutation.isPending}
            >
                Clear selection
            </button>
        </div>
    );
}
