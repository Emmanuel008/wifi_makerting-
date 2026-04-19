import React from 'react';

/**
 * Build page items: numbered buttons with ellipsis (e.g. 1, 2, 3, …, 124).
 */
function buildPageItems(current, totalPages) {
  if (totalPages <= 1) {
    return [{ type: 'page', n: 1 }];
  }
  /* Show every page when there are few; ellipsis pattern when many (e.g. 1,2,3,…,124) */
  if (totalPages <= 7) {
    return Array.from({ length: totalPages }, (_, i) => ({ type: 'page', n: i + 1 }));
  }

  const items = [];
  const pushPage = (n) => items.push({ type: 'page', n });
  const pushEllipsis = () => {
    if (items.length && items[items.length - 1].type !== 'ellipsis') {
      items.push({ type: 'ellipsis' });
    }
  };

  pushPage(1);

  if (current <= 3) {
    pushPage(2);
    pushPage(3);
    if (totalPages > 4) {
      pushEllipsis();
      pushPage(totalPages);
    } else if (totalPages === 4) {
      pushPage(4);
    }
  } else if (current >= totalPages - 2) {
    pushEllipsis();
    for (let p = Math.max(2, totalPages - 3); p <= totalPages; p += 1) {
      pushPage(p);
    }
  } else {
    pushEllipsis();
    pushPage(current - 1);
    pushPage(current);
    pushPage(current + 1);
    pushEllipsis();
    pushPage(totalPages);
  }

  return items;
}

export default function Pagination({ page, pageSize, total, onPageChange }) {
  const totalPages = Math.max(1, Math.ceil(total / pageSize) || 1);
  const safePage = Math.min(Math.max(1, page), totalPages);
  const from = total === 0 ? 0 : (safePage - 1) * pageSize + 1;
  const to = Math.min(safePage * pageSize, total);

  const pageItems = React.useMemo(
    () => buildPageItems(safePage, totalPages),
    [safePage, totalPages]
  );

  return (
    <div className="pagination" role="navigation" aria-label="Table pagination">
      <div className="paginationMeta">
        {total === 0 ? 'No rows' : `${from}–${to} of ${total}`}
      </div>
      <div className="paginationControls">
        {total > 0 && totalPages > 1 ? (
          <div className="paginationNumNav">
            <button
              type="button"
              className="paginationNumBtn"
              disabled={safePage <= 1}
              onClick={() => onPageChange(safePage - 1)}
              aria-label="Previous page"
            >
              «
            </button>
            {pageItems.map((item, idx) =>
              item.type === 'ellipsis' ? (
                <span
                  key={`ellipsis-${idx}`}
                  className="paginationNumBtn paginationNumBtnEllipsis"
                  aria-hidden
                >
                  …
                </span>
              ) : (
                <button
                  key={item.n}
                  type="button"
                  className={`paginationNumBtn${item.n === safePage ? ' isActive' : ''}`}
                  onClick={() => onPageChange(item.n)}
                  aria-label={`Page ${item.n}`}
                  aria-current={item.n === safePage ? 'page' : undefined}
                >
                  {item.n}
                </button>
              )
            )}
            <button
              type="button"
              className="paginationNumBtn"
              disabled={safePage >= totalPages}
              onClick={() => onPageChange(safePage + 1)}
              aria-label="Next page"
            >
              »
            </button>
          </div>
        ) : total > 0 && totalPages === 1 ? (
          <div className="paginationNumNav">
            <button
              type="button"
              className="paginationNumBtn isActive isSingle"
              aria-current="page"
              disabled
            >
              1
            </button>
          </div>
        ) : null}
      </div>
    </div>
  );
}
