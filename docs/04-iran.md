# Iran-first implications

Status: ADR-011 (Iran, Tehran first) is accepted. The payment/settlement items below were part of the originally-proposed marketplace architecture (ADR-006/007/015) and are **superseded** — Royadarman is not a payment processor and implements no payments, IPG, wallet, escrow, or settlement. The non-payment items (maps, messaging, locale, law) remain relevant to the current guidance/coordination service.

Evidence: Persian brand; Medical-CRM already implements Jalali, Iranian national-id checksum, and an Iranian SMS chain; adjacent clinic sites operate in that market.

If accepted:

- Payments: **superseded — out of scope.** Royadarman is not a payment processor. (Historical note: the original proposal considered one Iranian hosted IPG with signed webhooks and daily reconciliation; this was not implemented.)
- Maps: Neshan, Map.ir, or Balad behind an adapter. Fallback: PostGIS distance.
- Messaging: Kavenegar / Ghasedak / FarazSMS chain.
- Locale: UTC in the database. Jalali only at presentation. Patient PWA is Persian RTL first.
- Law: health advertising, referral/commission rules, invoice issuer, tax, data residency, minors. Counsel writes the memo.

Until counsel's memo exists: Royadarman handles guidance, coordination and support only — no visit-fee capture, no wallet, no escrow, no auto-assign without patient acceptance.
