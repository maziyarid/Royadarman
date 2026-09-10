# Principal data model

> **SUPERSEDED — historical Phase-0 design spec.** This document described the
> originally-proposed marketplace/payment data model (PostgreSQL/PostGIS entities,
> booking modes, payments, double-entry ledger, settlement, guardian/minor
> flows). That data model was **not** implemented. The implemented product is a
> guidance/coordination service on Laravel 13 / MariaDB with no payments, no
> marketplace, no booking holds, no guardian/minor flows, and no ledger. The
> current authoritative data model is `backend/ARCHITECTURE.md` and the migrations
> under `backend/database/migrations/`. This file is retained for historical
> provenance only.

Rule: Royadarman stores referral and transactional information. It does not become a dental EMR by accident.

Identity and privacy: User, PatientProfile, GuardianRelationship, Address, ConsentRecord, CommunicationPreference, Session, RoleAssignment.

Clinic network: Clinic, ClinicBranch, ClinicUser, Dentist, Credential, PayoutAccount, Service, BranchService, OperatingHours, Holiday, BookingMode.

Referral and matching: ReferralRequest, IntakeAnswer, UrgencyAssessment, PatientPreference, MatchRun, MatchCandidate, MatchDecision.

Scheduling: AvailabilityRule, CapacityWindow, AppointmentSlot, SlotHold, Appointment, AppointmentStatusHistory, CheckIn, VisitConfirmation.

Finance: Order, OrderLine, PriceSnapshot, PaymentIntent, PaymentTransaction, PaymentWebhook, Refund, LedgerAccount, LedgerTransaction, LedgerEntry, SettlementBatch, SettlementItem, ReconciliationResult.

Operations: SupportCase, Complaint, Review, Notification, AuditEvent, FraudSignal.

SlotHold is a first-class row with expiry, token, and unique constraint. LedgerEntry rows are insert-only. Reviews require a verified completed visit.

Out of MVP: diagnoses, imaging, prescriptions, treatment notes, odontograms, periodontal charts.
