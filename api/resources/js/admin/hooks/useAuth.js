import { useAuthStore } from '../store/authStore';

export function useAuth() {
    const user = useAuthStore((state) => state.user);
    const token = useAuthStore((state) => state.token);
    const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
    const isLoading = useAuthStore((state) => state.isLoading);
    const setAuth = useAuthStore((state) => state.setAuth);
    const setUser = useAuthStore((state) => state.setUser);
    const setLoading = useAuthStore((state) => state.setLoading);
    const logout = useAuthStore((state) => state.logout);

    return {
        user,
        token,
        isAuthenticated,
        isLoading,
        setAuth,
        setUser,
        setLoading,
        logout,
        role: user?.role ?? null,
    };
}
