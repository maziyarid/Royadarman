# Finance integer contract

Revision: 2026-10-01. Status: **proposed, not wired**. Worker: Grok 2, issue #14, slice G2.6.

This revision does not add a table, an amount column, a conversion factor, a merchant credential, or a route. `FinanceIntegerContract` is not called by case creation or review publishing.

## Evidence

| Fact | Level |
| --- | --- |
| `patient_cases.budget_band` is a string. Draft accepts `economic`, `balanced`, `flexible`, or `call` | Migration `2026_08_31_000100` and `CaseController` |
| `patient_cases.currency` is a 3-character string, default `IRR`. Draft always writes `IRR` | Migration `2026_09_08_000300` and `CaseController` |
| `patient_cases.budget_input_unit` is a string, default `toman`, and draft accepts `toman` or `irr` | Same migration and controller |
| `review_revisions.budget_band` is the same kind of nullable string | Migration `2026_09_08_000400` |
| The only `decimal` columns are `clinics.latitude` and `clinics.longitude` (9, 6). They are coordinates | Migration `2026_09_19_120000` |
| No `invoices`, `payments`, `ledger_entries`, `receipts`, `refunds`, `cheques`, or `instalments` table | Migration scan on this base |
| No integer column whose name is an amount, price, fee, or minor unit | Same scan |
| No payment or merchant key in `config/royadarman.php` | Key scan on this base. No secret was read |

A case can store currency `IRR` and input unit `toman` at the same time. The schema does not store the factor between them. This revision does not adopt "10 rials to 1 toman" or any other factor. `minorUnitFactor()` is null.

## What a number means

A budget band is not an amount. `1500000` is not a band. A float such as `10.5` is rejected before the missing-column reason. An integer, including zero, is still `amount_column_absent`.

A debit that does not equal its credit is `ledger_unbalanced`. A balanced pair is still `ledger_table_absent`. `merchantReady()` is false.

## What is not decided

No invoice, receipt, payment attempt, callback, refund, instalment, or cheque state. No clinic price list. Real merchant ownership and agreed finance rules remain gates. A migration still needs a fresh backup and a separately provisioned restore target, which this slice does not claim to have.

## API shape

Not implemented. No route is added.

```json
{"status":"finance_contract_not_wired","currency":"IRR","minor_unit_factor":null,"amount_column":false,"ledger":false,"merchant":false}
```

`currency: "IRR"` describes the stored case label. It is not a payment acceptance.

## Rollback

Revert this commit. No database restore.
