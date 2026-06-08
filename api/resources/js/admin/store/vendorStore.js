import { create } from 'zustand';

export const useVendorStore = create((set) => ({
    selectedVendor: null,

    setSelectedVendor: (selectedVendor) => set({ selectedVendor }),
    clearSelectedVendor: () => set({ selectedVendor: null }),
}));
