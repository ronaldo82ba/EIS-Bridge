import { Button, DatePicker, Form, Select, Space, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import DataTable from '../../components/DataTable';
import JsonViewer from '../../components/JsonViewer';
import Pagination from '../../components/Pagination';
import { useAuth } from '../../hooks/useAuth';
import { logService } from '../../services/logService';

const { RangePicker } = DatePicker;

export default function SystemLogs() {
    const { role } = useAuth();
    const [filters, setFilters] = useState({});
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);
    const [expanded, setExpanded] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['logs', 'system', filters, page, perPage],
        queryFn: async () => {
            const response = await logService.system({
                ...filters,
                page,
                per_page: perPage,
            });
            return response.data;
        },
    });

    const columns = [
        { title: 'Level', dataIndex: 'level', key: 'level' },
        { title: 'Message', dataIndex: 'message', key: 'message', ellipsis: true },
        { title: 'Channel', dataIndex: 'channel', key: 'channel' },
        { title: 'Timestamp', dataIndex: 'logged_at', key: 'logged_at' },
        {
            title: 'Context',
            key: 'context',
            render: (_, record) =>
                record.context ? (
                    <Typography.Link onClick={() => setExpanded(record.id)}>View</Typography.Link>
                ) : (
                    '—'
                ),
        },
    ];

    const onFilter = (values) => {
        const next = {};
        if (values.level) next.level = values.level;
        if (values.date_range?.[0]) next.from = values.date_range[0].format('YYYY-MM-DD');
        if (values.date_range?.[1]) next.to = values.date_range[1].format('YYYY-MM-DD');
        setFilters(next);
        setPage(1);
    };

    const handleExport = async () => {
        const response = await logService.export({ type: 'system', ...filters });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = `system-logs-${Date.now()}.csv`;
        link.click();
        window.URL.revokeObjectURL(url);
    };

    const expandedRow = data?.data?.find((row) => row.id === expanded);

    return (
        <>
            <Space style={{ width: '100%', justifyContent: 'space-between', marginBottom: 16 }}>
                <Typography.Title level={4} style={{ margin: 0 }}>System Logs</Typography.Title>
                {role === 'super_admin' && (
                    <Button onClick={handleExport}>Export CSV</Button>
                )}
            </Space>

            <Form layout="inline" onFinish={onFilter} style={{ marginBottom: 16 }}>
                <Form.Item name="level" label="Level">
                    <Select
                        allowClear
                        placeholder="Level"
                        style={{ width: 140 }}
                        options={[
                            { value: 'error', label: 'error' },
                            { value: 'warning', label: 'warning' },
                            { value: 'info', label: 'info' },
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
                    <JsonViewer data={expandedRow.context} title="Log context" />
                </div>
            )}
        </>
    );
}
