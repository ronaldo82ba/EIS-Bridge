import { Alert, Tag, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import DataTable from '../../components/DataTable';
import { billingService } from '../../services/billingService';

const columns = [
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Slug', dataIndex: 'slug', key: 'slug' },
    { title: 'Billing Model', dataIndex: 'billing_model', key: 'billing_model' },
    { title: 'Unit', dataIndex: 'unit', key: 'unit', render: (value) => value ?? '—' },
    {
        title: 'Amount',
        key: 'amount',
        render: (_, record) => `₱${Number(record.amount).toFixed(2)} ${record.currency}`,
    },
    {
        title: 'Status',
        dataIndex: 'is_active',
        key: 'is_active',
        render: (active) => <Tag color={active ? 'green' : 'default'}>{active ? 'Active' : 'Inactive'}</Tag>,
    },
];

export default function LicensePlans() {
    const { data, isLoading, isError, refetch } = useQuery({
        queryKey: ['billing', 'plans'],
        queryFn: async () => {
            const response = await billingService.plans();
            return response.data?.data ?? [];
        },
    });

    return (
        <>
            <Typography.Title level={2}>License Plans</Typography.Title>

            {isError && (
                <Alert
                    type="error"
                    message="Failed to load license plans"
                    showIcon
                    action={<Typography.Link onClick={() => refetch()}>Retry</Typography.Link>}
                    style={{ marginBottom: 16 }}
                />
            )}

            <DataTable
                rowKey="id"
                loading={isLoading}
                columns={columns}
                dataSource={data ?? []}
                pagination={false}
            />
        </>
    );
}
