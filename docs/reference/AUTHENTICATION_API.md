# Authentication and API Access

## Web sessions

- `GET /login` displays the credential form.
- `POST /login` accepts `login` (email, employee number, or member number) and `password`.
- The caller cannot select a role during login. The session starts in one of the roles already assigned to the account.
- `POST /role-context` changes context only to a role assigned to the authenticated user.
- `POST /logout` invalidates the web session.

## API tokens

- `POST /api/v1/auth/login` accepts JSON fields `login` and `password`; it returns a Sanctum bearer token expiring after 12 hours.
- `POST /api/v1/auth/logout` revokes the current bearer token.
- Send tokens as `Authorization: Bearer <token>`.
- Authentication rejects inactive users and users without one of the supported roles: `MEMBER`, `CASHIER`, or `MANAGER`.

## Member-owned resources

Authenticated members can list their own orders, payments, and receivables using:

- `GET /api/v1/orders`
- `GET /api/v1/payments`
- `GET /api/v1/receivables`

These endpoints derive the member from the authenticated account and do not accept a member identifier. Order item responses intentionally omit HPP snapshots.

## Deployment prerequisite

Sanctum's `personal_access_tokens` migration has been published with a UUID morph key to match the application's UUID user IDs. It has not been run. Apply it only through the separately approved deployment/migration process before enabling token login; this implementation phase does not access or modify any database.

The storefront's browser checkout is not yet connected to the authenticated transaction API and does not create orders. Members must use a client integrated with the protected API until that UI integration is implemented.

Production web sessions set the `Secure` cookie flag by default. Serve the application over HTTPS and do not override `SESSION_SECURE_COOKIE` to `false` in production.
