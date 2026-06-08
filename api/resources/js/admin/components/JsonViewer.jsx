import { Typography } from 'antd';

const { Paragraph } = Typography;

export default function JsonViewer({ data, title }) {
    const content =
        typeof data === 'string' ? data : JSON.stringify(data ?? {}, null, 2);

    return (
        <div>
            {title && <Typography.Title level={5}>{title}</Typography.Title>}
            <Paragraph>
                <pre style={{ margin: 0, overflow: 'auto', maxHeight: 480 }}>
                    {content}
                </pre>
            </Paragraph>
        </div>
    );
}
