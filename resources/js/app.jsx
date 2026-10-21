import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// every file in Pages/ is a page; Laravel picks one with Inertia::render('Name')
const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

// the mouse wheel over a focused number box would change its value (qty, price, L.O.A. …);
// let go of the box instead, so the wheel only scrolls the page
document.addEventListener('wheel', (event) => {
    const box = document.activeElement;
    if (box instanceof HTMLInputElement && box.type === 'number' && box === event.target) box.blur();
}, { passive: true });

createInertiaApp({
    title: (title) => (title ? `${title} · QJOMS` : 'QJOMS'),
    resolve: (name) => pages[`./Pages/${name}.jsx`],
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#eba63c' },
});
