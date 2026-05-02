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
