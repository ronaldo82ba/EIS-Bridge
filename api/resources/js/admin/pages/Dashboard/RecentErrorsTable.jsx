import { Card, Typography } from 'antd';
import { Link } from 'react-router-dom';
import DataTable from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';

const columns = [
    { title: 'Bridge TX ID', dataIndex: 'bridge_transaction_id', key: 'bridge_transaction_id' },
    { title: 'Merchant', dataIndex: 'merchant_code', key: 'merchant_code' },
    {
        title: 'Status',
        dataIndex: 'processing_status',
        key: 'processing_status',
        render: (status) => <StatusBadge status={status} />,
    },
    {
        title: 'Exception',
        dataIndex: 'exception',
        key: 'exception',
        ellipsis: true,
        render: (text, record) => text ?? record.error_message ?? '—',
    },
    { title: 'Failed at', dataIndex: 'failed_at', key: 'failed_at' },
];

export default function RecentErrorsTable({ errors = [], loading }) {
    return (
        <Card
            title="Recent Errors"
            loading={loading}
            extra={<Link to="/invoices?status=rejected">View all</Link>}
        >
            {errors.length === 0 && !loading ? (
                <Typography.Text type="secondary">No recent errors.</Typography.Text>
            ) : (
                <DataTable
                    columns={columns}
                    dataSource={errors}
                    loading={loading}
                    pagination={false}
                    size="small"
                />
            )}
        </Card>
    );
}
