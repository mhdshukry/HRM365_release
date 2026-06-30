# HRM365 Tests

Run the attendance calendar smoke test from the release root:

```powershell
php HRM365/tests/attendance_calendar_smoke.php
```

The test uses an in-memory SQLite database and does not touch the live `hrm365` MySQL database.
It verifies the shared attendance calendar rules for working days, no-shift days, holidays, full leave,
partial leave, short leave, and overtime skipping blocked dates.

Run the payroll smoke test from the release root:

```powershell
php HRM365/tests/payroll_smoke.php
```

The payroll test also uses an in-memory SQLite database. It verifies unpaid absence generation, paid and
unpaid leave handling, partial and short leave deductions, OT calculation, EPF/ETF calculation, payroll
feature switches, and protection for payroll records already marked as `Paid`.
