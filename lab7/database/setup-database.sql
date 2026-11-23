PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS students (student_id INTEGER PRIMARY KEY, student_name TEXT NOT NULL, student_email TEXT NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS tutoring_requests (request_id INTEGER PRIMARY KEY, student_id INTEGER NOT NULL, course TEXT NOT NULL, topic TEXT NOT NULL, preferred_date TEXT NOT NULL, preferred_time TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'Open' CHECK(status IN ('Open','Cancelled','Assigned','Completed')), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(student_id) REFERENCES students(student_id));

INSERT OR ignore INTO students (student_name, student_email) VALUES ('Maria Lopez', 'maria.lopez@example.com');
-- Lab 7: guarded so rerunning this script does not duplicate the seed request
-- (tutoring_requests has no unique key, so the Lab 6 INSERT OR IGNORE added a copy on every run).
INSERT INTO tutoring_requests (student_id, course, topic, preferred_date, preferred_time)
SELECT 1, 'IS 6465', 'PHP arrays and forms', '2026-11-03', '14:00'
WHERE NOT EXISTS (SELECT 1 FROM tutoring_requests WHERE student_id = 1 AND topic = 'PHP arrays and forms');
