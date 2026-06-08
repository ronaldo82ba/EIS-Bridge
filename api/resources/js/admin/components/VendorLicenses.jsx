import { Alert, Table, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import StatusBadge from './StatusBadge';
import { billingService } from '../services/billingService';

const columns = [
    { title: 'Plan', dataIndex: ['plan', 'name'], key: 'plan' },
    { title: 'Slug', dataIndex: ['plan', 'slug'], key: 'slug' },
    {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        render: (status) => <StatusBadge status={status} />,
    },
    { title: 'Quantity', dataIndex: 'quantity', key: 'quantity' },
    {
        title: 'Amount',
        key: 'amount',
        render: (_, record) => `₱${Number(record.plan?.amount ?? 0).toFixed(2)}`,
    },
];

export default function VendorLicenses({ vendorId }) {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['billing', 'vendor-licenses', vendorId],
        queryFn: async () => {
            const response = await billingService.vendorLicenses(vendorId);
            return response.data?.data ?? [];
        },
        enabled: Boolean(vendorId),
    });

    if (isError) {
        return <Alert type="warning" message="Unable to load vendor licenses." showIcon />;
    }

    return (
        <>
            <Typography.Title level={4}>Licenses</Typography.Title>
            <Table
                rowKey="id"
                loading={isLoading}
                columns={columns}
                dataSource={data ?? []}
                pagination={false}
                locale={{ emptyText: 'No licenses assigned.' }}
            />
        </>
    );
}
