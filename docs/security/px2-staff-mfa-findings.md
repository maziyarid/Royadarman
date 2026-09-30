# PX.2 staff MFA: source findings and proposed contract

Status: source analysis and synthetic reset-MFA characterisation tests. See PX.3/PX.4 notes for independently executed results. Enforcement activation is gated on the owner's A/B/C decision in issue #15.
Base: main f99210e05c1fef2e186d76e625164cc4ea101d8a. Sources: issue #15 packets 2, 3 and 5 (application code only; the `laravel/passkeys` package source was not read).

## Verified from source (not from a runtime)

1. MFA is enforced only when `role->isStaff() && StaffMfaService::isConfigured()`. Configured means a TOTP secret or at least one recovery code.
2. `AdministratorController::store` creates staff with `password = null`, `totp_secret = null` and `mfa_recovery_codes = null`. Every new staff member therefore starts with MFA off and first signs in by SMS OTP alone.
3. `AdministratorController::resetMfa` (owner only, not self, not demo) revokes the target's sessions and nulls secret and codes. It does not force re-enrolment, so a reset returns the account to the no-MFA state in finding 1.
4. `AdministratorController::index` shows `mfa => filled(totp_secret)`, so a recovery-code-only account is displayed as having no MFA although login still requires a code.
5. A recovery-code-only account that spends its last code becomes unconfigured, so MFA turns off silently (no secret, no codes).
6. `ProfileWorkspaceController::updateCredentials` skips the current-password check when `auth_method` is `otp`. For a staff account without MFA, control of the phone number is enough to set a new password. The route already uses EnsureRecentAuthentication on main; PX.1 (draft PR #17) proposes changing assurance from the shared user timestamp to the current session. It has not been merged into this branch.
7. `startTotp` stores the pending TOTP secret in the session. `session.encrypt` defaults to false, so with the database driver and that default, the application does not encrypt the pending secret in the session payload until confirmed, cancelled or the session expires. No separate pending-secret expiry was found. Production session configuration and storage encryption were not inspected.
8. `PasskeyVerified` login does not consult MFA configuration. Whether a passkey counts as a second factor is a policy decision.

## Not verified

Passkey package login behaviour and whether any production staff account currently has no MFA remain unverified. The integrator read SessionInventoryService and executed synthetic reset-MFA tests that confirm target sessions are deleted, an unrelated owner session remains, and the reset is audited. No production account or session data was accessed.

## Proposed contract (options, not activated)

- Option A, forced enrolment: a staff session without configured MFA may reach only the profile security routes until TOTP is confirmed and recovery codes are shown. This requires an independently verified enrolment and recovery path; absence of lockout cannot be guaranteed merely by proposing the restriction.
- Option B: option A with a dated per-role grace period.
- Option C: owner and superadmin exempt until a second recovery path exists.
- Regardless of option: `resetMfa` should set an enrolment-required marker instead of silently leaving MFA off; the admin list should show recovery-only accounts accurately; exhausting the last recovery code should warn before it is used.
- Any new middleware needs route wiring in `routes/web.php` (shared file), so it requires a recorded handoff.

Recommendation: A, with the owner enforced last and only after recovery codes are confirmed saved. This is a recommendation, not a decision.
