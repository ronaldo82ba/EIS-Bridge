import { Alert, Descriptions, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import StatusBadge from '../../components/StatusBadge';
import { branchService } from '../../services/branchService';
import DeviceList from './DeviceList';

export default function BranchDetail() {
    const { id } = useParams();

    const { data: branch, isLoading, isError } = useQuery({
        queryKey: ['branches', id],
        queryFn: async () => (await branchService.get(id)).data,
    });

    if (isError) {
        return (
            <Alert
                type="error"
                message="Failed to load branch"
                description="TODO: backend GET /admin/branches/{id}"
                showIcon
            />
        );
    }

    return (
        <>
            <Link to="/branches">← Branches</Link>
            <Typography.Title level={2}>{branch?.name ?? `Branch #${id}`}</Typography.Title>
            <Descriptions bordered column={2} loading={isLoading} style={{ marginBottom: 24 }}>
                <Descriptions.Item label="Code">{branch?.branch_code}</Descriptions.Item>
                <Descriptions.Item label="Status">
                    <StatusBadge status={branch?.status ?? 'active'} />
                </Descriptions.Item>
                <Descriptions.Item label="Merchant">{branch?.merchant_name ?? branch?.merchant?.name}</Descriptions.Item>
                <Descriptions.Item label="Address">{branch?.address ?? '—'}</Descriptions.Item>
            </Descriptions>
            <DeviceList branchId={id} devices={branch?.devices ?? []} loading={isLoading} />
        </>
    );
}
