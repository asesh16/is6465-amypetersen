

Part 1
1. See Part 1 Valid Request Screenshot
2. The browser sends a POST request to process-request.php (the form’s action). PHP reads the values from $_POST and checks them against the business rules. If everything passes, it prints a “Request received” page showing the course, request details, topics, and “Status: Pending review.” If any rule fails, it returns HTTP 400 and a “Request could not be submitted” page listing the errors. Nothing is saved yet because there is no database

Part 2
1. header.php: Provides the top of every HTML document: doctype, <html>, the <head> (charset, viewport, Bootstrap CSS link, css/styles.css link, title), and the opening <body>.
2. nav.php: Provides the shared Bootstrap navbar with the “Peer Tutoring Hub” brand link and the “Requests” and “Request a Session” links.
3. footer.php: Provides the footer text, the Bootstrap JS bundle, and the closing </body></html> tags.
4. Why require_once: Every page needs these parts to be complete, so require is right because a missing file should stop the page instead of rendering a broken layout. The _once guarantees each file is included only one time, so I never get duplicate doctypes, navbars, or footers. A change to the nav or footer is made in one place and every page picks it up.
5. Shared CSS: It is linked in includes/header.php with <link rel="stylesheet" href="css/styles.css">, right after the Bootstrap CDN link. Individual pages don’t link CSS themselves.

Part 3
1. The reason control is a textarea where the student explains what they need help with. PHP receives it as one string, and the rule is that it must be 10 to 500 characters.
2. The topics[] control is a set of checkboxes that lets the student pick one, several, or no topics. The brackets in the name tell PHP to collect every checked box, so PHP receives an array of strings. If nothing is checked, the key isn’t sent at all, which is why the code uses ?? [].
3. The preferredtime control is a group of radio buttons (Morning, Afternoon, Evening) that share one name so only one can be chosen. PHP receives one string, which is the value of the selected button.
4. The preferreddate control is a date input with a calendar picker. PHP receives it as one string in YYYY-MM-DD format, not as a real date object, so PHP still has to parse and validate it.
5. The form_name control is a hidden field with the value tutoring_request that identifies which form workflow the submission belongs to. PHP receives one string. It is invisible but not trusted, since anyone can edit it in the browser, so PHP checks that it equals tutoring_request.

Part 4
Test 1: Valid request
I entered IS 6465 as the course, 11/11/2026 as the preferred date, “Everything” as the reason, Exam review as the topic, and Afternoon as the preferred time. The request was accepted. The application showed a “Request received” page with Course: IS 6465, Request details: Everything, Topics: Exam review, and Status: Pending review. The business rules being checked were that the course is on the approved list, the time is Morning, Afternoon, or Evening, the reason is 10 to 500 characters, the date is valid and not in the past, at least one approved topic is selected, and the hidden form name is correct. “Everything” is exactly 10 characters, so it sits right at the minimum and passes.

Test 2: Missing or invalid value
I entered the same values (IS 6465, 11/11/2026, “Everything”, Afternoon) but left all of the topic checkboxes unchecked. The request was rejected. The application showed “Request could not be submitted” with the message “Select at least one valid topic.” The business rule being checked is that at least one approved topic must be selected, and every submitted topic has to be on the allowed list (Exam review, Practice problems, Study plan). I chose topics because the other fields use the HTML required attribute, so the browser would have stopped me before PHP saw anything. Checkboxes have no required attribute, so this one made it to the server.

Test 3: Past date
I entered IS 6465 as the course, 10/30/2026 as the preferred date (before today, 11/1/2026), “Everything” as the reason, Exam review as the topic, and Afternoon as the preferred time. The request was rejected. The application showed “Request could not be submitted” with the message “Preferred date cannot be in the past.” The business rule being checked is that the preferred date must be a valid date and cannot be earlier than today. PHP compares the submitted date to today’s date, so today itself would still be allowed.

Part 5
Testing results
I opened tests/validation-tests.php and all 10 test cases showed PASS: Valid time, Missing time, Altered value, Multiple valid topics, Missing topics, Altered topic, Minimum length, Maximum length, Too short, and Too long.
I opened requests.php, which lists three sample requests (IDs 12, 13, and 14) with their course, preferred date, and status, and a View button for each. I opened the details page for request 12, which shows the course (IS 6465), request details, preferred date (2026-10-22), preferred time (Morning), topics (Exam review, Study plan), and status (Pending), along with Edit and Cancel request buttons.


CRUD action	| Page
1. Create: request-form.php, which submits to process-request.php
2. Read one request: request-details.php
3. Read all requests: requests.php
4. Update: edit-request.php
5. Delete: delete-request.php

1. The query parameter is request_id. For request 12 the URL is request-details.php?request_id=12.

2. Viewing is read-only and doesn’t change anything on the server, so it is safe to refresh, bookmark, or share as a link. The ID in the URL tells PHP which record to show. PHP still treats the ID as untrusted input, so the page validates that it is a positive integer before looking anything up.

3. Updates and deletes change application state, and GET is supposed to be safe and repeatable. If a delete were a GET link, a browser prefetch, a crawler, a page refresh, or a link someone tricked me into clicking could delete a record by accident. POST keeps the data out of the URL, makes the action a deliberate form submission, and lets the server check the request method and add protections like CSRF tokens later. That is why the delete page shows a confirmation form that uses POST instead of deleting as soon as the page loads.
