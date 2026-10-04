# W05 clinical journey and recovery checkpoint — 4 October 2026

## Current state and ownership

This supersedes the 08:33 handoff; that original remains in local commit 8ff314198fc7a3401430d1542f019f5fc4d3575d and equivalent published commit 1664569043c5082764bb49dde0dd9828c5e9dd33. Task RPH-50 / RPH-109, contract RD-DELIVERY-20261004-v2. Existing branch w05/clinical-release-validation-20261004; own cPanel-owned worktree agent-workspaces/w05-clinical-20261004.

G01 comment5977948680 grants only W05ClinicalReleaseJourneyTest.php and this handoff. W02 clinical handoff5977956669 is retained. W05 resumption5979857573 and reproduced findings5979908726 request two narrow application paths; no source grant has been observed at this checkpoint. The appended code is a PROPOSED PATCH, NOT APPLIED to application source. W01 remains the only integrator/deployer; W04 independent review remains required. No migration, production write, actual patient/OPG, provider send or clinical activation occurred.

## F01 reconciliation — do not redo the fix

Fresh source comparison verifies W01 candidate86e7785f47acc569b557b59b9276e6768d70e71f contains the precise DocumentController::status view-to-learnStatus correction and the identical original W05 journey test. W01's12:17 RPH57 checkpoint reports that backend path remains undeployed after concurrent branding drift. This is implementation in a candidate, not W05 deployment or full acceptance. No duplicate source repair is made here.

Earlier recorded evidence remains valid only for its exact source: original baseline19tests/190assertions PASS; original W05 test16tests/317assertions with11PASS/5FAIL at08:29:20Z, all five status polling failures. Those are earlier executions, not the results of this continuation. Do not interpret the old local F01 RED cases as proof W01's corrected candidate is still defective.

## New reproduction and evidence

Executed final changed-scope suite at2026-10-04T12:24:48Z /15:54:48 Tehran: **9 tests /107 assertions; 3PASS,6FAIL,0errors,0skipped; PHPUnit exit1**. PHP8.3.35, Laravel13.29.0, PHPUnit12.5.34; approximately1.27s of test time. Single-file Pint and PHP syntax checks exit0. Expanded test SHA256 d44f1b03d5c4a7db221c2481d27940f2caebd400ff80ef924dd2b54c7a48a321.

The test uses real Laravel routes/handlers and persistent synthetic SQLite memory state. Scanner and storage fault adapters are fakes; queue/mail are fake; HTTP stray requests and outbound PHP socket functions are disabled. No .env or cached configuration exists in this checkout. Existing exact-lock dependencies are reused, not reinstalled. composer.lock SHA25672127e7d217d9a2e9672704a25e760450f528ca9a8bd264a1df8f4af39bf1b0b.

The unchanged upload/scanner/model/policy/filesystem/lock source is byte-equivalent to W01 candidate86e7785 (git diff --exit-code0). No full suite, private MariaDB daemon, real browser, actual scanner or live provider was run in this continuation. Source equivalence applies to these unchanged dependencies, not to every route/auth/UI difference in the combined candidate.

Durable ignored evidence: coverage/w05-evidence/w05-recovery-final-red.xml, .log and .json. The earlier nine-case run at12:22:46Z was9/106 with the same six failures. The final run adds an explicit HTTP assertion proving deleted-file access is still denied and diagnostic counts for the failed write; no assertion was weakened to obtain a pass.

Reproduce from backend/ in this isolated environment, using a stripped environment and the existing forced testing configuration:

```sh
/opt/cpanel/ea-php83/root/usr/bin/php -d memory_limit=256M   -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client   vendor/bin/phpunit tests/Feature/W05ClinicalReleaseJourneyTest.php   --filter 'interrupted_upload_creates|upload_storage_failure|completed_scan_replay|late_scan_failure|repeated_scan_failure|deleted_source_is_not|deletion_during_scan'   --colors=never --fail-on-warning
```

## Reproduced defects

### F02: storage failure is falsely acknowledged

QuarantineClinicalDocument::handle ignores writeStream=false. Actual HTTP202, one clinical_document row, one successful quarantine audit and zero stored files were observed. This creates a request that claims an upload exists when its bytes were not accepted. The proposed first hunk demands storage acknowledgement before creating the record or dispatching the scanner. Recovery after restoration is in the test after the failed assertion and is therefore NOT YET proven for this failure case. The separate interrupted-upload recovery positive does pass.

### F03: late or duplicate failure callbacks corrupt terminal state

The actual ScanClinicalDocument::failed callback demotes Approved and Rejected to ScanFailed (two failing cases). Repeating the callback duplicates the failure audit (one failure). The proposal locks and re-reads the row and atomically mutates only nondeleted Quarantined/Scanning state, including unfinished-attempt and audit writes. Already approved/rejected/deleted/failed results are no-op. It does not invent a new scanner, clinical role or retention period.

### F04: deleted sources continue processing and can become Approved

An existing deleted_at tombstone still invokes scanning. Deterministic deletion injected during the actual scanner call is then overwritten with Approved by completion (two failures). The real content route still returned404 after that interleaving, so no public/private-byte exposure is alleged by these tests. This is source lifecycle/retention-state corruption.

The proposal claims scan work under a fresh document lock, invokes the scanner outside the transaction, then rechecks deletion, source identity, current state and latest attempt before completing. Bounded local private promotion occurs under that document lock; the original is deleted only after the verdict transaction commits. Review lock duration and the actual local-disk assumption before applying. No new schema is proposed.

### Positive controls

Interrupted upload:422, no stored file/document/scan, then complete retry202 with exactly one persisted document. Completed approval replay: no scanner invocation or duplicate audit. Late failure after Deleted: no state mutation. These three cases PASS.

## Proposed patch: verification and remaining risk

Patch SHA25618561cd4c578be00aa16d122f87f560dfb5019a388abf29be791c88c919aa14f. Both proposed PHP files passed syntax and Pint; git apply --check passed against the unchanged source. Neither file was applied, and the proposal has NOT run the behaviour tests. Syntax/applicability is not proof the repair works.

Source preconditions:
- QuarantineClinicalDocument.php SHA2563fb6664c2140ba9bd14986c345ab15ee82b38b381eed31c00f9d44afd1324c71.
- ScanClinicalDocument.php SHA25640d808822c50e07c04e3d7bb879180816ad5084ea909248823acb9150466a845.

Residual checks before production: actual two-connection MariaDB deletion/completion/late-failure races; failure of private promotion or database/audit commit and orphan cleanup; queue-generation identity when an old failure arrives during a genuinely newer in-flight scan; real storage/scanner recovery and bounded lock duration. Latest-attempt completion fencing does not by itself establish failure-callback generation binding. Do not call these untested properties solved.

## Next executable steps

1. W01 grants the two named application paths or explicitly takes the patch. Do not overwrite the captain's moving candidate or any other worker's source.
2. In an owned isolated candidate, apply only reviewed hunks and run the nine new tests plus existing document/consent/review/source-deletion regressions. Keep old F01 baseline failures separate or consume the captain's exact F01 commit after permission/source reconciliation.
3. Add adverse storage/transaction and real MariaDB contention checks under a serial W01 runtime grant. Obtain independent W04 review at the actual candidate head.
4. W01 integrates, deploys and verifies the combined candidate; no author self-approval. Reconcile final served source after existing branding changes.

Implementation states: new regression tests IMPLEMENTED and TESTED_ISOLATED (RED); F02-F04 application repair PROPOSED_NOT_APPLIED / NOT_RUN; independent REVIEWED, INTEGRATED, DEPLOYED and ACCEPTED: NO for this new slice.

Full W05 scope remains open: no-OPG clinical reporting, tooth/surface findings, clinical/SOAP/history, treatment plans, recovery follow-ups and profile integration; W08's actual transactional released-report notification/feed contract; browser/physical-device proof. Real clinical taxonomy/signers/retention/guardian/advanced-AI activation is not invented. Patient availability, unread state and provider delivery remain distinct.

## Exact proposed source patch — NOT APPLIED

The following zero-context rendering contains the same proposed source changes, without Markdown trailing-space artefacts. Its SHA256 is 39813e0a0f53cf738348c5da11a1d16b198dd68eafc809b73bf1af248fe3dc4e. It passed `git apply --check --unidiff-zero`; that check is read-only. The original context-bearing patch and its previously recorded SHA remain preserved in the private evidence directory.

```diff
--- a/backend/app/Domain/Documents/Services/QuarantineClinicalDocument.php
+++ b/backend/app/Domain/Documents/Services/QuarantineClinicalDocument.php
@@ -44 +44,3 @@
-            Storage::disk($disk)->writeStream($key, $stream);
+            if (! Storage::disk($disk)->writeStream($key, $stream)) {
+                throw new \RuntimeException('Unable to store quarantined document.');
+            }
--- a/backend/app/Jobs/ScanClinicalDocument.php
+++ b/backend/app/Jobs/ScanClinicalDocument.php
@@ -44,4 +44,23 @@
-        /** @var ClinicalDocument $document */
-        $document = ClinicalDocument::query()->findOrFail($this->documentId);
-
-        if (! in_array($document->status, [DocumentStatus::Quarantined, DocumentStatus::Scanning], true)) {
+        $claim = DB::transaction(function (): ?array {
+            $document = ClinicalDocument::query()->lockForUpdate()->find($this->documentId);
+            if ($document === null || $document->deleted_at !== null
+                || ! in_array($document->status, [DocumentStatus::Quarantined, DocumentStatus::Scanning], true)) {
+                return null;
+            }
+
+            $document->forceFill([
+                'status' => DocumentStatus::Scanning,
+                'scan_attempted_at' => now(),
+                'scan_error_code' => null,
+            ])->save();
+            $attempt = ScanAttempt::query()->create([
+                'document_id' => $document->id,
+                'attempt_number' => (int) ScanAttempt::query()->where('document_id', $document->id)->max('attempt_number') + 1,
+                'status' => 'running',
+                'file_hash' => $document->sha256,
+                'started_at' => now(),
+            ]);
+
+            return [$document, $attempt];
+        });
+        if ($claim === null) {
@@ -50,16 +69,4 @@
-
-        $document->forceFill([
-            'status' => DocumentStatus::Scanning,
-            'scan_attempted_at' => now(),
-            'scan_error_code' => null,
-        ])->save();
-
-        $attemptNumber = ScanAttempt::query()->where('document_id', $document->id)->count() + 1;
-        $attempt = ScanAttempt::query()->firstOrCreate(
-            ['document_id' => $document->id, 'attempt_number' => $attemptNumber],
-            ['status' => 'running', 'file_hash' => $document->sha256, 'started_at' => now()]
-        );
-
-        $quarantine = Storage::disk($document->storage_disk);
-        $path = $quarantine->path($document->storage_key);
-        if (! hash_equals($document->sha256, hash_file('sha256', $path))) {
+        [$sourceDocument, $attempt] = $claim;
+        $quarantine = Storage::disk($sourceDocument->storage_disk);
+        $path = $quarantine->path($sourceDocument->storage_key);
+        if (! hash_equals($sourceDocument->sha256, hash_file('sha256', $path))) {
@@ -68,0 +76,2 @@
+
+        // The external scanner never runs while a database row lock is held.
@@ -70,32 +79,48 @@
-
-        if (! $result->clean) {
-            $this->complete($document, DocumentStatus::Rejected, $result->engine, $result->reference, 'infected');
-            $attempt->update(['status' => 'rejected', 'engine' => $result->engine, 'finished_at' => now()]);
-            $quarantine->delete($document->storage_key);
-
-            return;
-        }
-
-        $approvedDisk = (string) config('royadarman.opg.disk', 'private-opg');
-        $source = $quarantine->readStream($document->storage_key);
-
-        if ($source === null || $source === false) {
-            throw new RuntimeException('Unable to read quarantined document for promotion.');
-        }
-
-        try {
-            Storage::disk($approvedDisk)->writeStream($document->storage_key, $source);
-        } finally {
-            if (is_resource($source)) {
-                fclose($source);
-            }
-        }
-
-        $approvedPath = Storage::disk($approvedDisk)->path($document->storage_key);
-        if (! hash_equals($document->sha256, hash_file('sha256', $approvedPath))) {
-            Storage::disk($approvedDisk)->delete($document->storage_key);
-            $attempt->update(['status' => 'failed', 'error_code' => 'promotion_hash_mismatch', 'finished_at' => now()]);
-            throw new RuntimeException('Document hash changed during promotion.');
-        }
-
-        DB::transaction(function () use ($document, $approvedDisk, $result): void {
+        $outcome = DB::transaction(function () use ($sourceDocument, $attempt, $quarantine, $result): ?DocumentStatus {
+            $document = ClinicalDocument::query()->lockForUpdate()->find($this->documentId);
+            $latestAttempt = ScanAttempt::query()->where('document_id', $sourceDocument->id)
+                ->orderByDesc('attempt_number')->value('id');
+            if ($document === null || $document->deleted_at !== null
+                || $document->status !== DocumentStatus::Scanning
+                || $document->storage_disk !== $sourceDocument->storage_disk
+                || $document->storage_key !== $sourceDocument->storage_key
+                || $document->sha256 !== $sourceDocument->sha256
+                || $document->consent_event_id !== $sourceDocument->consent_event_id
+                || $latestAttempt !== $attempt->id) {
+                ScanAttempt::query()->whereKey($attempt->id)->whereNull('finished_at')->update([
+                    'status' => 'failed', 'error_code' => 'source_changed', 'finished_at' => now(),
+                ]);
+
+                return null;
+            }
+
+            if (! $result->clean) {
+                $this->complete($document, DocumentStatus::Rejected, $result->engine, $result->reference, 'infected');
+                $attempt->update(['status' => 'rejected', 'engine' => $result->engine, 'finished_at' => now()]);
+
+                return DocumentStatus::Rejected;
+            }
+
+            // Promotion uses the existing bounded local private disks. Holding the
+            // document lock here prevents deletion from winning halfway through it.
+            $approvedDisk = (string) config('royadarman.opg.disk', 'private-opg');
+            $source = $quarantine->readStream($document->storage_key);
+            if ($source === null || $source === false) {
+                throw new RuntimeException('Unable to read quarantined document for promotion.');
+            }
+            try {
+                if (! Storage::disk($approvedDisk)->writeStream($document->storage_key, $source)) {
+                    throw new RuntimeException('Unable to write private document during promotion.');
+                }
+            } finally {
+                if (is_resource($source)) {
+                    fclose($source);
+                }
+            }
+
+            $approvedPath = Storage::disk($approvedDisk)->path($document->storage_key);
+            if (! hash_equals($document->sha256, hash_file('sha256', $approvedPath))) {
+                Storage::disk($approvedDisk)->delete($document->storage_key);
+                throw new RuntimeException('Document hash changed during promotion.');
+            }
+
@@ -114 +138,0 @@
-
@@ -117,4 +141,2 @@
-                    'id' => (string) Str::ulid(),
-                    'resource_type' => ClinicalDocument::class,
-                    'resource_id' => $document->id,
-                    'status' => 'pending',
+                    'id' => (string) Str::ulid(), 'resource_type' => ClinicalDocument::class,
+                    'resource_id' => $document->id, 'status' => 'pending',
@@ -122,2 +144 @@
-                    'created_at' => now(),
-                    'updated_at' => now(),
+                    'created_at' => now(), 'updated_at' => now(),
@@ -126 +146,0 @@
-
@@ -128,4 +148,9 @@
-        });
-
-        $quarantine->delete($document->storage_key);
-        $attempt->update(['status' => 'approved', 'engine' => $result->engine, 'finished_at' => now()]);
+            $attempt->update(['status' => 'approved', 'engine' => $result->engine, 'finished_at' => now()]);
+
+            return DocumentStatus::Approved;
+        });
+
+        // Never remove the original before the verdict transaction commits.
+        if ($outcome === DocumentStatus::Approved || $outcome === DocumentStatus::Rejected) {
+            $quarantine->delete($sourceDocument->storage_key);
+        }
@@ -136,15 +161,17 @@
-        $document = ClinicalDocument::query()->find($this->documentId);
-
-        if ($document === null || $document->status === DocumentStatus::Deleted) {
-            return;
-        }
-
-        $document->forceFill([
-            'status' => DocumentStatus::ScanFailed,
-            'scan_error_code' => class_basename($exception),
-            'scan_completed_at' => now(),
-        ])->save();
-
-        ScanAttempt::query()->where('document_id', $document->id)->whereNull('finished_at')->update(['status' => 'failed', 'error_code' => class_basename($exception), 'finished_at' => now()]);
-
-        $this->audit($document, 'failed', 'scanner_unavailable');
+        DB::transaction(function () use ($exception): void {
+            $document = ClinicalDocument::query()->lockForUpdate()->find($this->documentId);
+            if ($document === null || $document->deleted_at !== null
+                || ! in_array($document->status, [DocumentStatus::Quarantined, DocumentStatus::Scanning], true)) {
+                return;
+            }
+
+            $document->forceFill([
+                'status' => DocumentStatus::ScanFailed,
+                'scan_error_code' => class_basename($exception),
+                'scan_completed_at' => now(),
+            ])->save();
+            ScanAttempt::query()->where('document_id', $document->id)->whereNull('finished_at')->update([
+                'status' => 'failed', 'error_code' => class_basename($exception), 'finished_at' => now(),
+            ]);
+            $this->audit($document, 'failed', 'scanner_unavailable');
+        });
```
