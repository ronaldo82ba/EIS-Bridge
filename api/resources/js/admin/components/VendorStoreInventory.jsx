import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { toastError, toastSuccess } from '../components/Toast';
import { storeService } from '../services/storeService';

const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

const emptyForm = {
    external_id: '',
    name: '',
    sku: '',
    category: '',
    brand: '',
    price: '',
    in_stock: true,
};

export default function VendorStoreInventory({ vendorId }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState(emptyForm);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['vendors', vendorId, 'store-inventory'],
        queryFn: async () => (await storeService.getInventory(vendorId)).data,
        enabled: Boolean(vendorId),
    });

    const useMainList = data?.meta?.use_main_online_store_product_list ?? true;
    const items = data?.data ?? [];

    const settingsMutation = useMutation({
        mutationFn: (nextValue) =>
            storeService.updateSettings(vendorId, {
                use_main_online_store_product_list: nextValue,
            }),
        onSuccess: () => {
            toastSuccess('Online store settings saved');
            queryClient.invalidateQueries({ queryKey: ['vendors', vendorId, 'store-inventory'] });
        },
        onError: (error) =>
            toastError(error.response?.data?.message ?? 'Failed to save online store settings'),
    });

    const createMutation = useMutation({
        mutationFn: (payload) => storeService.createInventoryItem(vendorId, payload),
        onSuccess: () => {
            toastSuccess('Inventory item added');
            setForm(emptyForm);
            queryClient.invalidateQueries({ queryKey: ['vendors', vendorId, 'store-inventory'] });
        },
        onError: (error) =>
            toastError(error.response?.data?.message ?? 'Failed to add inventory item'),
    });

    const deleteMutation = useMutation({
        mutationFn: (itemId) => storeService.deleteInventoryItem(vendorId, itemId),
        onSuccess: () => {
            toastSuccess('Inventory item removed');
            queryClient.invalidateQueries({ queryKey: ['vendors', vendorId, 'store-inventory'] });
        },
        onError: (error) =>
            toastError(error.response?.data?.message ?? 'Failed to remove inventory item'),
    });

    const handleSubmit = (event) => {
        event.preventDefault();

        createMutation.mutate({
            ...form,
            price: Number(form.price),
        });
    };

    if (isLoading) {
        return <div className="text-sm text-slate-500">Loading store inventory…</div>;
    }

    if (isError) {
        return (
            <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Failed to load store inventory.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="font-medium text-slate-800">Online Store</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Manage store inventory and choose which product list the online store displays.
                    </p>
                </div>
                <a
                    href={`/store/?vendor=${vendorId}`}
                    target="_blank"
                    rel="noreferrer"
                    className="text-sm font-medium text-blue-600 hover:text-blue-800"
                >
                    Open online store
                </a>
            </div>

            <label className="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <input
                    type="checkbox"
                    checked={useMainList}
                    onChange={(event) => settingsMutation.mutate(event.target.checked)}
                    disabled={settingsMutation.isPending}
                />
                Use main Online Store product list
            </label>

            {!useMainList && (
                <p className="text-sm text-slate-500">
                    The online store shows only items in this vendor&apos;s store inventory.
                </p>
            )}

            <div className="overflow-x-auto rounded-lg border border-slate-200">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-slate-50">
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Name</th>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">SKU</th>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Category</th>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Price</th>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Stock</th>
                            <th className="px-3 py-2 text-left font-medium text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-3 py-4 text-slate-500">
                                    No store inventory items yet.
                                </td>
                            </tr>
                        ) : (
                            items.map((item) => (
                                <tr key={item.id} className="border-b border-slate-100">
                                    <td className="px-3 py-2">{item.name}</td>
                                    <td className="px-3 py-2 font-mono text-xs">{item.sku}</td>
                                    <td className="px-3 py-2">{item.category}</td>
                                    <td className="px-3 py-2">₱{item.price.toLocaleString()}</td>
                                    <td className="px-3 py-2">{item.in_stock ? 'In stock' : 'Out of stock'}</td>
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            className="text-sm text-red-600 hover:text-red-800 disabled:opacity-50"
                                            onClick={() => deleteMutation.mutate(item.id)}
                                            disabled={deleteMutation.isPending}
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
                <h3 className="font-medium text-slate-800">Add inventory item</h3>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label className="block text-sm text-slate-600">
                        Product ID
                        <input
                            className={`${inputClass} mt-1`}
                            value={form.external_id}
                            onChange={(event) => setForm({ ...form, external_id: event.target.value })}
                            required
                        />
                    </label>

                    <label className="block text-sm text-slate-600">
                        Name
                        <input
                            className={`${inputClass} mt-1`}
                            value={form.name}
                            onChange={(event) => setForm({ ...form, name: event.target.value })}
                            required
                        />
                    </label>

                    <label className="block text-sm text-slate-600">
                        SKU
                        <input
                            className={`${inputClass} mt-1`}
                            value={form.sku}
                            onChange={(event) => setForm({ ...form, sku: event.target.value })}
                            required
                        />
                    </label>

                    <label className="block text-sm text-slate-600">
                        Category
                        <input
                            className={`${inputClass} mt-1`}
                            value={form.category}
                            onChange={(event) => setForm({ ...form, category: event.target.value })}
                            required
                        />
                    </label>

                    <label className="block text-sm text-slate-600">
                        Brand
                        <input
                            className={`${inputClass} mt-1`}
                            value={form.brand}
                            onChange={(event) => setForm({ ...form, brand: event.target.value })}
                            required
                        />
                    </label>

                    <label className="block text-sm text-slate-600">
                        Price (PHP)
                        <input
                            className={`${inputClass} mt-1`}
                            type="number"
                            min="0"
                            value={form.price}
                            onChange={(event) => setForm({ ...form, price: event.target.value })}
                            required
                        />
                    </label>
                </div>

                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={form.in_stock}
                        onChange={(event) => setForm({ ...form, in_stock: event.target.checked })}
                    />
                    In stock
                </label>

                <button
                    type="submit"
                    className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    disabled={createMutation.isPending}
                >
                    {createMutation.isPending ? 'Adding…' : 'Add item'}
                </button>
            </form>
        </div>
    );
}
