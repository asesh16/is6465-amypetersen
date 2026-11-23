-- Lab 7 upgrade: adds user accounts to the Lab 6 database.
-- Run once, after setup-database.sql:
--   sqlite3 tutoring.db < upgrade-to-user-management.sql
-- Schema follows the IS 6465 Authentication Reading Handout, section 2:
--   * users holds the account, password hash, single role and status
--   * students.user_id links a student profile to its account (UNIQUE, nullable)
-- Administrator and Tutor accounts have no students row.
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('Student', 'Tutor', 'Administrator')),
    status TEXT NOT NULL DEFAULT 'Active' CHECK(status IN ('Active', 'Inactive')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- SQLite cannot add a UNIQUE column with ALTER TABLE, so uniqueness is a separate index.
-- Existing Lab 6 students keep user_id NULL until a matching account registers.
ALTER TABLE students ADD COLUMN user_id INTEGER REFERENCES users(user_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_students_user_id ON students(user_id);
