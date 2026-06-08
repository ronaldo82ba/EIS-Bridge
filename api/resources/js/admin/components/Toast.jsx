let toastHandler = null;

export function registerToastHandler(handler) {
    toastHandler = handler;
}

export function toastSuccess(message) {
    if (toastHandler) {
        toastHandler(message, 'success');
        return;
    }

    window.alert(message);
}

export function toastError(message) {
    if (toastHandler) {
        toastHandler(message, 'error');
        return;
    }

    window.alert(message);
}

export default function ToastContainer() {
    return (
        <div
            id="app-toast"
            className="pointer-events-none fixed bottom-6 right-6 z-50 hidden max-w-sm rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
            role="status"
            aria-live="polite"
        />
    );
}
