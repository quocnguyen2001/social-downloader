# AGENTS

Use this file as the high‑level playbook before you touch anything. It captures the conventions, tooling, and edge cases we keep running into on **social-downloader** (Laravel 12 + Filament 3).

## 1. Project Snapshot
- API-first Laravel app that exposes video-extraction endpoints plus a Filament admin.
- Heavy use of yt-dlp via `app/Services/VideoExtraction`. Read `VIDEO_EXTRACTION_README.md` for the in-depth architecture.
- Queues (Horizon) handle extraction + uploads; most write paths assume a queue worker is running.
- Storage targets Cloudflare R2 (S3 API). Local disk is only a temp/cache layer.

## 2. Environment Setup (local)
1. `cp .env.example .env` then set secrets (DB, Redis, AWS/R2, mail).
2. `composer install` (PHP >= 8.4, ext-intl required).
3. `npm install` (Node 18+ recommended for Vite 6).
4. `php artisan key:generate`.
5. `php artisan migrate --seed` (adds demo admin + settings).
6. yt-dlp and ffmpeg must exist on PATH or configure `YT_DLP_BINARY_PATH` / `FFMPEG_BINARY_PATH`.
7. Optional but common: `php artisan filament:user` to create back-office access.

### Frequently tweaked env vars
- `YT_DLP_*` (binary, timeout, cookies). `YT_DLP_USING_COOKIES=true` requires `YT_DLP_COOKIES_FILE_PATH` pointing to either an absolute path or a path under project base/storage.
- `VIDEO_EXTRACTION_*` group controls driver toggles, temp directories, and rate-limits (`config/video-extraction.php` is the source of truth).
- `FILESYSTEM_DISK`, `AWS_*` for R2 uploads.
- `QUEUE_CONNECTION`, `HORIZON_PREFIX`.

## 3. Everyday Commands
- Start everything locally (PHP server + queue + logs + Vite) using `composer dev` (see `composer.json` script for the `concurrently` layout).
- Run queues in prod/staging via Horizon: `php artisan horizon`.
- Tests: `php artisan test` (or `composer test`). Feature coverage is light; add focused tests when you touch critical flows.
- Code style: `./vendor/bin/pint`.
- Asset builds: `npm run dev` for watch, `npm run build` for deployable assets.

## 4. Video Extraction Flow (read before editing)
1. `PlatformDetector` decides the driver from a URL.
2. A driver (see `app/Services/VideoExtraction/Drivers`) uses `YtDlpService` to fetch metadata + formats.
3. Drivers emit `ExtractionResult` → persisted via events/jobs.
4. Downloads are stored temporarily under `config('video-extraction.temp.directory')`, uploaded via `FileUploadService`, and optionally cleaned up.
5. Thumbnail handling lives in `ThumbnailService`; it saves to the `public` disk by default and returns disk/path pairs.

Key gotchas:
- Cookie handling: when `YT_DLP_USING_COOKIES=true`, make sure the cookie file actually lives either at `base_path($relative)` or in `storage/app`. The service logs loudly if the file is missing.
- Instagram formats are DASH; prefer `VideoFormat::isSuitableForDownload()` and the Instagram driver’s scoring logic instead of ad-hoc selection.
- Always pass through `FilenameService` when you need deterministic names; downstream cleanup relies on the hash format it generates.
- Queue jobs should stay idempotent—most of them already guard on `DownloadSession` status. Mirror that if you add new jobs.

## 5. Filament Admin
- Custom resources live under `app/Filament`. Keep components server-driven; no Livewire v2 leftovers.
- When updating Filament, run `php artisan filament:upgrade` (already part of composer scripts) and rebuild assets (`npm run dev:filament` for live reload, `npm run build:filament` for production).
- Tailwind plugins are configured via Vite; ensure new CSS utilities are purged or the build size balloons.

## 6. Testing & QA Expectations
- At minimum run `php artisan test` before handing off work. If you touch extraction logic, add/extend tests under `tests/Unit/` (e.g., `VideoFormatInstagramQualityMappingTest`) or integration tests hitting the controller.
- For command/event flows, favor feature tests with mocked services rather than broad end-to-end tests—yt-dlp/ffmpeg should stay mocked.
- Manual smoke checklist when changing extraction:
  - `POST /api/v1/extract` (guest + authenticated token) returns session id.
  - `GET /api/v1/extract/status/{id}` cycles through `pending → metadata → ready`.
  - Filament dashboard loads (run `npm run dev` for assets).

## 7. Coding Standards & Workflow
- Follow PSR-12, strict types where possible. Existing services already declare `declare(strict_types=1);`.
- Dependency injection > facades unless there’s a compelling reason. When you do use facades, wrap in services for easier testing.
- Prefer `apply_patch` for small edits, but never revert user changes you didn’t make.
- Planning: if a task spans multiple steps, use the built-in plan tool (see system instructions).
- Logs go to `storage/logs/laravel.log`. When debugging extraction, tail that log plus Horizon output.
- Keep comments intentional—only where non-obvious logic needs a breadcrumb.

## 8. Troubleshooting Cheatsheet
- **yt-dlp fails**: check `config('video-extraction.yt_dlp.binary_path')` and the cookie path logs. Run `yt-dlp --version` manually.
- **Thumbnails missing**: confirm `public` disk is configured and `ThumbnailService` can write to `storage/app/public`, then `php artisan storage:link`.
- **R2 upload issues**: `FileUploadService` expects `filesystems.disks.r2` to be S3-compatible. Ensure bucket CORS allows `GET` from frontend origins.
- **Filament 419 errors**: run `php artisan config:clear` and ensure `SESSION_DOMAIN` matches your dev host.

## 9. Reference Docs
- `VIDEO_EXTRACTION_README.md` – full architecture & API contract for extraction.
- `README.md` – vanilla Laravel docs (mostly boilerplate).
- `docs/` directory – ad-hoc specs (API keys, uploads, membership plans, etc.).
- `*.md` in repo root – task-specific briefs (API_KEY_AUTHENTICATION.md, etc.).

Keep this file updated when workflows change so new agents can ramp up quickly.
