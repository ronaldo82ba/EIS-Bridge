import { Alert, Button, Form, Typography, message } from 'antd';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { useParams } from 'react-router-dom';
import FormField from '../../components/FormField';
import { vendorService } from '../../services/vendorService';

export default function VendorWebhookConfig() {
    const { id } = useParams();
    const [form] = Form.useForm();

    const { data: vendor, isLoading, isError } = useQuery({
        queryKey: ['vendors', id],
        queryFn: async () => (await vendorService.get(id)).data,
    });

    useEffect(() => {
        if (vendor) {
            form.setFieldsValue({
                webhook_url: vendor.webhook_url ?? '',
                webhook_secret: '',
            });
        }
    }, [vendor, form]);

    const updateMutation = useMutation({
        mutationFn: (values) => vendorService.updateWebhook(id, values),
        onSuccess: () => message.success('Webhook config saved'),
        onError: (error) => message.error(error.response?.data?.message ?? 'Save failed'),
    });

    return (
        <>
            <Typography.Title level={2}>Webhook Config — {vendor?.name ?? `Vendor #${id}`}</Typography.Title>

            {isError && (
                <Alert
                    type="error"
                    message="Failed to load vendor"
                    description="TODO: backend PATCH /admin/webhooks/{vendorId}"
                    showIcon
                    style={{ marginBottom: 16 }}
                />
            )}

            <Form
                form={form}
                layout="vertical"
                style={{ maxWidth: 480 }}
                onFinish={(values) => updateMutation.mutate(values)}
            >
                <FormField name="webhook_url" label="Webhook URL" />
                <FormField
                    name="webhook_secret"
                    label="Webhook Secret"
                    type="password"
                    placeholder={vendor?.webhook_secret_masked ? '••••••••' : 'Enter secret'}
                />
                <Button type="primary" htmlType="submit" loading={updateMutation.isPending || isLoading}>
                    Save
                </Button>
                <Button style={{ marginLeft: 8 }} disabled>
                    Send test ping
                    {/* TODO: backend POST /admin/webhooks/{vendorId}/test */}
                </Button>
            </Form>
        </>
    );
}
