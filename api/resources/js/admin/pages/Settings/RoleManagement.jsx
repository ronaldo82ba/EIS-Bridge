import { Typography } from 'antd';
import DataTable from '../../components/DataTable';

const columns = [
    { title: 'Role', dataIndex: 'role', key: 'role' },
    { title: 'Description', dataIndex: 'description', key: 'description' },
];

const roles = [
    { id: 1, role: 'super_admin', description: 'Full platform access' },
    { id: 2, role: 'vendor_admin', description: 'Scoped to own vendor' },
    { id: 3, role: 'support', description: 'Read + operational actions' },
];

export default function RoleManagement() {
    return (
        <>
            <Typography.Title level={4}>Role Management</Typography.Title>
            <DataTable columns={columns} dataSource={roles} loading={false} pagination={false} />
        </>
    );
}
