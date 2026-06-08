import { Button, Descriptions, Space, Tabs, Timeline, Typography, message } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import JsonViewer from '../../components/JsonViewer';
import StatusBadge from '../../components/StatusBadge';
import { invoiceService } from '../../services/invoiceService';
import { maskSensitiveJson } from '../../utils/maskJson';
import InvoiceRetryModal from './InvoiceRetryModal';

export default function InvoiceDetailDrawer({ invoiceId, onRetrySuccess }) {
    const [retryOpen, setRetryOpen] = useState(false);
    const queryClient = useQueryClient();

    const { data: invoice, isLoading } = useQuery({
        queryKey: ['invoices', invoiceId],
        queryFn: async () => (await invoiceService.get(invoiceId)).data,
        enabled: !!invoiceId,
    });

    const retryMutation = useMutation({
        mutationFn: () => invoiceService.retry(invoiceId),
        onSuccess: () => {
            message.success('Invoice queued for retry');
            setRetryOpen(false);
            queryClient.invalidateQueries({ queryKey: ['invoices', invoiceId] });
            onRetrySuccess?.();
        },
        onError: (error) => message.error(error.response?.data?.message ?? 'Retry failed'),
    });

    const canRetry =
        invoice?.processing_status === 'rejected' || invoice?.processing_status === 'failed';

    const tabItems = [
        {
            key: 'overview',
            label: 'Overview',
            children: (
                <Descriptions bordered column={1} size="small">
                    <Descriptions.Item label="Bridge TX ID">{invoice?.bridge_transaction_id}</Descriptions.Item>
                    <Descriptions.Item label="POS TX ID">{invoice?.transaction_id}</Descriptions.Item>
                    <Descriptions.Item label="Merchant">{invoice?.merchant_code}</Descriptions.Item>
                    <Descriptions.Item label="Branch">{invoice?.branch_code}</Descriptions.Item>
                    <Descriptions.Item label="Device">{invoice?.pos_device_id}</Descriptions.Item>
                    <Descriptions.Item label="Processing">
                        <StatusBadge status={invoice?.processing_status} />
                    </Descriptions.Item>
                    <Descriptions.Item label="EIS status">
                        <StatusBadge status={invoice?.eis_status ?? 'queued'} />
                    </Descriptions.Item>
                    <Descriptions.Item label="EIS reference">{invoice?.eis_reference_no ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Created">{invoice?.created_at}</Descriptions.Item>
                </Descriptions>
            ),
        },
        {
            key: 'raw',
            label: 'Raw POS',
            children: <JsonViewer data={invoice?.raw_pos_json} />,
        },
        {
            key: 'bir',
            label: 'BIR',
            children: <JsonViewer data={invoice?.bir_json} />,
        },
        {
            key: 'signed',
            label: 'Signed',
            children: <JsonViewer data={maskSensitiveJson(invoice?.signed_json)} />,
        },
        {
            key: 'logs',
            label: 'Transmission Logs',
            children:
                invoice?.transmission_logs?.length > 0 ? (
                    <Timeline
                        items={invoice.transmission_logs.map((log) => ({
                            children: (
                                <>
                                    <Typography.Text strong>{log.event}</Typography.Text>
                                    <br />
                                    <Typography.Text type="secondary">{log.timestamp}</Typography.Text>
                                    {log.metadata && <JsonViewer data={log.metadata} />}
                                </>
                            ),
                        }))}
                    />
                ) : (
                    <Typography.Text type="secondary">No transmission logs.</Typography.Text>
                ),
        },
    ];

    if (isLoading) {
        return <Typography.Text type="secondary">Loading invoice…</Typography.Text>;
    }

    return (
        <>
            <Space style={{ marginBottom: 16 }}>
                <StatusBadge status={invoice?.processing_status} />
                <StatusBadge status={invoice?.eis_status ?? 'queued'} label={invoice?.eis_status ?? 'EIS pending'} />
                {canRetry && (
                    <Button size="small" onClick={() => setRetryOpen(true)}>
                        Retry
                    </Button>
                )}
            </Space>
            <Tabs items={tabItems} />
            <InvoiceRetryModal
                open={retryOpen}
                invoiceId={invoice?.bridge_transaction_id ?? invoiceId}
                loading={retryMutation.isPending}
                onCancel={() => setRetryOpen(false)}
                onConfirm={() => retryMutation.mutate()}
            />
        </>
    );
}
