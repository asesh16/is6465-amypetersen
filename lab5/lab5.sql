
--Part 2
-- INSERT OR ignore INTO students 
--     (student_name, student_email)
-- VALUES 
--     ('Jordan Lee', 'jordan.lee@example.edu');

-- INSERT OR ignore INTO students 
--     (student_name, student_email)
-- VALUES 
--     ('Avery Patel', 'avery.patel@example.edu');

-- INSERT OR ignore INTO tutoring_requests
--   (student_id, course, topic, preferred_date, preferred_time)
-- VALUES
--   (2, 'IS 6465', 'Forms', '2026-11-03', '14:00');

-- INSERT OR ignore INTO tutoring_requests
--   (student_id, course, topic, preferred_date, preferred_time)
-- VALUES
--   (2, 'IS 6465', 'SQL SELECT', '2026-11-17', '12:00');

-- INSERT OR ignore INTO tutoring_requests
--   (student_id, course, topic, preferred_date, preferred_time)
-- VALUES
--   (3, 'IS 6465', 'SQLite CRUD', '2026-11-05', '15:00');

--   select * from students;
--   select * from tutoring_requests;


-- Part 3
-- SELECT * from students;
-- select * from tutoring_requests;

-- select * from tutoring_requests 
-- where status = "Open";

-- select * from tutoring_requests 
-- where request_id = 1;

-- SELECT
--   r.request_id,
--   s.student_name,
--   s.student_email,
--   r.course, r.topic,
--   r.preferred_date, r.preferred_time, r.status
-- FROM tutoring_requests AS r
-- JOIN students AS s ON r.student_id = s.student_id
-- ORDER BY r.preferred_date;


-- Part 4
-- select * from tutoring_requests; 

-- UPDATE tutoring_requests
-- SET topic = 'PHP forms'
-- WHERE request_id = 12;

-- UPDATE tutoring_requests
-- SET status = 'Cancelled'
-- WHERE request_id = 13;

-- select * from tutoring_requests; 

--Part 5

INSERT OR ignore INTO tutoring_requests
  (student_id, course, topic, preferred_date, preferred_time)
VALUES
  (3, 'IS 6465', 'Delete Practice', '2026-11-05', '15:00');

select * from tutoring_requests;

DELETE from tutoring_requests
where topic = "Delete Practice";

delete from tutoring_requests;

select * from tutoring_requests;


