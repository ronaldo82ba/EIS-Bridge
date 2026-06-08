import { Tabs } from 'antd';
import BillingSummary from './BillingSummary';
import LicensePlans from './LicensePlans';

export default function BillingPage() {
    const items = [
        { key: 'summary', label: 'Summary', children: <BillingSummary /> },
        { key: 'plans', label: 'License Plans', children: <LicensePlans /> },
    ];

    return <Tabs defaultActiveKey="summary" items={items} />;
}
