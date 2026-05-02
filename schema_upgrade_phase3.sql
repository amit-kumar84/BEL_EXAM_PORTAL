-- ============================================================================
-- BEL Kotdwar Exam Portal — Phase 3 SQL upgrade (per-exam violation toggles,
-- exam-access grants, soft-delete + permissions auto-migrations).
-- The application also auto-applies these on every admin page load via
--   ensure_phase3_migrations()  in includes/helpers.php
-- so running this file manually is OPTIONAL.
-- ============================================================================
USE bel_exam_portal;

-- Per-exam proctor / violation switches (super-admin controlled)
ALTER TABLE exams
  ADD COLUMN IF NOT EXISTS violation_config TEXT NULL,
  ADD COLUMN IF NOT EXISTS force_fullscreen TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS max_violations INT NOT NULL DEFAULT 5;

-- Specific-exam access grants (super-admin → admin)
CREATE TABLE IF NOT EXISTS exam_admin_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id  INT NOT NULL,
  admin_id INT NOT NULL,
  access_level ENUM('view','edit','full') NOT NULL DEFAULT 'view',
  granted_by INT NULL,
  granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_admin (exam_id, admin_id),
  KEY idx_admin (admin_id),
  KEY idx_exam  (exam_id),
  CONSTRAINT fk_eaa_exam FOREIGN KEY (exam_id)  REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_eaa_adm  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Performance indexes for govt-scale record retention
CREATE INDEX IF NOT EXISTS idx_exams_active        ON exams (start_time, end_time, deleted_at);
CREATE INDEX IF NOT EXISTS idx_attempts_status     ON attempts (exam_id, status);
CREATE INDEX IF NOT EXISTS idx_assignments_exam    ON exam_assignments (exam_id);
CREATE INDEX IF NOT EXISTS idx_violations_attempt  ON violations (attempt_id, event_time);

-- Phase 2 columns (for sites that didn't get auto-migration yet) ------------
ALTER TABLE users    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
                     ADD COLUMN IF NOT EXISTS deleted_by INT NULL,
                     ADD COLUMN IF NOT EXISTS deleted_by_name VARCHAR(150) NULL,
                     ADD COLUMN IF NOT EXISTS deleted_by_email VARCHAR(150) NULL;
ALTER TABLE exams    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
                     ADD COLUMN IF NOT EXISTS deleted_by INT NULL,
                     ADD COLUMN IF NOT EXISTS deleted_by_name VARCHAR(150) NULL,
                     ADD COLUMN IF NOT EXISTS deleted_by_email VARCHAR(150) NULL;
ALTER TABLE questions ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
                      ADD COLUMN IF NOT EXISTS deleted_by INT NULL,
                      ADD COLUMN IF NOT EXISTS deleted_by_name VARCHAR(150) NULL,
                      ADD COLUMN IF NOT EXISTS deleted_by_email VARCHAR(150) NULL;
ALTER TABLE question_options ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
                             ADD COLUMN IF NOT EXISTS deleted_by INT NULL;
ALTER TABLE attempts ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
                     ADD COLUMN IF NOT EXISTS deleted_by INT NULL;

CREATE TABLE IF NOT EXISTS admin_permissions (
  admin_id INT PRIMARY KEY,
  perms TEXT NULL,
  view_all_exams TINYINT(1) NOT NULL DEFAULT 0,
  view_all_students TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_u FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Bilingual columns -------------------------------------------------------
ALTER TABLE questions
  ADD COLUMN IF NOT EXISTS question_text_hi TEXT NULL AFTER question_text,
  ADD COLUMN IF NOT EXISTS correct_text_hi  TEXT NULL AFTER correct_text;
ALTER TABLE question_options
  ADD COLUMN IF NOT EXISTS opt_text_hi TEXT NULL AFTER opt_text;
ALTER TABLE exams
  ADD COLUMN IF NOT EXISTS instructions_hi TEXT NULL AFTER instructions;
