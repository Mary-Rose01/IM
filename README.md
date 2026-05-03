# BorrowEase — CSIT226 Final Output Increment 1
### Ligaray & Pacina — BSCS 2 F1

---

## 📋 Project Overview

BorrowEase is a PHP/MySQL web application for managing item borrowing between students and organizations in a university setting. This implements the ERD designed for the CSIT226 Information Management 1 project.

---

## 🗄️ Database Setup

1. Open **phpMyAdmin** or your MySQL client
2. Create a new database called: `db_ligaraypacina`
3. Import the file: `database.sql`
4. The database will create all tables and a default admin account

**Default Admin Login:**
- Username: `admin`
- Password: `password`

---

## 📁 File Structure

```
project/
├── index.php               → Redirects to login or dashboard
├── login.php               → Login page
├── logout.php              → Session destroy + redirect
├── register.php            → User registration (Student / Organization)
├── dashboard.php           → Main dashboard with stats
│
├── users.php               → Admin CRUD: Users
├── students.php            → Admin CRUD: Students
├── organizations.php       → Admin CRUD: Organizations
├── items.php               → CRUD: Items (admin + owner)
├── bookings.php            → Admin CRUD: Bookings
├── borrow_requests.php     → CRUD: Borrow Requests
├── transactions.php        → Admin CRUD: Borrow Transactions
│
├── connect.php             → DB connection + session + helper functions
├── database.sql            → Full database schema + seed data
│
├── css/
│   └── style.css           → Main stylesheet
│
└── includes/
    ├── header.php          → HTML <head> + open <body>
    ├── footer.php          → Closing scripts + </body></html>
    └── sidebar.php         → Navigation sidebar
```

---

## 🗂️ Database Schema (aligned to ERD)

| Table                  | Description                              |
|------------------------|------------------------------------------|
| `tbluser`              | Base user entity (all roles)             |
| `tblstudent`           | Student subtype (linked to tbluser)      |
| `tblorganization`      | Organization subtype (linked to tbluser) |
| `tblitem`              | Borrowable items                         |
| `tblavailabilityslot`  | Time slots posted by organizations       |
| `tblbooking`           | Student bookings for slots               |
| `tblborrowrequest`     | Borrow requests linked to bookings       |
| `tblborrowtransaction` | Actual borrow transactions               |
| `tblcanceltransaction` | Cancellation records                     |

---

## ✅ CRUD Coverage (Increment 1 Checklist)

| Entity         | Create | Read | Update | Delete |
|----------------|--------|------|--------|--------|
| Users          | ✅     | ✅   | ✅     | ✅     |
| Students       | ✅     | ✅   | ✅     | ✅     |
| Organizations  | ✅     | ✅   | ✅     | ✅     |
| Items          | ✅     | ✅   | ✅     | ✅     |
| Bookings       | ✅     | ✅   | ✅     | ✅     |
| Borrow Requests| ✅     | ✅   | ✅     | ✅     |
| Transactions   | ✅     | ✅   | ✅     | ✅     |

---

## 🚀 How to Run

1. Place the entire `project/` folder inside your `htdocs/` (XAMPP) or `www/` (WAMP) directory
2. Start Apache and MySQL in XAMPP/WAMP
3. Import `database.sql` via phpMyAdmin
4. Visit: `http://localhost/project/`
5. Log in with `admin` / `password`

---

## 👥 Group Members
- Mary Rose T. Pacina
- Ericka Fatima Reign R. Ligaray

**Course:** CSIT226 — Information Management 1  
**Section:** BSCS 2 F1  
**Due:** May 11, 2026
