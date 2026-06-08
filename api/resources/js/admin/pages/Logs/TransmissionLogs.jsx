import { Button, DatePicker, Form, Input, Select, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import DataTable from '../../components/DataTable';
import JsonViewer from '../../components/JsonViewer';
import Pagination from '../../components/Pagination';
import { logService } from '../../services/logService';

const { RangePicker } = DatePicker;

export default function TransmissionLogs() {
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);
    const [expanded, setExpanded] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['logs', 'transmission', filters, page, perPage],
        queryFn: async () => {
            const response = await logService.transmission({
                ...filters,
                page,
                per_page: perPage,
            });
            return response.data;
        },
    });

    const columns = [
        { title: 'Invoice', dataIndex: ['invoice', 'bridge_transaction_id'], key: 'invoice' },
        { title: 'Event', dataIndex: 'event', key: 'event' },
        { title: 'Timestamp', dataIndex: 'timestamp', key: 'timestamp' },
        {
            title: 'Metadata',
            key: 'metadata',
            render: (_, record) =>
                record.metadata ? (
                    <Typography.Link onClick={() => setExpanded(record.id)}>View</Typography.Link>
                ) : (
                    '—'
                ),
        },
    ];

    const onFilter = (values) => {
        const next = {};
        if (values.invoice_id) next.invoice_id = values.invoice_id;
        if (values.status) next.status = values.status;
        if (values.date_range?.[0]) next.from = values.date_range[0].format('YYYY-MM-DD');
        if (values.date_range?.[1]) next.to = values.date_range[1].format('YYYY-MM-DD');
        setFilters(next);
        setPage(1);
    };

    const expandedRow = data?.data?.find((row) => row.id === expanded);

    return (
        <>
            <Typography.Title level={4}>Transmission Logs</Typography.Title>

            <Form layout="inline" onFinish={onFilter} style={{ marginBottom: 16 }}>
                <Form.Item name="invoice_id" label="Invoice ID">
                    <Input placeholder="Invoice ID" style={{ width: 140 }} />
                </Form.Item>
                <Form.Item name="status" label="Event">
                    <Select
                        allowClear
                        placeholder="Event"
                        style={{ width: 160 }}
                        options={[
                            { value: 'queued', label: 'queued' },
                            { value: 'sent_to_eis', label: 'sent_to_eis' },
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

            {expandedRow && (
                <div style={{ marginTop: 16 }}>
                    <JsonViewer data={expandedRow.metadata} title="Transmission metadata" />
                </div>
            )}
        </>
    );
}
