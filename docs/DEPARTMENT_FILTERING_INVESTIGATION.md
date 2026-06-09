# Department Filtering Issue - Investigation Report

## Executive Summary
The department filtering in `pages/dept/dashboard.php` is not working correctly because the SQL queries use an ambiguous column reference when joining multiple tables.

---

## 1. EMPLOYEES TABLE STRUCTURE

### Column Definition
```sql
`department` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
```

### Sample Department Values in Database
- `'General'`
- `'HSE&Formalities'`
- `'Mining Tech Service'`
- `NULL` (many employee records have NULL department)

**Example employees with departments:**
- ID 61: department = `'HSE&Formalities'` (Leo, Maintenance Specialist)
- ID 62: department = `'HSE&Formalities'` (Siska, HSE Officer)
- ID 64: department = `'HSE&Formalities'` (Windy, Maintenance Specialist)
- ID 58: department = `'General'` (Windy, HSE Officer)

---

## 2. DASHBOARD.PHP CODE ANALYSIS

### Lines 21-23: Department Condition Construction
```php
$department_condition = $is_superadmin
    ? '1=1'
    : "department = '" . $db->escapeString($_SESSION['department'] ?? '') . "'";
```

**Issue:** The condition is built as a bare column reference without a table alias.

### Lines 73-94: Recent Appointments Query
```php
$recent_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, p.position_name,
           u1.full_name as created_by_name,
           u3.full_name as ktt1_approved_name,
           u4.full_name as ktt2_approved_name,
           CASE 
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'pending' THEN 'warning'
               WHEN a.status = 'rejected' THEN 'danger'
               ELSE 'secondary'
           END as status_class
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u1 ON a.created_by = u1.id
    LEFT JOIN users u3 ON a.ktt1_approved_by = u3.id
    LEFT JOIN users u4 ON a.ktt2_approved_by = u4.id
    WHERE $department_condition
    ORDER BY a.created_at DESC
    LIMIT 15
");
```

---

## 3. ROOT CAUSE ANALYSIS

### The Problem
When `$department_condition` contains `"department = 'HSE&Formalities'"`, it's inserted into a query that joins 5 tables:
- `appointments` (alias: `a`)
- `employees` (alias: `e`)
- `positions` (alias: `p`)
- `users` (alias: `u1`, `u3`, `u4`)

**None of these tables are explicitly specified in the WHERE clause!**

### Why This Fails
1. **Ambiguity**: The bare column reference `department` is ambiguous
2. **Multiple departments**: Both `employees` and `users` tables have `department` columns
3. **MySQL behavior**: MySQL will either:
   - Pick the first matching column (usually `employees.department`)
   - Throw an error if it can't determine which table
   - Return incorrect results

### Query Execution Issue
When the WHERE clause is:
```sql
WHERE department = 'HSE&Formalities'
```

It should be:
```sql
WHERE e.department = 'HSE&Formalities'
```

This is critical because the query joins through `appointments -> employees`, but without the alias, the column reference is ambiguous.

---

## 4. SESSION DATA ANALYSIS

### User Session for HSE&Formalities Department
From `includes/auth.php` (lines 76-79):
```php
function hasDepartment() {
    return !empty($_SESSION['department']);
}
```

When a `department_user` logs in, `$_SESSION['department']` should be set to the user's department (e.g., `'HSE&Formalities'`).

---

## 5. AFFECTED QUERIES

All the following queries have the same issue:

| Line | Query | Issue |
|------|-------|-------|
| 26 | `SELECT COUNT(*) FROM employees WHERE $department_condition...` | ✓ Works (single table) |
| 27-29 | Employee statistics queries | ✓ Works (single table) |
| 32-58 | Appointment count queries | ✗ **FAILS** (joins without alias) |
| 61-71 | Certificate expiration query | ✗ **FAILS** (uses `($department_condition)`) |
| 74-94 | Recent appointments query | ✗ **FAILS** (joins without alias) |

---

## 6. THE FIX

### Required Changes to `pages/dept/dashboard.php`

Update line 21-23 to use the table alias `e`:

**BEFORE:**
```php
$department_condition = $is_superadmin
    ? '1=1'
    : "department = '" . $db->escapeString($_SESSION['department'] ?? '') . "'";
```

**AFTER:**
```php
$department_condition = $is_superadmin
    ? '1=1'
    : "e.department = '" . $db->escapeString($_SESSION['department'] ?? '') . "'";
```

### Why This Works
- When used in queries with `JOIN employees e`, the `e.department` explicitly references the employees table
- When used in queries with only the employees table, `e.department` still works (table alias is required in those queries anyway)
- Eliminates column ambiguity in multi-table joins

### Verification
After applying this fix, all filtered queries will correctly:
1. Filter appointments by the logged-in user's department
2. Show only employees in that department
3. Display only relevant statistics and certificates
4. Properly handle the `HSE&Formalities` department value

---

## 7. SUMMARY TABLE

| Item | Finding |
|------|---------|
| **Department Column** | Exists in `employees` table (varchar(50), nullable) |
| **Sample Values** | 'General', 'HSE&Formalities', 'Mining Tech Service', NULL |
| **HSE&Formalities Users** | Found in employees table (e.g., ID 61, 62, 64) |
| **Root Cause** | Ambiguous `department` column reference in WHERE clause with joins |
| **Affected Queries** | 6 out of 10+ queries (those with table joins) |
| **Fix Required** | Change `department = '...'` to `e.department = '...'` on line 23 |
| **Difficulty** | Low - single line change |
| **Risk Level** | Low - fixes ambiguity, no breaking changes |

