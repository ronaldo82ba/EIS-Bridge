import { Alert, Button, Form, Typography, message } from 'antd';
import { useMutation } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import FormField from '../../components/FormField';
import { vendorService } from '../../services/vendorService';

export default function VendorCreate() {
    const navigate = useNavigate();
    const [form] = Form.useForm();

    const createMutation = useMutation({
        mutationFn: (values) => vendorService.create(values),
        onSuccess: (response) => {
            message.success('Vendor created');
            navigate(`/vendors/${response.data.id}`);
        },
        onError: (error) => {
            message.error(error.response?.data?.message ?? 'Failed to create vendor');
        },
    });

    return (
        <>
            <Typography.Title level={2}>Create Vendor</Typography.Title>

            {createMutation.isError && (
                <Alert
                    type="error"
                    message="Create failed"
                    description={createMutation.error?.response?.data?.message ?? 'Admin vendor API not available.'}
                    showIcon
                    style={{ marginBottom: 16 }}
                />
            )}

            <Form
                form={form}
                layout="vertical"
                style={{ maxWidth: 480 }}
                onFinish={(values) => createMutation.mutate(values)}
            >
                <FormField name="name" label="Vendor name" required />
                <FormField name="webhook_url" label="Webhook URL" />
                <Button type="primary" htmlType="submit" loading={createMutation.isPending}>
                    Save
                </Button>
                <Link to="/vendors" style={{ marginLeft: 8 }}>
                    Cancel
                </Link>
            </Form>
        </>
    );
}
