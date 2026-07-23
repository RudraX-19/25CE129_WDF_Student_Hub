# StudentHub — Low-Fidelity Wireframe Notes
**Practical 1 | WDF Lab**

## Page Wireframes

---

### 1. register.html — Registration Page
```
+============================================================+
|  HEADER: StudentHub Portal                                 |
|  Tagline: Join the centralized platform...                 |
+============================================================+
|
|   SECTION: Student Registration
|   +----------------------------------------------------+
|   |  Full Name         :  [__________________________] |
|   |  Student Email     :  [__________________________] |
|   |  Student ID Number :  [__________________________] |
|   |  Major/Program     :  [-- Select Major --------v] |
|   |  Enrollment Status :  (o) Full-time  ( ) Part-time |
|   |  Home Address      :  [________________________]   |
|   |                       [________________________]   |
|   |  Create Password   :  [__________________________] |
|   |  Confirm Password  :  [__________________________] |
|   |                                                    |
|   |  [x] I agree to the Student Terms and Conditions  |
|   |                                                    |
|   |               [ Create Account ]                  |
|   +----------------------------------------------------+
|   Already have an account? Log in here
|
+============================================================+
|  FOOTER: © 2026 StudentHub                               |
+============================================================+
```

---

### 2. login.html — Login Page
```
+============================================================+
|  HEADER: StudentHub Portal                                 |
|  Tagline: Welcome to the centralized platform...          |
+============================================================+
|
|   SECTION: Login
|   +----------------------------------------------------+
|   |  Username :  [__________________________________]  |
|   |  Password :  [__________________________________]  |
|   |                                                    |
|   |                   [ Log In ]                      |
|   +----------------------------------------------------+
|   Dont have an account? Register here
|
+============================================================+
|  FOOTER: © 2026 StudentHub                               |
+============================================================+
```

---

### 3. index.html — Home Page (post-login)
```
+============================================================+
|  HEADER: StudentHub Portal              [Welcome, Student!]|
|                                                 [Logout]   |
+============================================================+
|  NAV: Home | Dashboard | Courses | Timetable | Grades |   |
|         Events | Library | Announcements | Profile         |
+============================================================+
|
|   H2: Welcome to StudentHub
|   Intro paragraph...
|
|   SECTION: Quick Links
|   - Go to Dashboard
|   - View My Courses
|
+============================================================+
|  FOOTER: © 2026 StudentHub                               |
+============================================================+
```

---

### 4. dashboard.html — Dashboard
```
+============================================================+
|  HEADER: StudentHub Portal                      [Logout]   |
+============================================================+
|  NAV: Home | Dashboard | Courses | Timetable | Grades...  |
+============================================================+
|
|   H2: Overview Dashboard
|
|   +------------------+   +-------------------------------+
|   | Academic Standing |   | Upcoming Deadlines           |
|   |  GPA: 3.8/4.0    |   | - Web Dev Project — 2 days  |
|   |  Major: Comp Sci  |   | - Physics Lab — 5 days      |
|   +------------------+   +-------------------------------+
|
|   +--------------------------------------------------+
|   | Today's Schedule                                  |
|   |  09:00-10:30  Web Development  (Room 205)        |
|   |  11:00-12:30  Data Structures  (Room 101)        |
|   +--------------------------------------------------+
|
+============================================================+
|  FOOTER: © 2026 StudentHub                               |
+============================================================+
```

---

### 5. courses.html — My Courses
```
+============================================================+
|  HEADER + NAV (same as dashboard)                          |
+============================================================+
|
|   H2: My Courses
|
|   TABLE:
|   | Code  | Course Name          | Credits | Instructor   |
|   |-------|---------------------|---------|--------------|
|   | CS201 | Web Development      |    4    | Prof. Sharma |
|   | CS202 | Data Structures      |    4    | Prof. Mehta  |
|   | PH101 | Physics              |    3    | Prof. Gupta  |
|   | MTH201| Mathematics          |    3    | Prof. Joshi  |
|
|   [View Course Details links per row]
|
+============================================================+
|  FOOTER                                                   |
+============================================================+
```

---

### 6. timetable.html — Timetable
```
+============================================================+
|  HEADER + NAV                                              |
+============================================================+
|
|   H2: Weekly Timetable
|
|   TABLE:
|   | Time     | Mon  | Tue  | Wed  | Thu  | Fri  |
|   |----------|------|------|------|------|------|
|   | 09:00    | WDev |  —   |WDev  |  —   |WDev  |
|   | 11:00    |  DS  | DS   |  —   | DS   |  —   |
|   | 14:00    | Math |  —   |Math  |  —   |Math  |
|
+============================================================+
|  FOOTER                                                   |
+============================================================+
```

---

### 7. grades.html — Grades & Transcripts
```
+============================================================+
|  HEADER + NAV                                              |
+============================================================+
|
|   H2: Grades & Transcripts
|   Overall GPA: 3.8 / 4.0
|
|   TABLE:
|   | Subject          | Midterm | Final | Grade |
|   |-----------------|---------|-------|-------|
|   | Web Development  |  85     |  90   |   A   |
|   | Data Structures  |  78     |  82   |   B+  |
|   | Physics          |  92     |  88   |   A   |
|
+============================================================+
|  FOOTER                                                   |
+============================================================+
```

---

### 8–12. events, library, announcements, course-details, profile
All share the same HEADER + NAV + FOOTER shell, with a main content area 
featuring relevant sections (lists, tables, or forms).

---
*See README.md for full text wireframe overview.*
