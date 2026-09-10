# Offline compatibility review

Full offline synchronization is intentionally outside the MVP. The current
design preserves a future migration path without changing the domain model:

- orders, order items, payments, shifts, customers, devices, and delivery
  details use ULIDs;
- POS create requests accept optional client-generated ULIDs and the backend
  returns an existing record for a safe replay;
- order, item, and payment replay checks reject IDs already owned by a different
  order, store, or tenant;
- transactional and configuration tables retain `updated_at`; store and catalog
  master data use `is_active` instead of destructive deletion;
- device ownership is recorded on orders, payments, and shifts where required;
- API, printer, browser storage, and synchronization are separate TypeScript
  service boundaries; the current sync implementation explicitly reports
  `online-only` and can later be replaced by an IndexedDB-backed engine;
- display numbers remain server-generated store-local presentation numbers and
  are not used as synchronization identity;
- printer output accepts already-prepared document lines, so cached print data
  can later be sent to the local QZ bridge without changing order mutations.

No background synchronization, conflict UI, or IndexedDB implementation is
included in this phase.
