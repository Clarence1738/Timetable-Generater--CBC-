# Timetable Management System

A PHP-based school timetable management system  that supports:
- Subject, grade, stream, and teacher setup
- Grade-level timetable configuration (periods/day)
- Subject lesson requirements per grade
- Automatic timetable generation
- Class timetable viewing
- Teacher timetable viewing
- Print-ready timetable pages

This project uses a lightweight backend API (FastRoute + Auryn + Medoo) and HTML/CSS/JavaScript frontend pages.

---

## Tech Stack

### Backend
- PHP (XAMPP)
- Medoo (database layer)
- FastRoute (routing)
- Auryn (dependency injection)
- Dompdf (available for PDF/report use)

### Frontend
- HTML, CSS, vanilla JavaScript
- SweetAlert for user notifications

### Database
- MySQL / MariaDB

---

## Project Structure

- `backend/` — API and business logic
  - `app/src/Controllers/` — API controllers (`AuthController`, `TeachersController`, `ClassesController`, `TimetableController`)
  - `app/src/Services/CacheService.php` — request-level in-memory cache for repeated lookups
  - `app/src/bootstrap.php` — app bootstrapping, error handling, log configuration
  - `app/src/routes.php` — API route definitions
  - `config/config.php` — database configuration
  - `sql/timetable.sql` — base schema
  - `sql/insert_admin.sql` — admin seed
- `Pages/` — frontend pages
  - `settings/timetable.html` — config, requirements, generate, and class view
  - `settings/teachers.html` — teacher settings UI
  - `views/class-timetable.html` — dedicated class timetable view
  - `views/teacher-timetable.html` — dedicated teacher timetable view
- `js/shared-pages.js` — shared frontend logic for timetable pages
- `js/shared-pages.min.js` — deployed shared script bundle
- `css/shared-pages.min.css` — shared styling bundle
- `TIMETABLE_VIEW_TESTING_GUIDE.md` — focused testing guide

---

## Prerequisites

- XAMPP running (Apache + MySQL)
- PHP compatible with installed dependencies
- Composer installed

---

## Setup Instructions

1. Place project under XAMPP htdocs (already done in this workspace).
2. Create database and import SQL:
   - Import `backend/sql/timetable.sql`
   - Import `backend/sql/insert_admin.sql`
3. Install backend dependencies:
   - `cd backend`
   - `composer install`
4. Verify database connection settings in `backend/config/config.php`.
5. Ensure Apache is serving:
  - `http://localhost/timetable/`

---

## Default Local URLs

- Main settings page:
  - `http://localhost/timetable/Pages/settings/timetable.html`
- Class timetable page:
  - `http://localhost/timetable/Pages/views/class-timetable.html`
- Teacher timetable page:
  - `http://localhost/timetable/Pages/views/teacher-timetable.html`
- API base:
  - `http://localhost/timetable/backend/public/index.php`

---

## Core Functional Flow

1. Login
2. Configure periods/day by grade-level groups
3. Set lesson requirements per grade
4. Generate timetable
5. View timetable by class or by teacher
6. Print timetable

---

## API Routes (Backend)

### Auth
- `POST /auth/login`
- `GET /auth/check`
- `GET /auth/logout`

### Settings
- `GET /settings/classes`
- `GET /settings/subjects`
- `POST /settings/subjects`
- `POST /settings/subjects/delete`
- `GET /settings/subject-stats`
- `GET /settings/grades`
- `POST /settings/grades`
- `POST /settings/grades/delete`
- `GET /settings/streams`
- `POST /settings/streams`
- `POST /settings/streams/delete`
- `GET /settings/teachers`
- `POST /settings/teachers`
- `POST /settings/teachers/delete`

### Timetable
- `GET /timetable/config`
- `POST /timetable/config`
- `GET /timetable/requirements`
- `POST /timetable/requirements`
- `POST /timetable/generate`
- `GET /timetable/view`
- `GET /timetable/teacher-view`

All routes are defined in `backend/app/src/routes.php`.

---

## Caching and Performance

### 1) Browser/HTTP Caching
- Static bundles use versioned query parameters (for cache busting), e.g. `shared-pages.min.js?v=1.3`.
- HTTP caching + compression are configured with `.htaccess`.

### 2) Backend Request-Level Caching
- Implemented in `backend/app/src/Services/CacheService.php`.
- Uses in-memory static cache (`self::$cache`) during a request lifecycle.
- Prevents repeated DB queries for frequently reused datasets:
  - grades, streams, streams-by-grade, subjects
  - periods-by-level
  - requirements-by-grade
  - teacher maps

### 3) Database Performance
- Performance indexes are included/applied for timetable-heavy tables.

---

## UX Improvements Implemented

- Loading states for actions:
  - Save Configuration
  - Load Requirements
  - Save Requirements
  - Generate Timetable
  - View Timetable (class/teacher)
- Clear no-data user feedback:
  - if no timetable exists yet, users are told to generate first
- Better inline status text while async actions run

---

## Logging and Error Handling

- App log file: `backend/app/logs/error.log`
- Log rotation guard added in bootstrap:
  - rotates when log exceeds ~2MB
  - keeps active file from growing indefinitely
- Verbose cache info logs are disabled by default to reduce noise.

---

## Testing

Use the companion guide:
- `TIMETABLE_VIEW_TESTING_GUIDE.md`

Quick check:
1. Generate timetable from settings page
2. View class timetable
3. View teacher timetable
4. Confirm no JS console errors
5. Confirm API responses are success for generated data

---

## Troubleshooting

### Timetable not showing
- Ensure a stream/teacher is selected
- Ensure timetable has been generated
- Check API response in browser Network tab
- Check backend logs in `backend/app/logs/error.log`

### Stale frontend behavior after changes
- Hard refresh browser (`Ctrl + F5`)
- Confirm script query version updated (currently `v=1.3`)

### Auth issues
- Verify session/cookies are enabled
- Test `GET /auth/check` endpoint

### DB connection issues
- Verify `backend/config/config.php` credentials
- Ensure MySQL service is running in XAMPP

---

## Security / Production Notes

Before production deployment:
- Change default DB credentials
- Disable PHP error display (already off) and keep secure logging
- Use HTTPS and secure cookie settings
- Restrict CORS origins appropriately
- Back up database and logs regularly

---

## Maintenance Notes

When updating frontend shared logic:
1. Edit `js/shared-pages.js`
2. Sync/copy to `js/shared-pages.min.js`
3. Bump query version in pages (e.g. `v=1.4`)
4. Hard refresh browser to validate

---

## Status

Project is in a completed, deployable local state with:
- functional timetable generation and viewing
- performance optimizations
- controlled logging
- improved user loading/error feedback

If needed later, Redis/APCu can be added as cross-request cache, but current implementation is intentionally lightweight and sufficient for this scope.
