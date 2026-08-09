import { createInertiaApp } from '@inertiajs/vue3';
import { resolveLayout } from '@/layouts/resolveLayout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: resolveLayout,
});
