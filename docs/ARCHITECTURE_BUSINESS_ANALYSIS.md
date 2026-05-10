# Architecture & Business Analysis: TEKNO C.U.B.E.

## Implemented High Impact Concurrency Control
- **Student borrow flow:** locks the student row and the inventory row before the transaction is created, preventing simultaneous over-borrowing of the same stock.
- **Instructor reservation flow:** sorts selected asset numbers before locking them so row acquisition happens in a consistent order and reduces deadlock risk when multiple items are reserved at once.
- **Admin return inspection flow:** locks both the borrow transaction and its linked inventory row before closing the return, so the stock update is applied against a locked record.
- **Primary query/write references:** `student/borrow.php`, `instructor/reservation_add.php`, `admin/returns/inspect.php`, and `connect.php` for shared query helpers and stock recalculation logic.

## Implemented Reservation Expiry Rule
- **Expiry window:** unclaimed reservations are auto-cancelled 30 minutes after the scheduled start time.
- **Effect:** cancelled reservations no longer count toward usable stock or future conflict checks because the shared refresh routine clears them before recalculating availability.
- **Where it runs:** the shared cleanup is triggered from `connect.php` via `refresh_future_reservation_conflicts()` and before the instructor reservation creation page loads item availability.

## Implemented Separation of Concerns Step
- **Environment config:** database credentials now live in `.env` and are loaded by `connect.php` at runtime instead of being hard-coded in the bootstrap.
- **Repository layer:** inventory and availability queries now flow through `repositories/ItemRepository.php` so student, instructor, and admin inventory pages reuse the same SQL logic.
- **Scope of this step:** this is a lightweight repository extraction, not a full MVC migration yet. The router/controller split remains a next-stage refactor.

## Implemented "Under Maintenance" Status
- **Business Rule:** Equipment undergoes maintenance and calibration. Lab Staff can manually mark items as "Under Maintenance" without requiring a student Breakage Report.
- **Database Schema:** `Inventory_item.CurrentCondition` ENUM now includes 'Under Maintenance' option alongside 'Good', 'Worn', and 'Damaged'.
- **Student/Instructor Visibility:** Repository filters exclude "Under Maintenance" items from student browsing and instructor reservation availability lists.
- **Admin Management:** Lab Staff can view all maintenance items in the admin inventory list, which displays with a 'badge-muted' visual badge for quick identification.
- **Validation:** Explicit user-friendly error messages prevent students from borrowing and instructors from reserving maintenance items.
- **Implementation Points:** 
  - `repositories/ItemRepository.php`: Added "Under Maintenance" exclusion to `searchAvailableItems()` and `listReservationCandidates()` WHERE clauses.
  - `admin/inventory/add.php` and `admin/inventory/edit.php`: Condition dropdowns now include "Under Maintenance" option.
  - `admin/inventory/index.php`: Badge logic updated to display 'badge-muted' for maintenance status.
  - `student/borrow.php`: Validation rejects borrowing with message "This item is currently under maintenance and cannot be borrowed."
  - `instructor/reservation_add.php`: Validation rejects reservation with message "This item is under maintenance and cannot be reserved."

## 1. Current State Analysis

### Current Workflows
- **Borrowing (Student):** Students borrow items, use them, and trigger a "Request Return". The cycle only completes when an Admin/Lab Staff inspects the item.
- **Reservations (Instructor):** Instructors create batch reservations for specific items and quantities to secure equipment for classes.
- **Inspection & Liabilities:** Admins inspect returning items. Damaged items automatically generate Breakage Reports and flag the student with a liability, using the replacement cost.

### Edge Cases (Currently Unhandled or Vulnerable)
- **Time/Concurrency Conflicts:** A student might borrow an item today that an instructor explicitly reserved for tomorrow. The system must prevent borrowing if it threatens a confirmed future reservation.
- **Unclaimed Reservations:** Instructors might reserve equipment but fail to pick it up. This permanently locks inventory away from other users.
- **Partial Returns:** A student with multiple borrowed items, or an instructor returning a batch, might return some items but not all. The current flow assumes an all-or-nothing interaction or requires individual transactions per item.
- **Simultaneous Borrowing:** Two students requesting the last available item at the exact same millisecond could result in negative inventory if database transactions/locks aren't strictly enforced.

### Scalability
- **Database Load:** File-based, procedural scripts querying the database on every page load without caching will struggle under heavy concurrent load (e.g., beginning of a lab period when 40 students borrow items).
- **Codebase Size:** Without an MVC framework or structured class hierarchy, adding new features will bloat individual files and make the codebase brittle.

### Maintainability
- **Mixed Concerns:** HTML, SQL, and business logic exist in single files (e.g., `register.php`, `login.php`). This violates the Single Responsibility Principle, making testing impossible without a browser.
- **Global Constraints:** `connect.php` acts as a monolithic utility file. As the application grows, this file will become unmanageable.

### UX Friction
- **Lack of Autonomy/Feedback:** Students stuck in "Return Requested" state cannot borrow new items (if limits are reached) while waiting for an Admin to click a button.
- **Form Resubmission:** Registration and login forms do not persist data on error, forcing users to retype passwords and selections.
- **Bulk Actions:** Admins and Instructors lack bulk action capabilities for approving requests, returning items, or generating reports.

### Role Permissions
- **Granularity:** "Admin" handles both system administration (creating users) and day-to-day lab staff duties (inspecting equipment). This should ideally be split (System Admin vs. Lab Custodian).
- **Data Access:** No clear separation of data boundaries between different departments (e.g., does a CCS Admin see CEA equipment?).

### Missing Business Rules
- **Borrow Limits:** No rule defining the maximum number of items a student can borrow at once.
- **Overdue Rules:** No time-based penalty or automated flagging for items kept beyond their due date/time.
- **Maintenance/Calibration:** Lab equipment requires calibration. There is no status for "Under Maintenance" – items are only good, worn, or damaged.
- **Clearance Checking:** The system does not block login, borrowing, or registration based on outstanding school financial balances.

---

## 2. Recommendations & Strategic Improvements

### 🔴 HIGH IMPACT (Immediate Action Needed)

**1. Implement Concurrency Control & Database Locking**
- **Issue:** Race conditions on the last available item.
- **Solution:** Use SQL `SELECT ... FOR UPDATE` when querying available quantity during the borrow/reserve checkout process to lock the row until the transaction commits.

**2. Introduce Reservation Expiry Rules**
- **Business Rule:** Auto-cancel reservations if not claimed within X minutes of the scheduled time. Return the quantity to `Available`.

**3. Separation of Concerns (Code Architecture)**
- **Refactoring:** Adopt a lightweight router and MVC pattern. Move SQL queries to Repository classes (e.g., `ItemRepository::getAvailable()`) to make business rules reusable across Student and Instructor flows.
- **Environment config:** Move DB credentials out of `connect.php` and into a `.env` file.

**4. Introduce "Under Maintenance" Status**
- **Business Rule:** Equipment degrades. Allow Lab Staff to manually move items from "Available" to "Under Maintenance" without requiring a student Breakage Report.

### 🟡 MEDIUM IMPACT (Process & UX Optimization)

**5. Enhanced Validation Rules**
- Add limits on active borrows (e.g., max 3 items per student).
- Block students from borrowing *any* new items if they currently have an active `HasLiability` flag.
- Enforce CSRF token validation on all POST requests to prevent malicious form submissions.
- Implement soft-deletes (`IsDeleted = 1`) on inventory and users to maintain historical integrity for audits.

**6. Split Admin & Lab Staff Roles**
- **Lab Staff:** Can inspect, approve, and manage inventory conditions.
- **Super Admin:** Can manage users, departments, and system settings.

**7. UI/UX Enhancements**
- **Pagination:** Implement pagination for `student/available_items.php` and `admin/inventory/index.php`.
- **Form Persistence:** Flash session variables back to forms on failed validation so users don't have to re-select dropdowns.
- **Real-time Availability:** Add an AJAX/Fetch call to check item availability right before the user hits "Confirm Borrow", reducing rejection errors.

### 🟢 LOW IMPACT (Future-Proofing & Nice-to-Haves)

**8. Automated Overdue Notifications**
- Set up a cron job or background task to flag overdue items and send email alerts to students.

**9. Barcode / QR Code Integration**
- **Workflow:** Admins scan an item to instantly pull up the Return Inspection screen, removing manual searching from the equation.

**10. Advanced Reporting Dashboard**
- Add charts (using Chart.js) to the Admin dashboard showing: Most borrowed items, breakage trends by department, and peak lab usage hours to justify future equipment budgets.