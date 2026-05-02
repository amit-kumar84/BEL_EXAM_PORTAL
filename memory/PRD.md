# BEL Kotdwar Exam Portal — PRD

## Problem statement
Existing PHP exam portal. User wants admin-controlled HARD lockdown in student exam screen:
- Force-block all browser shortcuts on Windows / macOS / Linux
- New toggles for screen-sharing & remote-access detection
- Block extension / AI overlays
- Complete hardcoded lockdown when toggles are on
- Force fullscreen with Esc / F11 blocked
- Student info card on right side of exam screen

## Implemented (this session)
- 7 new toggles added to `default_violation_config()` in `includes/helpers.php`
- 8 new switches in `admin/exams.php` Proctor & Violation Controls UI
- `assets/js/lockdown.js` comprehensive hard-block handler:
  - Mac shortcuts (Cmd+Tab/Q/H/M/Space/W/N/T/`, Cmd+,)
  - Alt shortcuts (Alt+Tab/F4/Space/←/→)
  - All F-keys F1..F12
  - Mac screenshot combos (Cmd+Shift+3/4/5)
  - Extension / AI overlay MutationObserver
  - Clipboard API + drag-drop + cut block
  - getDisplayMedia wrapper (screen-share block)
  - Remote-access heuristics (colour depth, pointer latency, hw concurrency)
  - CSP meta hint injection
- Candidate info card on right sidebar of `student/take-exam.php` with photo/name/roll/DOB/exam-code (bilingual labels)

## Backlog (P1)
- Electron kiosk wrapper for true OS-level key block (Windows key, RDP detection)
- Server-side violation summary on monitor-exam.php for new violation types
- Unit harness for lockdown.js (Playwright) on a static PHP stack

## Session 2 — Sidebar drawer + Admin account controls
- `assets/css/app.css` — admin sidebar now **fixed** on left (`position:fixed`), main content scrolls independently (`overflow-y:auto`), sticky top-bar, mobile hamburger collapse
- `admin/_shell_top.php` — added mobile hamburger toggle + backdrop + data-testids on all nav links
- `admin/admins.php` **Reset** modal now edits **Name + Email + Password** (password optional). Password stored ONLY as bcrypt; `plain_password` column explicitly nulled for admins.
- `admin/admins.php` — new **Edit** button visible only on super admin's own row → opens `superEditModal` with developer-set verification question. Wrong answers are logged as `super_edit_denied`.
- `admin/admins.php` — super admin's email is **masked** (`a••••@••••••••.in`) when displayed to other viewers (only super admin sees own email in plain).
- `includes/config.php` — new constants `SUPER_VERIFY_QUESTION` and `SUPER_VERIFY_ANSWER_HASH` (SHA-256 of trimmed-lowercase answer). Developer-only control.
- `includes/config.php` — `ensureSuperAdmin()` no longer force-resets the super admin password on every request; only seeds once if no super admin exists. This is essential so the super admin CAN change their password.
