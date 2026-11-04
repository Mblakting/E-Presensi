# Phase 4 manual consent, evaluation, and privacy checks

Run these checks only on an isolated competition instance with synthetic records or a consenting adult volunteer. Do not use real student biometrics for this demo.

## 1. Review the consent text before enabling its workflow

1. Read `config/competition.php` under `competition.consent.text`.
2. Replace the contact placeholder, assign a new `version`, and set `text_approved` to `true` only after the user has reviewed the exact text. The default is an unapproved draft and server-side grant requests must remain blocked.
3. This text is not legal advice or a statement of legal compliance. For a minor, use the school's or guardian's documented consent process. For competition trials, use synthetic accounts or consenting adults only.
4. Apply migrations on the competition database, then enable `FEATURE_COMPETITION_CONSENT=true` only on that instance.

## 2. Verify consent gates

1. With no consent for a synthetic NISN, open the face enrollment page and attempt to save a descriptor. The operation must be blocked before the existing client controller stores anything.
2. Try face attendance and the face descriptor endpoint without active consent; they must be blocked and point to the existing non-face attendance path.
   The descriptor endpoint must query and return only currently consented students; it must not invoke the legacy controller that reads every stored descriptor.
3. Upload a ZIP containing a synthetic NISN with no active consent. The request must be rejected before the legacy extractor writes any temporary image.
4. Record a grant only after the configured consent text and the relevant written process are reviewed. Verify that the grant stores its version and SHA-256 text hash.
5. Confirm that enrollment and face attendance work for a granted synthetic/adult test account. Revoke consent and repeat the requests; they must be blocked. The revocation does not erase existing descriptor, photo, or attendance data from client tables.
6. Confirm that a new consent text version requires a new grant. Prior consent events remain append-only unless an expired superseded history row is removed by the manual purge.

## 3. Record real manual evaluation trials

The automated tests use synthetic signals and are not anti-spoofing evidence. For a manual trial, record only what actually happened:

1. Use a consenting adult volunteer whose face is enrolled on the isolated instance.
2. Test a genuine live face, then a printed photograph, then a photograph displayed on a screen. Repeat as needed and record each attempt separately; do not infer a result from a small number of attempts.
3. For each attempt, record `trial_type` (`genuine`, `print`, or `screen`), the observed `outcome` (`accepted`, `rejected`, or `review`), a short reason, an anonymous tester code such as `T1`, the date (`YYYY-MM-DD`), and a brief note.
4. Do not include names, NISN, contact details, photos, descriptors, credentials, or identifying information in reasons or notes. The importer rejects tester names, email-like strings, phone-like strings, and long numeric identifiers, but free text still requires human privacy review.
5. Save the CSV using the exact header:

   ```csv
   trial_type,outcome,reason,tester_code,tested_at,notes
   ```

6. Import from a local path (do not commit the raw import file):

   ```powershell
   php artisan competition:import-evaluation-trials .\private-manual-trials.csv
   ```

7. Review the dashboard. It reports `n`, keeps `review` separate from the decided denominator, and shows FAR/FRR only when a denominator exists. A small sample is descriptive, not a guarantee.
8. Export the anonymized repository snapshot after reviewing for accidental identifying text:

   ```powershell
   php artisan competition:export-evaluation-snapshot
   ```

   Inspect `docs/evaluation/manual_trials.csv` and `docs/evaluation/METHODOLOGY.md` before committing them. If no real trials exist, keep the header-only CSV and honest zero-trial methodology; never add example performance numbers.

## 4. Verify audit and manual retention

1. As an admin-guard user, open the review queue, AI Evaluation, and Audit Log pages. Confirm the corresponding access events appear. A student-guard session must be redirected from each admin page/action.
2. Confirm consent grant/withdrawal, successful trial imports/exports, and purge actions are auditable. The viewer is read-only and does not display IP address or user-agent.
3. Purge is manual only; no scheduler is configured. First preview:

   ```powershell
   php artisan competition:purge-liveness-data --dry-run
   php artisan competition:purge-trust-scores --dry-run
   ```

4. Only run either command without `--dry-run` after checking the counts and confirming that its configured retention is acceptable. The latest consent event per NISN must survive. These commands do not delete data from client `siswa` or `presensi` tables, including descriptors, photos, and attendance.

The existing `User` model has no role column. Competition admin pages use the application's existing `auth:user` guard; this is the existing admin-identity assumption, not a newly added role/permission system.

## 5. Preview and erase a synthetic subject's competition data

1. Enable `FEATURE_COMPETITION_DATA_ERASURE=true` and `FEATURE_COMPETITION_CONSENT=true` only on the isolated competition instance.
2. Preview an NISN beginning with `9999`; no deletion should occur:

   ```powershell
   php artisan competition:erase-synthetic-subject 9999000001 --dry-run
   ```

3. Review the counts. Only then run the same command with `--confirm`. The command deletes the subject's competition trust, challenge, queue, consent, and subject audit rows, then writes a new erasure audit event. It does not modify client `siswa` or `presensi` rows. It cannot delete evaluation trials because trials are not linked to a subject NISN.
4. Confirm the latest consent is absent (so face processing is denied when the consent gate is active), the erasure event remains in the audit viewer, other synthetic NISNs remain, and the subject's client-table rows remain unchanged.
5. A non-`9999` NISN, either disabled flag, or a call without `--confirm` must be rejected before deletion.
