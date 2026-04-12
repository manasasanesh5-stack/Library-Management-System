# SecureLibrary – Secure Library Management System

SecureLibrary is a secure PHP and MySQL-based Library Management System developed as part of a Secure Web Development project. The application supports two user roles—**Administrator** and **Member**—and provides secure functionality for authentication, book management, member management, borrowing workflows, profile management, and audit logging.

The project was designed to demonstrate the implementation of core secure web development practices in a realistic CRUD-based application. Particular emphasis was placed on authentication security, access control, input validation, secure session handling, CSRF protection, password hashing, audit logging, and static and functional security testing.

---

## Project Overview

The system allows:

- **Administrators** to manage books, manage members, issue and return books, view security audit logs, and change their own password.
- **Members** to register, log in, browse available books, borrow books, view borrowing history, and manage their profile.

This project was built to meet the requirements of a secure enterprise-style web application with:
- CRUD functionality
- database integration
- at least two user roles with different privileges
- documented security improvements
- functional testing and SAST

---

## Technology Stack

- **Backend:** PHP 8+
- **Database:** MySQL / phpMyAdmin
- **Frontend:** HTML5, CSS3, JavaScript
- **Server:** Apache (XAMPP recommended for local setup)
- **Database Access:** PDO with prepared statements
- **Security Testing:** Manual functional security testing + Semgrep SAST

---

## Key Features

### Administrator Features
- Secure admin login
- Dashboard with statistics and recent activity
- Book CRUD:
  - add books
  - edit books
  - delete books
  - duplicate ISBN prevention
- Member management:
  - view members
  - activate/deactivate accounts
  - delete members
- Borrowing management:
  - issue books
  - return books
  - view active, overdue, and returned records
- Audit log viewer
- Admin profile and password change

### Member Features
- Self-registration
- Secure login/logout
- Browse and search books
- Filter books by genre and availability
- Borrow available books
- View personal borrowing history
- Update profile name
- Change password

---

## Security Objectives and Improvements

The application was built with security as a primary objective. The following controls were implemented:

### Authentication Security
- Passwords are hashed using **bcrypt**
- Login uses **prepared statements**
- Generic login error messages help avoid account enumeration
- Brute-force login protection is implemented using a `login_attempts` table and temporary lockout logic
- Deactivated users cannot log in

### Authorization and Access Control
- Role-based access control distinguishes **admin** and **member**
- Protected routes require authenticated access
- Admin-only pages are blocked from non-admin users
- Member-only pages are restricted appropriately
- Unauthorized access attempts are logged

### Session Security
- Secure session initialization
- HTTP-only session cookies
- SameSite cookie policy
- Session ID regeneration to reduce fixation risk
- Idle session timeout handling

### CSRF Protection
- CSRF tokens are generated server-side
- Sensitive POST requests verify valid CSRF tokens
- Invalid token submissions are rejected

### Input Validation and Output Encoding
- Server-side validation for:
  - email format
  - password strength
  - required fields
  - ISBN format
  - valid form data
- Output is escaped using HTML encoding helpers to reduce XSS risk

### Database Security
- PDO prepared statements are used throughout
- Duplicate ISBN and duplicate user email checks are enforced
- Borrowing operations use transactions for consistency
- Row locking is used in borrowing workflows to avoid race conditions

### Logging and Auditing
- Security-related actions are written to an `audit_log`
- Login failures, account lock events, password changes, and access violations are recorded
- IP addresses are logged for traceability

### Server and Configuration Hardening
- `.htaccess` includes:
  - security headers
  - access restrictions
  - hidden file blocking
  - custom error pages
  - directory listing disabled
- Direct access to sensitive files is restricted

---

## Project Structure

```text
library_system/
├── .htaccess
├── index.php
├── database.sql
│
├── includes/
│   ├── config.php
│   ├── header.php
│   └── footer.php
│
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── admin/
│   ├── dashboard.php
│   ├── books.php
│   ├── members.php
│   ├── borrowings.php
│   ├── audit.php
│   └── profile.php
│
├── member/
│   ├── dashboard.php
│   ├── browse.php
│   ├── my_borrowings.php
│   └── profile.php
│
└── public/
    ├── css/
    │   └── style.css
    ├── js/
    │   └── main.js
    ├── 403.php
    └── 404.php
````

---

## Folder and File Purpose

### Root Files

* **index.php** – redirects users to the correct dashboard based on session role
* **database.sql** – database schema and seed data
* **.htaccess** – Apache security configuration, headers, access restrictions, and error pages

### `includes/`

* **config.php** – database connection, session setup, CSRF helpers, audit logging, access guards, validation, and rate limiting
* **header.php** – shared navigation bar
* **footer.php** – shared footer and JavaScript include

### `auth/`

* **login.php** – secure login with CSRF verification and brute-force protection
* **register.php** – member registration with validation and password complexity rules
* **logout.php** – secure logout using POST and CSRF verification

### `admin/`

* **dashboard.php** – admin overview and recent activity
* **books.php** – book management
* **members.php** – member administration
* **borrowings.php** – issue and return workflow
* **audit.php** – security audit log viewer
* **profile.php** – admin password management

### `member/`

* **dashboard.php** – member summary dashboard
* **browse.php** – browse, search, filter, and borrow books
* **my_borrowings.php** – borrowing history
* **profile.php** – name and password management

### `public/`

* **style.css** – responsive styling
* **main.js** – client-side validation and password strength feedback
* **403.php / 404.php** – custom error pages

---

## Database Design

The project uses the following core tables:

* `users`
* `books`
* `borrowings`
* `audit_log`
* `login_attempts`

### Main Relationships

* A user can have many borrowing records
* A book can appear in many borrowing records
* Audit logs can optionally reference a user
* Login attempts store email, IP address, success status, and timestamp

---

## Setup and Installation

### 1. Clone or Download the Project

Place the project inside your Apache web root, for example:

```text
xampp/htdocs/library_system
```

### 2. Start Apache and MySQL

Use XAMPP Control Panel to start:

* **Apache**
* **MySQL**

### 3. Create the Database

Open **phpMyAdmin** and import:

```text
database.sql
```

This will:

* create the database `library_db`
* create all required tables
* insert a default admin user
* insert sample books

### 4. Configure Database Access

Check the database settings in:

```php
includes/config.php
```

Default values are typically:

* host: `localhost`
* port: `3306`
* database: `library_db`
* username: `root`
* password: empty (default XAMPP)

Update them if your local environment is different.

### 5. Open the Application

Visit the project in your browser, for example:

```text
http://localhost/library_system/
```

or, if configured with a local virtual host:

```text
http://library.local/
```

---

## Default Admin Account

The seed data provides a default administrator:

* **Email:** `admin@library.com`
* **Password:** `Admin@1234`

> Change the default admin password immediately after first login.

---

## Usage Guide

### Administrator Workflow

1. Log in using the admin account
2. Open the dashboard
3. Add, edit, or delete books
4. Manage member accounts
5. Issue or return books
6. View the audit log
7. Change admin password from profile page

### Member Workflow

1. Register a new member account
2. Log in
3. Browse or search books
4. Borrow an available book
5. View borrowing history
6. Update name or password in profile page

---

## Security Testing

The project includes both **manual functional security testing** and **Static Application Security Testing (SAST)**.

### Manual Security Tests Performed

#### 1. Brute-Force Protection Test

* Repeatedly entered incorrect login credentials
* After multiple failed attempts, the application displayed a temporary lockout message
* Result: **Passed**

#### 2. Role-Based Access Control Test

* Logged in as a member
* Attempted to open admin-only URLs such as `/admin/dashboard.php`
* Application returned **403 Forbidden**
* Result: **Passed**

#### 3. CSRF Protection Test

* Tampered with the hidden CSRF token in a POST form
* Submitted the form with an invalid token
* Application rejected the request with an error
* Result: **Passed**

---

## Static Application Security Testing (SAST)

SAST was conducted using **Semgrep**.

### Tool Used

* **Semgrep**

### Command

```bash
semgrep --config=p/security-audit .
```

### Result

* Scan completed successfully
* The scan reported **0 findings** for the scanned project files

This provided additional assurance that the source code did not contain obvious insecure coding patterns detectable by the selected static analysis ruleset.

---

## Additional Validation and Defensive Controls

The application also includes:

* password strength validation on both client and server side
* duplicate account prevention
* duplicate ISBN prevention
* form input sanitization
* output escaping
* custom 403 and 404 pages
* responsive UI for desktop and mobile devices

---

## Security Notes

This project is designed for **educational use** and secure development demonstration. While strong controls were implemented, security is an ongoing process. Additional future improvements could include:

* password reset via email
* multi-factor authentication
* stronger centralized overdue-status refresh logic
* automated unit and integration tests
* stricter Content Security Policy without inline allowances
* environment-based secret management

---

## How to Run Security Scan Again

To rerun SAST locally:

```bash
semgrep --config=p/security-audit .
```

To save results to a file:

```bash
semgrep --config=p/security-audit . > semgrep_results.txt
```

---

## Project Contributions

This project includes:

* secure authentication and registration logic
* secure session and CSRF handling
* role-based authorization
* book, member, and borrowing management modules
* audit logging and security monitoring
* functional security testing
* static security analysis using Semgrep

---

## Important Notes for Assessors / Reviewers

* The project includes a default seeded admin account for demonstration
* The application is intended to run locally with Apache and MySQL
* Security features are implemented in code and supported by testing evidence
* README, report, video demo, and GitHub commit history together form the complete project submission package

---

## License / Academic Use

This project was created for academic assessment purposes. It is intended for demonstration and educational evaluation.

---
