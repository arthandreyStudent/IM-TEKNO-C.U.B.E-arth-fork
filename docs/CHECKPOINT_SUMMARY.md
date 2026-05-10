# Increment 1 Checkpoint Summary & Architecture Overview

## Project Architecture
- **Tech Stack**: Vanilla PHP (Procedural with utility functions), MySQL (via `mysqli`), HTML, CSS, Vanilla JS.
- **Routing**: File-based routing (e.g., `admin/dashboard.php`, `student/borrow.php`).
- **State Management**: Native PHP sessions (`$_SESSION`) for authentication and flash messages.
- **Security**: Prepared statements for SQL injection prevention, `password_hash`/`password_verify` for passwords, and basic role-based access control via `require_role()`.

## Active Business Rules
- **Reservation expiry**: unclaimed instructor reservations are auto-cancelled 30 minutes after the scheduled start time.
- **Availability recalculation**: `refresh_future_reservation_conflicts()` now also clears expired reservations before recalculating future stock conflicts.

## Core Application Flows & State
- **User Roles**: Admin (Lab Staff), Student, Instructor.
- **Inventory Management**: CRUD available for Admin. Pre-populated for all six college departments.
- **Borrowing Flow**: Students borrow items. When returning, they only "Request Return". The transaction is not finalized until an Admin/Lab Staff inspects the item.
- **Inspection Flow**: Admin inspects returned items. If marked "Good" or "Worn", the transaction closes. If marked "Damaged", a `Breakage_report` is automatically created, the item is not restocked, and the student's `HasLiability` flag is set to true using the `ReplacementCost`.
- **Reservation Flow**: Instructors can make batch reservations with specific quantities.`QuantityReserved` is updated and affects available stock.

## Important AI Context & Decisions
1. **No direct item returns**: Always route returns through Admin inspection.
2. **Database conventions**: Use prepared statements exclusively. Rely on `connect.php` utility functions like `url()`, `h()`, `redirect()`, and `set_flash()`.
3. **UI Rules**: Use line-style CSS icons rather than emojis. Theming uses CIT-U inspired maroon and gold classes.
4. **Liabilities**: Handled automatically via inspection status.

## Required Items Addressed

1. **CRUD of the Admin user**
   - Admin can create, view, edit, and delete users through `admin/users/`.
   - Public registration does not create Admin users. Admin users are controlled from the Admin portal.

2. **Different entity CRUD**
   - Department CRUD is available in `admin/departments/`.
   - Inventory CRUD is available in `admin/inventory/`.

3. **Database aligned with ERD**
   - Tables implemented: User, Student, Instructor, Department, Inventory_item, Borrow_transaction, Breakage_report, Reservation_batch, and Reserved_item.
   - The schema follows the submitted ERD but includes practical implementation columns such as Email, PasswordHash, CreatedAt, and QuantityReserved.

4. **Updated ERD-ready changes**
   - Reserved_item now includes QuantityReserved so instructors can reserve specific quantities.
   - Borrow_transaction supports Return Requested status so the Admin/Lab Staff can inspect returned items before closure.

5. **UI and flow improvements**
   - CIT-U inspired maroon and gold theme.
   - No emoji icons, replaced with line-style CSS icons.
   - Registration dropdowns for departments and courses.
   - Student and Instructor dashboard sorting and filtering.
   - Admin return inspection and breakage report generation.
