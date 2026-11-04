# E-Presensi — AI Web Innovation Challenge, Bali DigiFest 2026

## Project Name

**E-Presensi — Liveness-Aware Attendance Trust Layer (competition prototype)**

## Description

E-Presensi is a Laravel attendance application for school students and administrators. This competition branch preserves the client's existing attendance workflows and adds optional, feature-flagged Trust Engine and liveness challenge features.

The client's original Laravel starter README is preserved at [docs/README.client.md](docs/README.client.md).

## Problem & Target User

Schools need a practical way to record student arrival/departure and review attendance. The existing application serves students who record attendance and school staff who manage students and review attendance reports. The competition prototype explores additional integrity signals without replacing the client's attendance system.

## Key Features

- Existing GPS/geofence and photo attendance workflows.
- Existing browser-based face recognition attendance and student face enrollment.
- Student and administrator authentication, student/jurusan management, and attendance reports.
- Optional shadow Trust Engine that records derived face/GPS/attendance-consistency scores.
- Optional Phase 3a liveness challenge and admin-only manual review queue, enabled only by `FEATURE_TRUST_ENGINE_ENFORCE`.
- Optional, versioned consent ledger and face-processing gate for enrollment, descriptors, face attendance, and ZIP enrollment, enabled only by `FEATURE_COMPETITION_CONSENT`.
- Optional read-only AI Evaluation dashboard for recorded challenge data and manually imported genuine/print/screen trials, enabled only by `FEATURE_AI_EVALUATION_DASHBOARD`.
- Optional paginated audit viewer, enabled only by `FEATURE_COMPETITION_AUDIT_VIEWER`.
- Optional, synthetic-NISN-only competition-data erasure command, enabled only by `FEATURE_COMPETITION_DATA_ERASURE` together with the consent gate.
- Competition-only synthetic student/attendance seeder and trust/liveness-data retention purge commands.
- Competition flags default to `false`; the competition features are not enabled by default.

The implemented competition feature flags are `FEATURE_TRUST_ENGINE_SHADOW`, `FEATURE_TRUST_ENGINE_ENFORCE`, `FEATURE_COMPETITION_CONSENT`, `FEATURE_AI_EVALUATION_DASHBOARD`, `FEATURE_COMPETITION_AUDIT_VIEWER`, and `FEATURE_COMPETITION_DATA_ERASURE`. Their defaults and related retention/TTL parameters are listed in [.env.example](.env.example). Anomaly detection and a competition navigation master switch are not implemented and have no active flags.

## Tech Stack

- PHP 8.2+ and Laravel 12.
- Blade templates and JavaScript.
- face-api.js 0.22.2 for pre-existing, browser-side face detection, descriptors, and matching.
- SQLite in-memory for automated tests; deployment database is environment-configured.
- Vite for frontend asset builds.

## AI Implementation

The pre-existing face recognition uses face-api.js in the browser to extract 128-value face descriptors and compare them using Euclidean distance. It is not a server-side face model.

The competition Trust Engine in Phase 2 is **rule-based scoring**, not a trained or generative AI model. It calculates component scores from face distance, GPS validity/geofence distance, and attendance-time consistency. In shadow mode, it stores a score only after an existing attendance request succeeds; it does not accept or reject attendance.

Phase 3a adds an optional randomized blink/head-turn challenge. The browser derives a small liveness signal from face landmarks; the server issues a session-bound, expiring, single-use nonce, checks the submitted derived signal, applies configurable score thresholds, and can send medium/unavailable cases to an admin review queue. The enforcement score uses configurable weights of face 30, GPS 25, consistency 15, and liveness 30; acceptance requires passed liveness and a score of at least 70. This is a rule-based challenge and consistency check, **not an AI liveness model**.

The existing face `confidence`/distance is still calculated and supplied by the browser. Phase 3b (server-side descriptor distance verification) is not implemented, so a client can still forge that value. Liveness evidence is also client-derived and is not cryptographic proof. Review actions annotate only competition tables; the existing attendance table is not changed. See [the Phase 3a manual test guide](docs/PHASE3A_MANUAL_TEST.md).

Phase 4 adds a consent ledger and conditional gate without changing client controllers or views. The default consent notice is explicitly a draft: its contact placeholder and text must be reviewed, versioned, and approved before real consent can be recorded. Underage participation must follow the school's or guardian's written process. Withdrawal blocks further competition face processing but does not automatically erase existing client-table biometrics or attendance.

The AI Evaluation dashboard is a read-only report, not a model or a performance claim. It computes FAR/FRR only from imported, actually recorded trials; review is separate from the decided denominator. The repository snapshot is currently header-only with `n=0`, so no FAR, FRR, or attack-rejection rate is claimed. Import and export instructions are in [the Phase 4 manual guide](docs/PHASE4_MANUAL_TEST.md).

AI coding assistance used: **GitHub Copilot (Copilot SDK in VS Code)**. The generated/assisted changes were reviewed and tested before this local commit.

## Architecture / Data Flow

1. A student authenticates with the existing student guard.
2. If `FEATURE_TRUST_ENGINE_ENFORCE=true`, an injected external script requests a random challenge and derives blink/head-turn evidence from the existing live camera stream. Middleware validates the nonce, user/session binding, expiry, one-time use, score, and liveness decision before calling the existing face-attendance controller.
3. High scores are accepted only with passed liveness; medium scores and the bounded `liveness_unavailable` fallback proceed through the existing controller and enter the competition review queue only after a successful attendance response. Review decisions change only competition queue/audit tables.
4. If `FEATURE_TRUST_ENGINE_SHADOW=true`, middleware observes successful attendance and dispatches its existing Phase 2 Trust Engine event in `terminate()`.
5. The Phase 2 listener writes to `competition_trust_scores`; its `raw_signals` stores derived distance, GPS accuracy, and time difference, not raw coordinates.
6. Challenge and review records contain derived scores/status/reasons, not face photos, descriptors, coordinates, or submitted proof samples.
7. With `FEATURE_COMPETITION_CONSENT=true`, an admin records a versioned consent event. Middleware blocks face enrollment, face attendance, and ZIP extraction without current consent, and withholds descriptors for students without current consent. Withdrawal blocks future face processing but never deletes client-table data.
8. With `FEATURE_AI_EVALUATION_DASHBOARD=true`, the admin dashboard aggregates challenge verdict/reason/liveness fields and manually imported `competition_evaluation_trials`. CSV imports require anonymous tester codes, are validated atomically, and are deduplicated. `competition:export-evaluation-snapshot` writes only the trial fields and methodology to `docs/evaluation/`.
9. With `FEATURE_COMPETITION_AUDIT_VIEWER=true`, opening the review queue, evaluation dashboard, or audit page is recorded in the existing competition audit table. The audit viewer is paginated and read-only.
10. Purge commands are manual; no automatic purge schedule is configured. `competition:purge-liveness-data --dry-run` previews expired challenges/queue/audit rows, superseded consent history, and old evaluation trials. It keeps the latest consent event for each NISN. `competition:purge-trust-scores --dry-run` previews trust-score cleanup. Configured retention defaults are 90 days for trust scores/challenges/review/audit, 365 days for superseded consent history, and 180 days for evaluation trials.
11. `competition:erase-synthetic-subject {nisn} --dry-run` previews per-NISN deletion from competition tables; actual deletion requires `--confirm`, and the command accepts only NISNs beginning with `9999`. It preserves a new deletion audit event, does not touch client `siswa`/`presensi` tables, and does not delete evaluation trials because those trials are not linked to NISN.

With competition flags disabled, attendance storage and responses follow the client's existing behavior. With enforce enabled, a failed/invalid challenge is rejected before the existing face-attendance controller; review decisions do not rewrite attendance rows.

## Run Instructions

Requirements: PHP 8.2+, Composer, and Node.js/npm for frontend builds.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

For an isolated competition database, run **only** the synthetic seeder:

```powershell
php artisan db:seed --class=CompetitionSyntheticSeeder
```

Do not run `php artisan db:seed` without a class on the competition instance: the existing `DatabaseSeeder` invokes the client `SiswaSeeder` and creates a default admin account. Set `COMPETITION_DEMO_PASSWORD` in the private instance environment or leave it empty to generate a random demo password, printed once during seeding. Never commit or publish demo credentials.

All competition feature flags default to `false`. Enable competition flags only on a separate competition instance, never on client production.

The consent notice must be reviewed and approved before changing its `text_approved` setting. See [the Phase 4 manual guide](docs/PHASE4_MANUAL_TEST.md) for controlled camera trials, CSV import/export, audit checks, and manual purge steps.

Run tests by suite:

```powershell
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

The PHPUnit configuration references `tests/DeployFix`, which is absent from this repository; the unfiltered suite fails because that directory is missing.

## Deployment URL

Not published. No competition deployment URL is configured in this repository.

## Demo Account

Synthetic student accounts are created by `CompetitionSyntheticSeeder` with NISN values using the `9999` prefix. Demo passwords are supplied through the private `COMPETITION_DEMO_PASSWORD` environment setting or generated once during seeding. **No password is included in this repository.** Judge credentials will be provided by the organizers through their form.

## Known Limitations

- Phase 2 shadow scoring and Phase 3a challenge scoring are rule-based; neither is a trained ML model. Phase 2 retains its original 40/40/20 component weights; Phase 3a has a separate configurable 30/25/15/30 decision score.
- Phase 3a liveness metrics are calculated in the browser, so they add interaction friction and signal consistency only. They do not prevent deepfakes or camera-feed injection and are not cryptographic proof.
- The legacy face distance remains browser-supplied until Phase 3b; direct clients can forge `confidence`. This release does not claim server-side face verification.
- If liveness evaluation is unavailable after a valid challenge, a limited daily review fallback can still record attendance when face and GPS minimum scores pass. Such an attendance appears in the existing client attendance record while the competition queue awaits review.
- Photo-print/screen behavior requires a manual, consent-based camera test; no anti-spoofing result is claimed until that test is performed.
- The default consent wording is an unapproved draft with a contact placeholder. It must be reviewed and versioned before use; it does not claim legal compliance. The existing `User` model has no role column, so admin-only competition pages rely on the existing `auth:user` guard and do not add a role system.
- The evaluation snapshot currently has no manual trial rows (`n=0`). Metrics will remain unavailable until real, anonymized manual trials are imported; any eventual results are convenience-sample evidence, not a guarantee.
- Reasons and notes in trial CSVs are free text. The importer checks common identifier patterns and requires tester codes like `T1`, but a human must inspect the snapshot for names or other identifiers before commit.
- Retention purge is not scheduled automatically. The configured durations are defaults and require an operator to run the dry-run, inspect counts, and invoke purge manually.
- `FEATURE_TRUST_ENGINE_ENFORCE` defaults false. With all flags false, competition routes and script injection are not registered or run.
- No camera trials have been recorded: the evaluation snapshot is `n=0`, so no FAR/FRR or print/screen rejection result is claimed.
- GPS checks depend on browser/device location and the existing server-side geofence validation.
- `phpunit.xml` references an absent `tests/DeployFix` suite; run Unit and Feature suites separately.
- No deployment URL or team-member roster was supplied for this repository.

## Third-party Resources

- Laravel framework: [MIT license](https://github.com/laravel/framework/blob/12.x/LICENSE.md).
- face-api.js 0.22.2: [repository and license](https://github.com/justadudewhohacks/face-api.js).
- Tabler UI assets: [Tabler](https://tabler.io/).
- Leaflet maps and OpenStreetMap tiles: [Leaflet](https://leafletjs.com/) and [OpenStreetMap](https://www.openstreetmap.org/copyright).
- AI coding assistant: GitHub Copilot (Copilot SDK in VS Code).

## Team Members

Not supplied in the repository; add the authorized team roster before public submission.
