# Security review

The current organization, store, and device are resolved from authenticated
membership plus server-side session context. POS request payloads never select an
organization or store. Resource policies and domain actions re-check tenant and
store ownership, including records resolved through route model binding.

Transactional model fillable lists exclude organization, store, device, totals,
status, and actor identifiers. Those values are associated or force-filled only
inside domain actions. Audit storage is internal-only and removes sensitive
credential fields.

POS traffic is rate limited per authenticated user (falling back to IP), with a
stricter independent limiter for QZ signing. QZ private keys remain server-side
configuration and are not included in application logs, audit values, Vue source,
or public assets.

The dedicated security tests cover cross-tenant and cross-store IDOR, permission
bypass, expired subscriptions, device spoofing, printer binding, client-supplied
tenant identifiers, signing throttles, and audit sanitization.
