import { Typography } from 'antd';
import DataTable from '../../components/DataTable';

const columns = [
    { title: 'Vendor', dataIndex: 'vendor_name', key: 'vendor_name' },
    { title: 'Webhook URL', dataIndex: 'webhook_url', key: 'webhook_url', ellipsis: true },
    { title: 'Last delivery', dataIndex: 'last_delivered_at', key: 'last_delivered_at' },
];

export default function WebhooksPage() {
    return (
        <>
            <Typography.Title level={2}>Webhooks</Typography.Title>
            <DataTable columns={columns} dataSource={[]} loading={false} />
        </>
    );
}
