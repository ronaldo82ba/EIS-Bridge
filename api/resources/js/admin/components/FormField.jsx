import { Form, Input, Select } from 'antd';

export default function FormField({
    name,
    label,
    type = 'text',
    required = false,
    options = [],
    ...rest
}) {
    let control = <Input {...rest} />;

    if (type === 'password') {
        control = <Input.Password {...rest} />;
    }

    if (type === 'textarea') {
        control = <Input.TextArea rows={4} {...rest} />;
    }

    if (type === 'select') {
        control = <Select options={options} {...rest} />;
    }

    return (
        <Form.Item
            name={name}
            label={label}
            rules={required ? [{ required: true, message: `${label} is required` }] : []}
        >
            {control}
        </Form.Item>
    );
}
