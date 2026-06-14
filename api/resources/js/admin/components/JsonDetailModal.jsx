import Modal from './Modal';
import JsonViewer from './JsonViewer';

export default function JsonDetailModal({ open, title, data, onClose }) {
    return (
        <Modal open={open} title={title} onCancel={onClose} footer={null} width={720}>
            <JsonViewer data={data} />
        </Modal>
    );
}
