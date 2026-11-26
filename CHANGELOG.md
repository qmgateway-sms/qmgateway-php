# Changelog

## 0.1.0 (2026-11-26)
- First version: messages (send, send to many, status, list, scheduled, cancel, reschedule), inbox, devices, one-time passwords, account, balance and statistics.
- Typed results and typed errors (`AuthenticationException`, `PermissionException`, `InvalidRequestException`, `NotFoundException`, `RateLimitException`, `ServerException`, `ConnectionException`).
- Safe retries with the same `Idempotency-Key`, `Retry-After` support, 30 second timeout.
- Auto-pagination (`messages->each()`, `inbox->each()`, `messages->eachScheduled()`).
- Webhook verifier with typed events, `Webhook::sign()` for your own tests, `Testing\FakeTransport`.
- `bin/qmgateway` command-line tool.
- Security: a caller-supplied idempotency key must be 1 to 255 visible characters with no spaces, so it cannot be used to inject a header.
