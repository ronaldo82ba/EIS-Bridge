import { Button, DatePicker, Form, Input, Select, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import DataTable from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import Pagination from '../../components/Pagination';
import { logService } from '../../services/logService';

const { RangePicker } = DatePicker;

export default function WebhookLogs() {
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);

    const { data, isLoading } = useQuery({
        queryKey: ['logs', 'webhooks', filters, page, perPage],
        queryFn: async () => {
            const response = await logService.webhooks({
                ...filters,
                page,
                per_page: perPage,
            });
            return response.data;
        },
    });

    const columns = [
        { title: 'Vendor', dataIndex: ['vendor', 'name'], key: 'vendor' },
        { title: 'Event', dataIndex: 'event', key: 'event' },
        { title: 'Status', dataIndex: 'status_code', key: 'status_code' },
        {
            title: 'Success',
            dataIndex: 'success',
            key: 'success',
            render: (value) => <StatusBadge status={value ? 'success' : 'failed'} />,
        },
        { title: 'Attempt', dataIndex: 'attempt', key: 'attempt' },
        { title: 'Delivered', dataIndex: 'delivered_at', key: 'delivered_at' },
    ];

    const onFilter = (values) => {
        const next = {};
        if (values.vendor_id) next.vendor_id = values.vendor_id;
        if (values.status_code) next.status_code = values.status_code;
        if (values.date_range?.[0]) next.from = values.date_range[0].format('YYYY-MM-DD');
        if (values.date_range?.[1]) next.to = values.date_range[1].format('YYYY-MM-DD');
        setFilters(next);
        setPage(1);
    };

    return (
        <>
            <Typography.Title level={4}>Webhook Delivery Logs</Typography.Title>

            <Form layout="inline" onFinish={onFilter} style={{ marginBottom: 16 }}>
                <Form.Item name="vendor_id" label="Vendor ID">
                    <Input placeholder="Vendor ID" style={{ width: 120 }} />
                </Form.Item>
                <Form.Item name="status_code" label="HTTP">
                    <Select
                        allowClear
                        placeholder="Status"
                        style={{ width: 120 }}
                        options={[
                            { value: 200, label: '200' },
                            { value: 500, label: '500' },
                        ]}
                    />
                </Form.Item>
                <Form.Item name="date_range" label="Date">
                    <RangePicker />
                </Form.Item>
                <Form.Item>
                    <Button type="primary" htmlType="submit">Filter</Button>
                </Form.Item>
            </Form>

            <DataTable
                columns={columns}
                dataSource={data?.data ?? []}
                loading={isLoading}
                pagination={false}
            />

            <Pagination
                current={data?.current_page ?? page}
                pageSize={data?.per_page ?? perPage}
                total={data?.total ?? 0}
                onChange={(nextPage, nextSize) => {
                    setPage(nextPage);
                    setPerPage(nextSize);
                }}
            />
        </>
    );
}
