import { Alert, Button, Empty, Popconfirm, Space, Typography, message } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import DataTable from '../../components/DataTable';
import { usePagination } from '../../hooks/usePagination';
import { queueService } from '../../services/queueService';
import { extractPaginated } from '../../utils/pagination';

export default function FailedJobs() {
    const queryClient = useQueryClient();
    const { page, perPage, params, setPage } = usePagination();

    const { data, isLoading, isError } = useQuery({
        queryKey: ['jobs', 'failed', params],
        queryFn: async () => extractPaginated(await queueService.failedJobs(params)),
    });

    const retryMutation = useMutation({
        mutationFn: (id) => queueService.retryJob(id),
        onSuccess: () => {
            message.success('Job queued for retry');
            queryClient.invalidateQueries({ queryKey: ['jobs', 'failed'] });
            queryClient.invalidateQueries({ queryKey: ['queues', 'status'] });
        },
        onError: (error) => message.error(error.response?.data?.message ?? 'Retry failed'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => queueService.deleteJob(id),
        onSuccess: () => {
            message.success('Failed job deleted');
            queryClient.invalidateQueries({ queryKey: ['jobs', 'failed'] });
            queryClient.invalidateQueries({ queryKey: ['queues', 'status'] });
        },
        onError: (error) => message.error(error.response?.data?.message ?? 'Delete failed'),
    });

    const columns = [
        { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
        { title: 'UUID', dataIndex: 'uuid', key: 'uuid', ellipsis: true },
        { title: 'Queue', dataIndex: 'queue', key: 'queue' },
        { title: 'Connection', dataIndex: 'connection', key: 'connection' },
        {
            title: 'Job',
            dataIndex: 'display_name',
            key: 'display_name',
            ellipsis: true,
            render: (text, record) => text ?? record.job_class ?? '—',
        },
        {
            title: 'Exception',
            dataIndex: 'exception',
            key: 'exception',
            ellipsis: true,
            render: (text) => text?.split('\n')[0] ?? text,
        },
        { title: 'Failed at', dataIndex: 'failed_at', key: 'failed_at' },
        {
            title: 'Actions',
            key: 'actions',
            render: (_, record) => (
                <Space>
                    <Button
                        size="small"
                        loading={retryMutation.isPending}
                        onClick={() => retryMutation.mutate(record.id)}
                    >
                        Retry
                    </Button>
                    <Popconfirm
                        title="Delete this failed job?"
                        onConfirm={() => deleteMutation.mutate(record.id)}
                    >
                        <Button size="small" danger loading={deleteMutation.isPending}>
                            Delete
                        </Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <>
            <Typography.Title level={4}>Failed Jobs</Typography.Title>

            {isError && (
                <Alert
                    type="error"
                    message="Failed to load failed jobs"
                    description="TODO: backend GET /admin/jobs/failed"
                    showIcon
                    style={{ marginBottom: 16 }}
                />
            )}

            <DataTable
                columns={columns}
                dataSource={data?.data ?? []}
                loading={isLoading}
                locale={{
                    emptyText: <Empty description="No failed jobs — pipeline healthy" />,
                }}
                pagination={{
                    current: page,
                    pageSize: perPage,
                    total: data?.pagination?.total ?? 0,
                    onChange: setPage,
                }}
            />
        </>
    );
}
