import { Typography } from 'antd';
import DataTable from '../../components/DataTable';
import { PHASE1_MOCK } from '../../config/phase1';

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
            {PHASE1_MOCK && (
                <Typography.Paragraph type="secondary">
                    Roles are currently rendered from the local scaffold until settings APIs are connected.
                </Typography.Paragraph>
            )}
            <DataTable columns={columns} dataSource={roles} loading={false} pagination={false} />
        </>
    );
}
