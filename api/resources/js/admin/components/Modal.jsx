import { Modal as AntModal } from 'antd';

export default function Modal({ open, title, children, onCancel, onOk, confirmLoading, ...rest }) {
    return (
        <AntModal
            open={open}
            title={title}
            onCancel={onCancel}
            onOk={onOk}
            confirmLoading={confirmLoading}
            destroyOnHidden
            {...rest}
        >
            {children}
        </AntModal>
    );
}
