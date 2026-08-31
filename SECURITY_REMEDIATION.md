# Security remediation requirements

Before the next deployment, an authorized operator must rotate every privileged
account credential, static recovery token, and external secret that was ever
embedded in removed authentication or maintenance source. Source removal does
not constitute credential rotation.

Privileged account recovery must be implemented in a future phase as a CLI-only,
expiring, one-time, audited procedure. It must never be reachable through an
ordinary web request.

Run the public-artifact deployment gate locally and in CI:

```sh
php scripts/check_public_security.php
```

The command fails when known authentication diagnostics, privileged seed/reset
endpoints, or embedded administrator fallback patterns are present.

## Admin authorization deployment

Before enabling ordinary Administrator access, apply
`php_app/database/migrations/080_admin_permissions_hardening.sql` through the
normal CLI migration runner:

```sh
php php_app/run_migrations.php
```

Until that migration is applied, ordinary Administrators are denied by design.
Active Super Administrators retain their authenticated, database-backed bypass.
