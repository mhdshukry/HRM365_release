# Database Cleanup Checklist

Run this only after taking a verified database backup.

## Candidate legacy tables

- `leave_requests`
- `payroll`

## Pre-cleanup checks

1. Export a full SQL backup of the live `hrm365` database.
2. Search the application for direct reads/writes to the legacy tables.
3. Confirm all leave workflows use `leave_applications`, `leave_types`, `leave_policies`, and `leave_balances`.
4. Confirm all payroll workflows use `payroll_records`.
5. Compare row counts and sample employee history before deleting any legacy table.

## Suggested SQL audit

```sql
SELECT COUNT(*) AS legacy_leave_requests FROM leave_requests;
SELECT COUNT(*) AS legacy_payroll_rows FROM payroll;
SELECT COUNT(*) AS current_leave_applications FROM leave_applications;
SELECT COUNT(*) AS current_payroll_records FROM payroll_records;
```

Only drop the legacy tables after confirming the application no longer depends on them and the backup can be restored.
