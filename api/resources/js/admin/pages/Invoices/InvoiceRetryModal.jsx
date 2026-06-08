import { Typography } from 'antd';
import Modal from '../../components/Modal';

export default function InvoiceRetryModal({ open, invoiceId, onCancel, onConfirm, loading }) {
    return (
        <Modal
            open={open}
            title="Retry invoice processing"
            onCancel={onCancel}
            onOk={onConfirm}
            confirmLoading={loading}
            okText="Retry"
        >
            <Typography.Paragraph>
                Re-queue invoice <strong>{invoiceId}</strong> for processing?
            </Typography.Paragraph>
        </Modal>
    );
}
