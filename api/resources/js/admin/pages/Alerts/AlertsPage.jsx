import { Alert, Button, Card, Col, Row, Select, Space, Tag, Typography } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import DataTable from '../../components/DataTable';
import Pagination from '../../components/Pagination';
import { alertService } from '../../services/alertService';

const severityColors = {
    critical: 'red',
    warning: 'orange',
    info: 'blue',
};

export default function AlertsPage() {
    const queryClient = useQueryClient();
    const [filters, setFilters] = useState({ active_only: true });
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);

    const { data: summary } = useQuery({
        queryKey: ['alerts', 'summary'],
        queryFn: async () => {
            const response = await alertService.summary();
            return response.data;
        },
        refetchInterval: 60_000,
    });

    const { data, isLoading, isError } = useQuery({
        queryKey: ['alerts', filters, page, perPage],
        queryFn: async () => {
            const response = await alertService.list({
                ...filters,
                page,
                per_page: perPage,
            });
            return response.data;
        },
    });

    const acknowledgeMutation = useMutation({
        mutationFn: (id) => alertService.acknowledge(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['alerts'] });
        },
    });

    const resolveMutation = useMutation({
        mutationFn: (id) => alertService.resolve(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['alerts'] });
        },
    });

    const columns = [
        {
            title: 'Severity',
            dataIndex: 'severity',
            key: 'severity',
            render: (value) => <Tag color={severityColors[value] ?? 'default'}>{value}</Tag>,
        },
        { title: 'Type', dataIndex: 'type', key: 'type' },
        { title: 'Title', dataIndex: 'title', key: 'title' },
        { title: 'Message', dataIndex: 'message', key: 'message', ellipsis: true },
        { title: 'Created', dataIndex: 'created_at', key: 'created_at' },
        {
            title: 'Actions',
            key: 'actions',
            render: (_, record) => (
                <Space>
                    {!record.acknowledged_at && (
                        <Button
                            size="small"
                            onClick={() => acknowledgeMutation.mutate(record.id)}
                            loading={acknowledgeMutation.isPending}
                        >
                            Acknowledge
                        </Button>
                    )}
                    {!record.resolved_at && (
                        <Button
                            size="small"
                            type="primary"
                            onClick={() => resolveMutation.mutate(record.id)}
                            loading={resolveMutation.isPending}
                        >
                            Resolve
                        </Button>
                    )}
                </Space>
            ),
        },
    ];

    return (
        <>
            <Typography.Title level={2}>Alerts</Typography.Title>

            {isError && (
                <Alert type="error" message="Failed to load alerts" showIcon style={{ marginBottom: 16 }} />
            )}

            <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
                <Col xs={24} sm={8}>
                    <Card>
                        <Typography.Text type="secondary">Active</Typography.Text>
                        <Typography.Title level={3}>{summary?.total_active ?? 0}</Typography.Title>
                    </Card>
                </Col>
                <Col xs={24} sm={8}>
                    <Card>
                        <Typography.Text type="secondary">Critical</Typography.Text>
                        <Typography.Title level={3} style={{ color: '#cf1322' }}>
                            {summary?.by_severity?.critical ?? 0}
                        </Typography.Title>
                    </Card>
                </Col>
                <Col xs={24} sm={8}>
                    <Card>
                        <Typography.Text type="secondary">Unacknowledged</Typography.Text>
                        <Typography.Title level={3}>{summary?.unacknowledged ?? 0}</Typography.Title>
                    </Card>
                </Col>
            </Row>

            <Space style={{ marginBottom: 16 }}>
                <Select
                    placeholder="Severity"
                    allowClear
                    style={{ width: 160 }}
                    onChange={(value) => {
                        setFilters((prev) => ({ ...prev, severity: value }));
                        setPage(1);
                    }}
                    options={[
                        { value: 'critical', label: 'critical' },
                        { value: 'warning', label: 'warning' },
                        { value: 'info', label: 'info' },
                    ]}
                />
                <Select
                    placeholder="Type"
                    allowClear
                    style={{ width: 200 }}
                    onChange={(value) => {
                        setFilters((prev) => ({ ...prev, type: value }));
                        setPage(1);
                    }}
                    options={[
                        { value: 'certificate_expiring', label: 'certificate_expiring' },
                        { value: 'ptt_expiring', label: 'ptt_expiring' },
                        { value: 'high_error_rate', label: 'high_error_rate' },
                        { value: 'queue_backlog', label: 'queue_backlog' },
                    ]}
                />
            </Space>

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
