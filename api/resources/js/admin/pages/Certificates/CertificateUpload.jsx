import { Button, Form, Typography, Upload } from 'antd';
import { useParams } from 'react-router-dom';
import FormField from '../../components/FormField';

export default function CertificateUpload() {
    const { merchantId } = useParams();

    return (
        <>
            <Typography.Title level={2}>Upload Certificate — Merchant #{merchantId}</Typography.Title>
            <Form layout="vertical" style={{ maxWidth: 480 }}>
                <Form.Item name="file" label="Certificate file">
                    <Upload beforeUpload={() => false} maxCount={1}>
                        <Button>Choose file</Button>
                    </Upload>
                </Form.Item>
                <FormField name="password" label="Certificate password" type="password" />
                <Button type="primary">Upload</Button>
            </Form>
        </>
    );
}
