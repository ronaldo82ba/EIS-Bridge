import { Typography } from 'antd';
import DataTable from '../../components/DataTable';

const columns = [
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Code', dataIndex: 'branch_code', key: 'branch_code' },
    { title: 'Merchant', dataIndex: 'merchant_name', key: 'merchant_name' },
    { title: 'Devices', dataIndex: 'devices_count', key: 'devices_count' },
];

export default function BranchList() {
    return (
        <>
            <Typography.Title level={2}>Branches & Devices</Typography.Title>
            <DataTable columns={columns} dataSource={[]} loading={false} />
        </>
    );
}
