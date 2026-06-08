import { Button, Typography } from 'antd';
import DataTable from '../../components/DataTable';

const columns = [
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Email', dataIndex: 'email', key: 'email' },
    { title: 'Role', dataIndex: 'role', key: 'role' },
];

export default function UserList() {
    return (
        <>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
                <Typography.Title level={4} style={{ margin: 0 }}>
                    Users
                </Typography.Title>
                <Button type="primary">Add User</Button>
            </div>
            <DataTable columns={columns} dataSource={[]} loading={false} />
        </>
    );
}
