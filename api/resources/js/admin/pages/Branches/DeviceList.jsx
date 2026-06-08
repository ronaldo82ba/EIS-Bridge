import { Button, Form, Select, Typography, message } from 'antd';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import DataTable from '../../components/DataTable';
import FormField from '../../components/FormField';
import StatusBadge from '../../components/StatusBadge';
import { branchService } from '../../services/branchService';

const columns = [
    { title: 'POS Device ID', dataIndex: 'pos_device_id', key: 'pos_device_id' },
    { title: 'Name', dataIndex: 'name', key: 'name' },
    {
        title: 'Status',
        dataIndex: 'status',
        key: 'status',
        render: (status) => <StatusBadge status={status ?? 'active'} />,
    },
    { title: 'Created', dataIndex: 'created_at', key: 'created_at' },
];

export default function DeviceList({ branchId, devices = [], loading = false }) {
    const [form] = Form.useForm();
    const queryClient = useQueryClient();

    const createMutation = useMutation({
        mutationFn: (values) => branchService.createDevice(branchId, values),
        onSuccess: () => {
            message.success('Device registered');
            form.resetFields();
            queryClient.invalidateQueries({ queryKey: ['branches', branchId] });
        },
        onError: (error) => message.error(error.response?.data?.message ?? 'Failed to register device'),
    });

    const lockMutation = useMutation({
        mutationFn: ({ deviceId, status }) => branchService.updateDevice(deviceId, { status }),
        onSuccess: () => {
            message.success('Device status updated');
            queryClient.invalidateQueries({ queryKey: ['branches', branchId] });
        },
        onError: (error) => message.error(error.response?.data?.message ?? 'Update failed'),
    });

    const actionColumns = [
        ...columns,
        {
            title: 'Actions',
            key: 'actions',
            render: (_, record) => (
                <Button
                    size="small"
                    onClick={() =>
                        lockMutation.mutate({
                            deviceId: record.id,
                            status: record.status === 'locked' ? 'active' : 'locked',
                        })
                    }
                >
                    {record.status === 'locked' ? 'Unlock' : 'Lock'}
                </Button>
            ),
        },
    ];

    return (
        <>
            <Typography.Title level={4}>Devices — Branch #{branchId}</Typography.Title>
            <DataTable columns={actionColumns} dataSource={devices} loading={loading} pagination={false} />

            <Typography.Title level={5} style={{ marginTop: 24 }}>
                Register device
            </Typography.Title>
            <Form
                form={form}
                layout="vertical"
                style={{ maxWidth: 480 }}
                onFinish={createMutation.mutate}
            >
                <FormField name="pos_device_id" label="POS device ID" required />
                <FormField name="name" label="Device name" />
                <Form.Item name="status" label="Status" initialValue="active">
                    <Select
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'locked', label: 'Locked' },
                        ]}
                    />
                </Form.Item>
                <Button type="primary" htmlType="submit" loading={createMutation.isPending}>
                    Register
                </Button>
            </Form>
        </>
    );
}
