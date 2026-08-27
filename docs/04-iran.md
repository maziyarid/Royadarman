# Iran-first implications

Status: proposed until ADR-011 is locked.

Evidence: Persian brand; Medical-CRM already implements Jalali, Iranian national-id checksum, and an Iranian SMS chain; adjacent clinic sites operate in that market.

If accepted:

- Payments: one Iranian hosted IPG, not Stripe. Hosted page, signed webhooks, daily reconciliation. Prefer split settlement if counsel allows. No consumer wallet. Sheba for payouts.
- Maps: Neshan, Map.ir, or Balad behind an adapter. Fallback: PostGIS distance.
- Messaging: Kavenegar / Ghasedak / FarazSMS chain.
- Locale: UTC in the database. Jalali only at presentation. Patient PWA is Persian RTL first.
- Law: health advertising, referral/commission rules, invoice issuer, tax, data residency, minors. Counsel writes the memo.

Until the memo exists: visit fee only, no wallet, no escrow language, no auto-assign without patient acceptance.
