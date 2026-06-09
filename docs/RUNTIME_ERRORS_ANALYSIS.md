# Runtime Errors Analysis: pages/user/employee_detail.php

**Analysis Date:** May 8, 2026  
**File:** `pages/user/employee_detail.php` (685 lines)  
**Comparison:** `pages/dept/employee_detail.php` (590 lines - CORRECT STRUCTURE)

---

## 1. CRITICAL ISSUES (Priority: CRITICAL)

### Issue 1: **Undefined Variable - $db Used Before Initialization**

**Location:** Line 18 (in POST handler)  
**Error Type:** Fatal Error - Call to undefined method on non-object/null  

**Code:**
```php
// Line 6-7: Include files
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Line 10: checkPageAccess() - SUCCESS ✓
checkPageAccess(['user']);

// Line 13-78: POST HANDLER STARTS HERE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_certificate') {
    
    // Line 18: ❌ ERROR - $db is NOT defined yet!
    $emp_check = $db->query("SELECT id FROM employees WHERE id = $employee_id AND contractor_company = '" . 
                            $db->escapeString($company_name) . "'")
                ->fetch_assoc();
    // ... uses $db->query() and $db->escapeString() multiple times
}

// Line 79: Include header.php
require_once '../../includes/header.php';

// Line 81: ❌ $db is FIRST INITIALIZED HERE
$db = new Database();

// Line 82: ❌ $company_name is FIRST INITIALIZED HERE
$company_name = $_SESSION['company_name'] ?? '';
```

**Problem:**  
- POST handler runs BEFORE $db initialization
- POST handler runs BEFORE $company_name initialization
- Will cause fatal error when form is submitted

**Expected Error Output:**
```
Fatal error: Call to undefined method on null in employee_detail.php on line 18
OR
Uncaught Error: Call to a member function query() on null
```

**Correct Structure (from dept/employee_detail.php):**
```php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/header.php';  // ← FIRST include

$db = new Database();  // ← THEN initialize
$department = $_SESSION['department'] ?? '';  // ← THEN get session

// ← THEN handle POST (no POST handler in dept version, but logic would go here)
```

**Fix:** Move POST handler AFTER $db and $company_name initialization, or move initialization BEFORE POST handler.

---

### Issue 2: **Undefined Variable - $company_name Used Before Initialization**

**Location:** Line 16 (in POST handler)  
**Error Type:** Notice → Undefined variable `$company_name`  

**Code:**
```php
// Line 16: Used in POST handler
$emp_check = $db->query("... AND contractor_company = '" . $db->escapeString($company_name) . "'")

// Line 82: First defined here
$company_name = $_SESSION['company_name'] ?? '';
```

**Impact:**  
- Variable undefined, will use empty string or cause error
- SQL query will be incomplete/incorrect
- Employee verification will fail

---

### Issue 3: **Missing Null Check on $certifications Before Accessing ->num_rows**

**Location:** Line 544  
**Error Type:** Potential Fatal Error - Call to a member function on null/bool  

**Code:**
```php
// Line 115: Query executed
$certifications = $db->query("SELECT ec.*, c.cert_name ... FROM employee_certifications ...");

// ... many lines later...

// Line 544: ❌ NO null check - if query fails, $certifications is FALSE
<?php if ($certifications->num_rows > 0): ?>
    <?php while ($cert = $certifications->fetch_assoc()): ?>
        <!-- display cert -->
    <?php endwhile; ?>
<?php endif; ?>
```

**Problem:**  
- If `$db->query()` fails (SQL error, DB connection issue), it returns `false` (bool), not a result object
- Accessing `->num_rows` on `false` causes: `Call to a member function num_rows() on bool`
- Fatal error crashes the page

**Correct Pattern (from dept/employee_detail.php line 233):**
```php
<?php if ($certifications && $certifications->num_rows > 0): ?>
    <!-- This checks if $certifications is truthy BEFORE accessing ->num_rows -->
<?php endif; ?>
```

**Fix:** Add null/bool check:
```php
<?php if ($certifications && $certifications->num_rows > 0): ?>
```

---

## 2. MEDIUM PRIORITY ISSUES

### Issue 4: **Missing Null Check on $employee Result**

**Location:** Line 99 (employee info display)  
**Error Type:** Potential Undefined Array Key Warning  

**Code:**
```php
// Line 93: Query result
$employee = $db->query("SELECT e.*, u.full_name as verified_by_name, u.username as verified_by_username ...")
           ->fetch_assoc();

// Line 98: Redirect if not found
if (!$employee) {
    header('Location: employees.php');
    exit();
}

// Line 101 ONWARDS: Uses $employee array
<?php echo htmlspecialchars($employee['full_name']); ?>
```

**Current Status:** ✓ **GOOD** - Proper redirect check after query

---

### Issue 5: **Potential Issue with Multiple $db Initializations?**

**Location:** Lines 6-7 vs Line 81  
**Error Type:** N/A - FALSE POSITIVE  

**Analysis:**
- Line 6: `require_once '../../includes/db.php'` - includes the Database CLASS definition
- Line 81: `$db = new Database()` - instantiates ONE Database object
- **NO ISSUE** - Database class is loaded once, instance created once ✓

---

## 3. DATABASE METHOD VALIDATION

### Database Class Methods (includes/db.php)

✓ **query($sql)** - VALID  
```php
public function query($sql) {
    return $this->conn->query($sql);
}
```

✓ **escapeString($string)** - VALID  
```php
public function escapeString($string) {
    return $this->conn->real_escape_string($string);
}
```

✓ **prepare($sql)** - Available but not used  
✓ **lastInsertId()** - Available but not used  

**Verdict:** All methods used are VALID ✓

---

## 4. FUNCTION VALIDATION

### checkPageAccess() Function

**Location:** includes/auth.php (Line 38)  
**Status:** ✓ **FUNCTION EXISTS**  
**Usage:** `checkPageAccess(['user'])` at line 7

**Note:** Function is called BEFORE $db initialization, which is CORRECT because auth checks don't require database.

---

## 5. CONSISTENCY COMPARISON: /user vs /dept

| Aspect | /user/employee_detail.php | /dept/employee_detail.php | Status |
|--------|---------------------------|--------------------------|--------|
| Include order | auth → db → checkPageAccess → header | auth → db → header | ❌ WRONG |
| $db initialization | Line 81 (AFTER POST handler) | Line 13 (BEFORE logic) | ❌ WRONG |
| $certifications null check | Line 544: NO check | Line 233: `if ($certifications && ...)` | ❌ WRONG |
| $employee null check | Line 98: Has check | Line 31: Has check | ✓ OK |
| Database methods used | query(), escapeString() | query(), real_escape_string() | ✓ OK |
| Access control | checkPageAccess(['user']) | hasDepartment() check | ✓ Both valid |

---

## SUMMARY TABLE

| # | Issue | Severity | Type | Line | Impact |
|---|-------|----------|------|------|--------|
| 1 | $db used before init | CRITICAL | Fatal Error | 18 | Page crashes on POST |
| 2 | $company_name undefined | CRITICAL | Fatal Error | 16 | POST handler fails |
| 3 | No null check on $certifications | CRITICAL | Fatal Error | 544 | Page crashes if query fails |
| 4 | POST handler before init | CRITICAL | Logic Error | 13-78 | All POST vars undefined |
| 5 | checkPageAccess OK | ✓ | N/A | 7 | Works correctly |
| 6 | Database methods valid | ✓ | N/A | - | All methods exist |

---

## RECOMMENDATIONS

### HIGH PRIORITY FIXES (Do these first)

1. **Fix: Move $db and $company_name initialization BEFORE POST handler**
   - Move lines 81-82 to line 10 (after requires, after checkPageAccess)
   - Or move the POST handler logic to AFTER line 82
   
2. **Fix: Add null check to $certifications**
   - Line 544: Change `if ($certifications->num_rows > 0)` 
   - To: `if ($certifications && $certifications->num_rows > 0)`

3. **Fix: Ensure POST handler uses proper order**
   - Verify $employee_id is retrieved from $_POST BEFORE checking DB
   - Verify $company_name is available BEFORE using in query

### MEDIUM PRIORITY IMPROVEMENTS

4. **Consider: Add error handling to database queries**
   ```php
   $certifications = $db->query($sql);
   if (!$certifications) {
       $error = "Error retrieving certifications: " . $db->getConnection()->error;
       $certifications = null;
   }
   ```

5. **Consider: Validate $_POST['employee_id'] matches URL id**
   - Line 15: `$employee_id = intval($_POST['employee_id']);`
   - Should verify this matches the GET parameter $id

### TESTING CHECKLIST

- [ ] Submit form with POST request → Should NOT crash
- [ ] Submit certificate with file upload → Should work
- [ ] Employee with no certifications → Should show "No data" instead of crash
- [ ] Database connection error → Should show proper error, not fatal error
- [ ] Access from different company → Should redirect properly

---

## CODE STRUCTURE COMPARISON

### ❌ WRONG (Current - user/employee_detail.php)
```
1. require auth
2. require db (just class, not instance)
3. checkPageAccess (BEFORE $db exists!)
4. [POST HANDLER - uses $db and $company_name that don't exist yet]
5. include header
6. $db = new Database()  ← FIRST TIME $db exists
7. $company_name = $_SESSION...
8. Process queries...
```

### ✓ CORRECT (dept/employee_detail.php)
```
1. require auth
2. require db
3. require header
4. $db = new Database()
5. $conn = $db->getConnection()
6. Get session variables
7. Process queries...
```

---

## SEVERITY LEVELS

- **CRITICAL (Must Fix):** Issues 1, 2, 3, 4 - Will cause page crashes
- **HIGH (Should Fix):** None - but Issue 1-4 must be addressed
- **MEDIUM (Nice to Fix):** Issues 5 (add error handling)
- **LOW (Can ignore):** Database method validation (already correct)

