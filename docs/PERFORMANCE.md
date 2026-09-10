# Performance boundaries

The POS keeps cart and product-click calculations in Pinia, so product selection
does not make a network request. The bootstrap endpoint is the single initial
read boundary.

Bootstrap catalog and store configuration are cached through Redis for one hour.
Category/product and table/printer/route model changes invalidate the relevant
tenant or store key immediately. Open-table occupancy, subscription, permission,
and active-shift state intentionally remain uncached because they are volatile.

Order and payment creation remain synchronous transactional writes. Queues are
reserved for future non-interactive work; no current order or payment write is
dispatched to a queue.

Filament resource lists use their built-in pagination. API order history uses a
50-record paginator. Report input is limited to 366 days and top products to 10
rows. Tenant/store/date indexes support common order, payment, and report filters.
