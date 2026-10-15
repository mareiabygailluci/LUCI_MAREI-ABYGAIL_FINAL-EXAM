# LOST2FOUND — Barangay Lost & Found Management System

A web-based capstone system for **Barangay Libis** that connects residents who lost items with
those who found them, featuring an **automated weighted item-matching engine** and a full
claim verification workflow.

**Stack:** PHP 8 (PDO) · MySQL · HTML/CSS/JS (no frameworks, no build step)

---

## Run it on localhost after cloning (FINAL EXAM setup)

> Clone this repo anywhere inside `htdocs` — the folder name does not matter.
> Example below uses `Lost2Found`, but `LUCI_MAREI-ABYGAIL_FINAL-EXAM` works the same.

1. Clone the repo into XAMPP's web folder:
   ```bash
   git clone https://github.com/mareiabygailluci/LUCI_MAREI-ABYGAIL_FINAL-EXAM.git "C:\xampp\htdocs\Lost2Found"
   ```
   (If your XAMPP is at `D:\xamp`, clone to `D:\xamp\htdocs\Lost2Found` instead.)
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **http://localhost/phpmyadmin** → **Import** → choose
   `database/lost2found.sql` → **Go**.
   The file creates the `lost2found` database, all tables, categories,
   locations, **and the demo accounts below** (safe to re-import anytime).
4. Log in (no registration needed):
   - Landing page: **http://localhost/Lost2Found/**
   - Login page: **http://localhost/Lost2Found/auth/login.php**

## Test accounts (gagana agad pagkatapos mag-import ng SQL)

| Role     | Email                              | Password      | Notes                          |
|----------|------------------------------------|---------------|--------------------------------|
| Admin    | `admin@lost2found.ph`              | `Admin1234`   | Barangay admin, full access    |
| Resident | `mareiabygailluci.dost@gmail.com`  | `Jeremiah29:11` | Active resident, ready to use |

> Change these passwords after logging in (My Profile → Change password).

The MySQL credentials live in `config/db.php` (XAMPP defaults: `root`, empty password).

## Quick Start (fresh database / new admin)

1. Copy the project folder into `C:\xampp\htdocs\lost2found`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **http://localhost/phpmyadmin**, create/select nothing manually — just go to
   **Import** and upload `database/lost2found.sql` (it creates the `lost2found` database,
   all tables, categories, and locations).
4. Open **http://localhost/lost2found/auth/register.php** and create your account —
   **the first account registered automatically becomes the Barangay Admin**.
   Everyone after that registers as a resident.
5. Log in at **http://localhost/lost2found/auth/login.php**.

The MySQL credentials live in `config/db.php` (XAMPP defaults: `root`, empty password).

---

## Features

### 👤 Resident
- Register / login (account activation controlled by admin)
- **Lost item reporting** — full CRUD (create, view, edit, delete own reports)
- **Found item reporting** — full CRUD
- **Search & filter** — by keyword, category, color, location, type
- **Possible matches** — side-by-side comparison with match score
- **Claim requests** — file with reason + optional proof photo, track status
- **Notifications** — bell menu + full page
- **Profile management** — update info, change password

### 🛡️ Barangay Admin
- Dashboard with stat cards and charts
- Manage lost/found reports: approve → matching engine runs, reject, delete, mark returned
- **Possible matches review** — confirm or dismiss (statuses roll back automatically)
- **Claim verification** — approve/reject with reviewer stamps, then *Mark Returned* which
  closes the related lost reports
- **User management** — create, edit, activate/deactivate, delete
- **Category & location management** — full CRUD with usage counts
- **Statistics** — charts by category/location/status, key figures

### 🔥 Automated Matching Engine (`config/config.php`)
Weighted scoring on every approved pair:

| Criteria | Weight | Rule                                        |
|----------|--------|---------------------------------------------|
| Category | 20%    | exact match                                 |
| Color    | 20%    | exact (20) or partial keyword overlap (10)  |
| Location | 25%    | exact match                                 |
| Date     | 20%    | found ≥ lost; tighter gap scores higher     |
| Brand    | 15%    | case-insensitive match                      |

Pairs scoring **≥ 60%** create a match row and notify both owners.
Matching only runs on **approved** items so the status flow stays
`pending → approved → matched`.

### 📦 Status flows
- **Lost item:**  pending → approved → matched → claimed → closed (or rejected)
- **Found item:** pending → approved → matched → returned (or rejected)
- **Match:**      pending → confirmed / dismissed
- **Claim:**      pending → approved / rejected → (item returned)

---

## CRUD checklist

| Module      | Create | Read | Update | Delete |
|-------------|:------:|:----:|:------:|:------:|
| Users       | ✅     | ✅   | ✅     | ✅ (deactivate recommended) |
| Lost Items  | ✅     | ✅   | ✅     | ✅     |
| Found Items | ✅     | ✅   | ✅     | ✅     |
| Categories  | ✅     | ✅   | ✅     | ✅     |
| Locations   | ✅     | ✅   | ✅     | ✅     |
| Claims      | ✅     | ✅   | ✅ (review) | ✅ (cancel) |

---

## Folder structure

```
lost2found/
├── index.php              Public landing page
├── auth/                  login, register, logout (first registered user = admin)
├── resident/              dashboard, reports (CRUD), browse, matches, claims,
│                          notifications, profile
├── admin/                 dashboard, users, categories, locations,
│                          lost/found management, item_view, matches,
│                          claims, statistics
├── includes/              header (sidebar + topbar shell), footer
├── config/                db.php (PDO), config.php (auth, helpers, matching)
├── database/              lost2found.sql (schema + seeds)
├── assets/                css/style.css (design system), js/app.js
└── uploads/               item photos (auto-created)
```

## Security notes
- Passwords hashed with `password_hash()`
- All queries use **PDO prepared statements**
- **CSRF tokens** on every state-changing request
- Output escaped with `htmlspecialchars()` via `e()`
- Uploaded images validated by real MIME type, renamed randomly, capped at 5 MB
- Residents can only edit/delete **their own** unlocked reports

> Tip: change your password after the first login (My Profile → Change password).
