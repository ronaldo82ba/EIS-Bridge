import { Typography } from 'antd';
import DataTable from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';

const columns = [
    { title: 'Merchant', dataIndex: 'merchant_name', key: 'merchant_name' },
    { title: 'Cert expiry', dataIndex: 'expires_at', key: 'expires_at' },
    {
        title: 'PTT status',
        dataIndex: 'ptt_status',
        key: 'ptt_status',
        render: (status) => <StatusBadge status={status} />,
    },
];

export default function CertificateList() {
    return (
        <>
            <Typography.Title level={2}>Certificates & PTT</Typography.Title>
            <DataTable columns={columns} dataSource={[]} loading={false} />
        </>
    );
}
