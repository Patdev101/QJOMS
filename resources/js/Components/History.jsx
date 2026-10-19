import Icon from './Icon';

// which icon and color each kind of event gets
const looks = {
    created: ['file-text', 'pine'],
    revised: ['pencil', 'amber'],
    approved: ['check-circle', 'pine'],
    status: ['clipboard', 'amber'],
    invoiced: ['receipt', 'pine'],
    payment: ['check', 'pine'],
    payment_removed: ['trash', 'clay'],
    deleted: ['trash', 'clay'],
};

// timeline of everything that happened to a quotation, its job order and invoice (newest first)
export default function History({ items }) {
    return (
        <section className="card">
            <div className="card-head">
                <h2><Icon name="clock" /> History</h2>
                <span className="muted" style={{ fontSize: 13 }}>{items.length} {items.length === 1 ? 'event' : 'events'}</span>
            </div>
            {items.length === 0 ? (
                <div className="empty compact"><p>No history yet. Events are recorded from now on.</p></div>
            ) : (
                <ol className="timeline">
                    {items.map((item) => {
                        const [icon, tone] = looks[item.action] ?? ['clock', 'ink'];
                        return (
                            <li key={item.id}>
                                <span className={`timeline-ic icon-${tone}`}><Icon name={icon} size={15} /></span>
                                <div>
                                    <div className="timeline-text">{item.description}</div>
                                    <div className="cell-sub" title={item.when}>
                                        {item.user ? `by ${item.user} · ` : ''}{item.when} · {item.ago}
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}
        </section>
    );
}
