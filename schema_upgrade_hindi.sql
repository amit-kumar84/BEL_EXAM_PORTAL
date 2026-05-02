-- ============================================================================
-- BEL Kotdwar Exam Portal — HINDI/BILINGUAL upgrade (OFFLINE, runs on intranet)
-- ----------------------------------------------------------------------------
-- This file adds the columns needed to store Hindi versions of question text,
-- options and short-answer correct text alongside their English originals.
-- The app also auto-adds these columns at runtime via
--   ensure_bilingual_columns()  in includes/helpers.php
-- so running this script manually is optional.
--
-- Run ONCE on an existing install (in phpMyAdmin → SQL tab, or via CLI):
--   mysql -u root -p bel_exam_portal < schema_upgrade_hindi.sql
-- ============================================================================

USE bel_exam_portal;

ALTER TABLE questions
  ADD COLUMN IF NOT EXISTS question_text_hi TEXT NULL AFTER question_text,
  ADD COLUMN IF NOT EXISTS correct_text_hi  TEXT NULL AFTER correct_text;

ALTER TABLE question_options
  ADD COLUMN IF NOT EXISTS opt_text_hi TEXT NULL AFTER opt_text;

ALTER TABLE exams
  ADD COLUMN IF NOT EXISTS instructions_hi TEXT NULL AFTER instructions;
