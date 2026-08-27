# Principal data model

Rule: Royadarman stores referral and transactional information. It does not become a dental EMR by accident.

Identity and privacy: User, PatientProfile, GuardianRelationship, Address, ConsentRecord, CommunicationPreference, Session, RoleAssignment.

Clinic network: Clinic, ClinicBranch, ClinicUser, Dentist, Credential, PayoutAccount, Service, BranchService, OperatingHours, Holiday, BookingMode.

Referral and matching: ReferralRequest, IntakeAnswer, UrgencyAssessment, PatientPreference, MatchRun, MatchCandidate, MatchDecision.

Scheduling: AvailabilityRule, CapacityWindow, AppointmentSlot, SlotHold, Appointment, AppointmentStatusHistory, CheckIn, VisitConfirmation.

Finance: Order, OrderLine, PriceSnapshot, PaymentIntent, PaymentTransaction, PaymentWebhook, Refund, LedgerAccount, LedgerTransaction, LedgerEntry, SettlementBatch, SettlementItem, ReconciliationResult.

Operations: SupportCase, Complaint, Review, Notification, AuditEvent, FraudSignal.

SlotHold is a first-class row with expiry, token, and unique constraint. LedgerEntry rows are insert-only. Reviews require a verified completed visit.

Out of MVP: diagnoses, imaging, prescriptions, treatment notes, odontograms, periodontal charts.
