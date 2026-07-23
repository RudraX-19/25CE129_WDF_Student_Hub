# StudentHub Portal

> **Course:** Web Development Fundamentals (ITUE203)
> **Practical:** 1 — Project Initiation, Requirement Analysis, Sitemap, Wireframe & GitHub Setup
> **Student:** *(Your Name / Roll No.)*

---

## 1. Problem Scope

Students often struggle to find academic and extracurricular resources in one place. Information is typically scattered across multiple disconnected systems, leading to missed deadlines, poor communication between faculty and students, and low engagement with campus activities.

The **StudentHub portal** solves this fragmented experience by providing a single, centralized platform where students can seamlessly access their timetables, grades, club activities, library resources, and communicate with peers and faculty — all from one place.

---

## 2. User Roles

| Role | Responsibilities |
|---|---|
| **Student** | View grades, check timetables, register for events, access course materials |
| **Faculty** | Post announcements, upload course materials, manage class lists, publish grades |
| **Club Coordinator** | Create/manage extracurricular events, oversee club memberships |
| **Administrator** | Manage system-wide settings, user accounts, roles, and permissions |

---

## 3. Key Modules

- **Authentication** — Registration, Login, and Logout flow
- **Academic Dashboard** — Overview of GPA, enrolled courses, and upcoming deadlines
- **Course Management** — Repository for syllabus, assignments, and lecture materials
- **Schedule & Timetable** — Weekly calendar view of classes and exams
- **Events & Clubs** — Noticeboard for club activities, workshops, and campus sports
- **Library / Resources** — Digital books, journals, and useful academic links
- **Announcements** — Campus-wide or course-specific notices
- **Profile & Settings** — User profile, password changes, and preferences

---

## 4. Navigation Flow

```
Register ──► Login ──► Home (index.html)
                              │
          ┌───────────────────┼───────────────────────┐
          ▼                   ▼                        ▼
      Academic           Campus Life               Account
   ┌──────────────┐    ┌──────────────┐        ┌──────────────┐
   │ My Courses   │    │ Events/Clubs │        │   Profile    │
   │ Crs. Details │    │Announcements │        └──────────────┘
   │ Grades       │    │  Library     │
   │ Timetable    │    └──────────────┘
   └──────────────┘
                              │
                           Logout ──► Login
```

---

## 5. Minimum 10 Pages

| # | Page | File | Description |
|---|------|------|-------------|
| 1 | Registration | `register.html` | New student sign-up with full form |
| 2 | Login | `login.html` | Entry point for all users |
| 3 | Home | `index.html` | Landing page after login with quick links |
| 4 | Dashboard | `dashboard.html` | GPA, deadlines, today's schedule overview |
| 5 | My Courses | `courses.html` | List of currently enrolled courses |
| 6 | Course Details | `course-details.html` | Materials, syllabus for a specific course |
| 7 | Timetable | `timetable.html` | Weekly/daily class schedule |
| 8 | Grades & Transcripts | `grades.html` | Detailed academic performance breakdown |
| 9 | Events & Clubs | `events.html` | Campus activities and event registrations |
| 10 | Library / Resources | `library.html` | Digital resources and academic links |
| 11 | Announcements | `announcements.html` | Campus and course-specific notices |
| 12 | Profile & Settings | `profile.html` | Personal info and account preferences |

---

## 6. Sitemap

```
StudentHub Portal
│
├── [PUBLIC]
│   ├── register.html         (Student Registration)
│   └── login.html            (Login)
│
└── [PRIVATE — after login]
    ├── index.html            (Home / Landing)
    ├── dashboard.html        (Dashboard Overview)
    │
    ├── [Academic]
    │   ├── courses.html       (My Courses)
    │   ├── course-details.html(Course Details)
    │   ├── timetable.html     (Timetable)
    │   └── grades.html        (Grades & Transcripts)
    │
    ├── [Campus Life]
    │   ├── events.html        (Events & Clubs)
    │   ├── announcements.html (Announcements)
    │   └── library.html       (Library)
    │
    └── [Account]
        └── profile.html       (Profile & Settings)
```

---

## 7. Low-Fidelity Wireframe (Text-Based)

Full wireframe screenshots are stored in `docs/wireframes/`.
Below is the structure summary:

### register.html / login.html
```
+---------------------------------------------+
|       HEADER: StudentHub Portal              |
+---------------------------------------------+
|      Form Title (Register / Login)           |
|  +---------------------------------------+   |
|  | Label     :  [Input Field          ] |   |
|  | Label     :  [Input Field          ] |   |
|  | ...                                  |   |
|  |         [ Submit Button ]            |   |
|  +---------------------------------------+   |
|      Link to Login / Register                |
+---------------------------------------------+
|       FOOTER: © 2026 StudentHub             |
+---------------------------------------------+
```

### index.html / dashboard.html / all inner pages
```
+---------------------------------------------+
|  HEADER: StudentHub Portal    [Logout]        |
+---------------------------------------------+
|  NAV: Home | Dashboard | Courses | Timetable |
|        Grades | Events | Library | Profile   |
+---------------------------------------------+
|                                             |
|   PAGE TITLE                                |
|                                             |
|   [ Section 1 ]         [ Section 2 ]      |
|   Content / Cards /     Content / Table    |
|   Lists / Tables        / Form             |
|                                             |
+---------------------------------------------+
|       FOOTER: © 2026 StudentHub             |
+---------------------------------------------+
```

---

## 8. Project Folder Structure

```
StudentHub/
├── README.md                  ← This file — Project documentation
├── student_hub/               ← All HTML pages
│   ├── register.html          ← Student registration form
│   ├── login.html             ← Login page
│   ├── index.html             ← Home (post-login landing page)
│   ├── dashboard.html         ← Dashboard overview
│   ├── courses.html           ← My Courses
│   ├── course-details.html    ← Individual course page
│   ├── timetable.html         ← Weekly timetable
│   ├── grades.html            ← Grades & Transcripts
│   ├── events.html            ← Events & Clubs
│   ├── library.html           ← Library & Resources
│   ├── announcements.html     ← Announcements
│   └── profile.html           ← Profile & Settings
├── assets/
│   ├── css/                   ← Stylesheets
│   ├── js/                    ← JavaScript files
│   └── images/                ← Image assets
└── docs/
    └── wireframes/            ← Wireframe screenshots & diagrams
```

---

## 9. Key Questions — Answers

### Q1. What is a URL and its parts?

A **URL (Uniform Resource Locator)** is the web address used to locate a resource on the internet.

**Structure:**
```
https://www.example.com:443/path/to/page?query=value#section
  │         │          │        │             │          │
  │      Domain      Port     Path          Query    Fragment
Scheme/
Protocol
```

| Part | Example | Description |
|------|---------|-------------|
| Scheme | `https://` | Protocol used (http, https, ftp) |
| Domain | `www.example.com` | Host/server name |
| Port | `:443` | Network port (optional; 80=HTTP, 443=HTTPS) |
| Path | `/path/to/page` | Location of the resource on the server |
| Query | `?query=value` | Parameters passed to the server |
| Fragment | `#section` | Anchor within the page (client-side only) |

---

### Q2. How does an HTML file get processed in a web browser?

1. **User enters URL** → Browser sends an HTTP GET request to the server
2. **Server responds** with the HTML file content
3. **HTML Parsing** → Browser reads HTML top-to-bottom and builds the **DOM (Document Object Model)**
4. **CSS Parsing** → Browser fetches linked CSS and builds the **CSSOM (CSS Object Model)**
5. **Render Tree** → DOM + CSSOM are combined into a Render Tree
6. **Layout** → Browser calculates size and position of every element
7. **Paint** → Pixels are drawn on screen
8. **JavaScript** → `<script>` tags are fetched, parsed, and executed (can modify DOM/CSSOM)
9. **Final Display** → User sees the rendered page

---

### Q3. How is page navigation flow managed among all HTML pages?

In this **pure HTML project**, navigation is managed using:

- **`<a href="...">` anchor tags** — clicking a link loads a new HTML file
- **`<form action="...">` submit** — form submission navigates to the action URL
- **Relative paths** — all pages are in the same `student_hub/` folder, so links use filenames directly (e.g., `href="dashboard.html"`)

**Flow:**
```
register.html  →[form submit]→  login.html
login.html     →[form submit]→  index.html
index.html     →[nav links]→    dashboard.html / courses.html / etc.
Any page       →[Logout link]→  login.html
```

No JavaScript routing or server-side session management is used — navigation is entirely through HTML hyperlinks.

---

### Q4. How will GitHub commits be maintained after each practical?

Best practices followed:

1. **One commit per practical** — commit all files related to that practical together
2. **Meaningful commit messages** using the format:
   ```
   Practical N: <short description>
   ```
   Example: `Practical 1: Sitemap, wireframes, folder structure, README`
3. **Staged commits** — only add relevant files (`git add <files>`)
4. **Branch strategy** (recommended):
   - `main` — stable, submission-ready code
   - `practical-N` — work-in-progress branch per practical
5. **Push after every session** to avoid data loss

**Commit Command Example:**
```bash
git add .
git commit -m "Practical 1: Project setup, sitemap, wireframe, README"
git push origin main
```

---

## 10. GitHub Repository

- **Repository Name:** `StudentHub`
- **Visibility:** Public
- **Branch:** `main`

> Link: *(Add your GitHub repository URL here)*

---

## 11. Tools & Technologies Used

| Tool | Purpose |
|------|---------|
| VS Code | Code editor |
| HTML5 | Page structure and content |
| Git | Version control |
| GitHub | Remote repository hosting |
| Draw.io / Figma | Wireframe and sitemap design |

---

*© 2026 StudentHub — WDF Practical Submission*
