import { Link } from '@inertiajs/react';

// "Showing 1–20 of 45" and page numbers for a Laravel paginator
export default function Pager({ page }) {
    if (! page.total) return null;

    return (
        <div className="pager">
            <span>Showing {page.from}–{page.to} of {page.total}</span>
            {page.last_page > 1 && (
                <ul className="pagination">
                    {page.links.map((link, i) => (
                        <li key={i} className={`page-item${link.active ? ' active' : ''}${link.url ? '' : ' disabled'}`}>
                            {link.url
                                ? <Link className="page-link" href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                                : <span className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
