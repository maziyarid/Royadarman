# Iran-first implications

> **PARTIALLY SUPERSEDED.** The non-payment items below (Jalali at
> presentation only, UTC storage, Iranian SMS chain behind an adapter,
> locale RTL) remain consistent with the current architecture. The
> payment items (Iranian hosted IPG, split settlement, Sheba payouts, no
> wallet/escrow) describe the earlier **marketplace/payment** design and
> are **not** part of the current MVP. **Current canonical architecture:**
> `backend/ARCHITECTURE.md`.

Status: proposed until ADR-011 is locked.

Evidence: Persian brand; Medical-CRM already implements Jalali, Iranian national-id checksum, and an Iranian SMS chain; adjacent clinic sites operate in that market.

If accepted:

- Payments: one Iranian hosted IPG, not Stripe. Hosted page, signed webhooks, daily reconciliation. Prefer split settlement if counsel allows. No consumer wallet. Sheba for payouts.
- Maps: Neshan, Map.ir, or Balad behind an adapter. Fallback: PostGIS distance.
- Messaging: Kavenegar / Ghasedak / FarazSMS chain.
- Locale: UTC in the database. Jalali only at presentation. Patient PWA is Persian RTL first.
- Law: health advertising, referral/commission rules, invoice issuer, tax, data residency, minors. Counsel writes the memo.

Until the memo exists: visit fee only, no wallet, no escrow language, no auto-assign without patient acceptance.
