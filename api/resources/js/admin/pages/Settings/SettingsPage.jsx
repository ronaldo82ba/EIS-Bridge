import { Tabs, Typography } from 'antd';
import RoleManagement from './RoleManagement';
import UserList from './UserList';

export default function SettingsPage() {
    const tabItems = [
        { key: 'users', label: 'Users', children: <UserList /> },
        { key: 'roles', label: 'Roles', children: <RoleManagement /> },
    ];

    return (
        <>
            <Typography.Title level={2}>Settings</Typography.Title>
            <Tabs items={tabItems} />
        </>
    );
}
