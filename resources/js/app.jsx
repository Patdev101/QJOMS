import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// every file in Pages/ is a page; Laravel picks one with Inertia::render('Name')
const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · QJOMS` : 'QJOMS'),
    resolve: (name) => pages[`./Pages/${name}.jsx`],
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#eba63c' },
});
