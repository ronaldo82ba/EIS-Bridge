import { Tabs, Typography } from 'antd';
import { useAuth } from '../../hooks/useAuth';
import AuditLogs from './AuditLogs';
import SystemLogs from './SystemLogs';
import TransmissionLogs from './TransmissionLogs';
import WebhookLogs from './WebhookLogs';

export default function LogsPage() {
    const { role } = useAuth();

    const tabItems = [
        { key: 'system', label: 'System', children: <SystemLogs />, roles: ['super_admin', 'support'] },
        { key: 'transmission', label: 'Transmission', children: <TransmissionLogs />, roles: ['super_admin', 'support'] },
        { key: 'webhooks', label: 'Webhooks', children: <WebhookLogs />, roles: ['super_admin', 'support', 'vendor_admin'] },
        { key: 'audit', label: 'Audit', children: <AuditLogs />, roles: ['super_admin', 'support'] },
    ].filter((tab) => !role || tab.roles.includes(role));

    return (
        <>
            <Typography.Title level={2}>Logs & Audit</Typography.Title>
            <Tabs items={tabItems} />
        </>
    );
}
