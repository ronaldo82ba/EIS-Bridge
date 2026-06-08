import { Button, DatePicker, Form, Input, Select, Space, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import DataTable from '../../components/DataTable';
import JsonViewer from '../../components/JsonViewer';
import Pagination from '../../components/Pagination';
import { useAuth } from '../../hooks/useAuth';
import { logService } from '../../services/logService';

const { RangePicker } = DatePicker;

export default function AuditLogs() {
    const { role } = useAuth();
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);
    const [expanded, setExpanded] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['logs', 'audit', filters, page, perPage],
        queryFn: async () => {
            const response = await logService.audit({
                ...filters,
                page,
                per_page: perPage,
            });
            return response.data;
        },
    });

    const columns = [
        { title: 'User', dataIndex: 'user_name', key: 'user_name' },
        { title: 'Action', dataIndex: 'action', key: 'action' },
        { title: 'Entity', dataIndex: 'entity_type', key: 'entity_type' },
        { title: 'Entity ID', dataIndex: 'entity_id', key: 'entity_id' },
        { title: 'When', dataIndex: 'created_at', key: 'created_at' },
        {
            title: 'Changes',
            key: 'changes',
            render: (_, record) =>
                record.changes ? (
                    <Typography.Link onClick={() => setExpanded(record.id)}>View</Typography.Link>
                ) : (
                    '—'
                ),
        },
    ];

    const onFilter = (values) => {
        const next = {};
        if (values.user_id) next.user_id = values.user_id;
        if (values.entity_type) next.entity_type = values.entity_type;
        if (values.action) next.action = values.action;
        if (values.date_range?.[0]) next.from = values.date_range[0].format('YYYY-MM-DD');
        if (values.date_range?.[1]) next.to = values.date_range[1].format('YYYY-MM-DD');
        setFilters(next);
        setPage(1);
    };

    const handleExport = async () => {
        const response = await logService.export({ type: 'audit', ...filters });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = `audit-logs-${Date.now()}.csv`;
        link.click();
        window.URL.revokeObjectURL(url);
    };

    const expandedRow = data?.data?.find((row) => row.id === expanded);

    return (
        <>
            <Space style={{ width: '100%', justifyContent: 'space-between', marginBottom: 16 }}>
                <Typography.Title level={4} style={{ margin: 0 }}>Audit Logs</Typography.Title>
                {role === 'super_admin' && (
                    <Button onClick={handleExport}>Export CSV</Button>
                )}
            </Space>

            <Form layout="inline" onFinish={onFilter} style={{ marginBottom: 16 }}>
                <Form.Item name="user_id" label="User ID">
                    <Input placeholder="User ID" style={{ width: 100 }} />
                </Form.Item>
                <Form.Item name="entity_type" label="Entity">
                    <Input placeholder="Entity type" style={{ width: 160 }} />
                </Form.Item>
                <Form.Item name="action" label="Action">
                    <Select
                        allowClear
                        placeholder="Action"
                        style={{ width: 140 }}
                        options={[
                            { value: 'created', label: 'created' },
                            { value: 'updated', label: 'updated' },
                            { value: 'deleted', label: 'deleted' },
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
                    <JsonViewer data={expandedRow.changes} title="Audit changes" />
                </div>
            )}
        </>
    );
}
