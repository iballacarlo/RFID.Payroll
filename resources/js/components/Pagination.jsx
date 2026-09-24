import { Link } from '@inertiajs/react';

export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return <div className="pagination">{links.map((link, index) => (
        link.url ? <Link key={index} href={link.url} className={link.active ? 'active' : ''} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} dangerouslySetInnerHTML={{ __html: link.label }} />
    ))}</div>;
}
