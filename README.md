# E-Presensi — AI Web Innovation Challenge, Bali DigiFest 2026

## Project Name

**E-Presensi — Liveness-Aware Attendance Trust Layer (competition prototype)**

## Description

E-Presensi is a Laravel attendance application for school students and administrators. This competition branch preserves the client's existing attendance workflows and adds an optional, feature-flagged Trust Engine in shadow mode. The Trust Engine records a rule-based score after a successful attendance request; in the current phase it does not change the attendance decision.

The client's original Laravel starter README is preserved at [docs/README.client.md](docs/README.client.md).

## Problem & Target User

Schools need a practical way to record student arrival/departure and review attendance. The existing application serves students who record attendance and school staff who manage students and review attendance reports. The competition prototype explores additional integrity signals without replacing the client's attendance system.

## Key Features

- Existing GPS/geofence and photo attendance workflows.
- Existing browser-based face recognition attendance and student face enrollment.
- Student and administrator authentication, student/jurusan management, and attendance reports.
- Optional shadow Trust Engine that records derived face/GPS/attendance-consistency scores.
- Competition-only synthetic student/attendance seeder and trust-score retention purge command.
- Competition flags default to `false`; the competition features are not enabled by default.

## Tech Stack

- PHP 8.2+ and Laravel 12.
- Blade templates and JavaScript.
- face-api.js 0.22.2 for pre-existing, browser-side face detection, descriptors, and matching.
- SQLite in-memory for automated tests; deployment database is environment-configured.
- Vite for frontend asset builds.

## AI Implementation

The pre-existing face recognition uses face-api.js in the browser to extract 128-value face descriptors and compare them using Euclidean distance. It is not a server-side face model.

The competition Trust Engine in Phase 2 is **rule-based scoring**, not a trained or generative AI model. It calculates component scores from face distance, GPS validity/geofence distance, and attendance-time consistency. In shadow mode, it stores a score only after an existing attendance request succeeds; it does not accept or reject attendance.

**Phase 3 liveness challenge-response is planned but not implemented.** No liveness guarantee is claimed in this release.

AI coding assistance used: **GitHub Copilot (Copilot SDK in VS Code)**. The generated/assisted changes were reviewed and tested before this local commit.

## Architecture / Data Flow

1. A student authenticates with the existing student guard.
2. The existing attendance controller validates the submitted face match / GPS location and records attendance using the existing workflow.
3. If `FEATURE_TRUST_ENGINE_SHADOW=true`, middleware observes a successful response and dispatches a Trust Engine event in `terminate()`, after the response is sent.
4. The listener calculates rule-based scores and writes a record to `competition_trust_scores`; failures are logged and do not rewrite the attendance response.
5. Trust `raw_signals` stores derived distance, GPS accuracy, and time difference, not raw coordinates.

The competition score is an advisory evaluation signal. Existing attendance storage still follows the client's existing behavior.

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

- Phase 2 Trust Engine is shadow-only and rule-based; it is not an ML model and does not change the existing attendance decision.
- Phase 3 liveness challenge-response and manual review workflow are not implemented.
- Existing face matching runs in the browser. The current system does not establish cryptographic proof that a live person, rather than a replay/injected camera feed, produced client-side signals.
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
