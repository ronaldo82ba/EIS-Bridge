import { useQuery } from '@tanstack/react-query';

export function useFetch(key, fetcher, options = {}) {
    return useQuery({
        queryKey: Array.isArray(key) ? key : [key],
        queryFn: fetcher,
        ...options,
    });
}
