# CODE SNIPPETS - Ready to Implement
## Copy-Paste untuk Update pages/dept/employee_detail.php

---

## 📋 CSS CHANGES - Full Style Block

### REPLACE the entire `<style>` section dengan ini:

```css
<style>
.employee-detail-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* EMPLOYEE HEADER CARD - NEW */
.employee-header-card {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.employee-header-content {
    display: flex;
    align-items: center;
    gap: 30px;
}

.employee-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: #37474F;
    font-weight: bold;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.employee-header-info h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
}

.employee-header-info p {
    margin: 5px 0;
    opacity: 0.9;
}

/* INFO GRID */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-left: 4px solid #37474F;  /* CHANGED from #FFA240 */
}

.info-card h4 {
    margin: 0 0 15px 0;
    color: #37474F;  /* CHANGED from #F57C00 */
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #666;
    min-width: 150px;
}

.info-value {
    color: #333;
    flex: 1;
}

/* CERTIFICATIONS SECTION */
.cert-section {
    background: white;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 20px;
    border-left: 4px solid #37474F;  /* CHANGED from #FFA240 */
}

.cert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.cert-header h3 {
    margin: 0;
    color: #333;  /* CHANGED from #F57C00 */
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.cert-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.cert-table thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    padding: 15px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
    font-size: 13px;
}

.cert-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}

.cert-table tbody tr:hover {
    background-color: #f8f9fa;
}

/* STATUS BADGES */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.verified {
    background: #d4edda;
    color: #155724;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.expired {
    background: #e2e3e5;
    color: #383d41;
}

/* ALERT STYLES - NEW */
.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert-info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

/* BUTTONS */
.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.badge-danger {
    background: #fee2e2;
    color: #ef4444;
}

.badge-secondary {
    background: #f3f4f6;
    color: #666;
}

.btn {
    background: #37474F;  /* CHANGED from #E8F5E9 */
    color: white;  /* CHANGED from #2E7D32 */
    gap: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #37474F;  /* CHANGED from #F57C00 */
    transform: translateY(-2px);
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.btn-info {
    background: #37474F;
    color: white;
}

.btn-info:hover {
    background: #37474F;
}

/* UTILITIES */
.no-data-row {
    text-align: center;
    padding: 40px !important;
    color: #999;
}

.text-muted {
    color: #6c757d;
}

.text-danger {
    color: #dc3545;
    font-weight: 500;
}

.text-warning {
    color: #ffc107;
    font-weight: 500;
}

.table-responsive {
    overflow-x: auto;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .employee-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .employee-detail-container {
        padding: 15px;
    }
    
    .info-label {
        min-width: 120px;
        font-size: 13px;
    }
    
    .info-value {
        font-size: 13px;
    }
    
    .cert-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}
</style>
```

---

## 📝 HTML CHANGES

### ADD ini sebelum closing PHP tag dan sebelum `?>` line (sebelum `<!-- Employee Information Grid -->`)

```php
// Initialize message and error variables if not already present
if (!isset($message)) {
    $message = '';
}
if (!isset($error)) {
    $error = '';
}
?>

<!-- ALERTS -->
<?php if ($message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="employee-detail-container">
    <!-- EMPLOYEE HEADER CARD - NEW -->
    <div class="employee-header-card">
        <div class="employee-header-content">
            <div class="employee-avatar">
                <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
            </div>
            <div class="employee-header-info">
                <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
                <p><i class="fas fa-id-badge"></i> <span data-lang="id-short">ID</span>: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['position'] ?? '-'); ?></p>
                <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['contractor_company'] ?? '-'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Employee Information Grid -->
    <!-- ... rest of existing content ... -->
```

---

## 🔍 LOCATION GUIDE

### Step 1: Find the `<style>` tag
- Open `pages/dept/employee_detail.php`
- Find the `<style>` tag (usually around line 70-80)
- Look for `.info-card` styling
- Replace entire `<style>` ... `</style>` block

### Step 2: Find the HTML section
- Look for `<!-- Employee Information Grid -->`
- This should be after `?>` closing PHP tag
- Before this line, add the ALERTS HTML and HEADER CARD

### Step 3: Current structure (before change)
```
...
?>

<!-- Employee Information Grid -->
<div class="info-grid">
```

### Step 4: New structure (after change)
```
...
?>

<!-- ALERTS -->
<?php if ($message): ?>
...

<div class="employee-detail-container">
    <!-- EMPLOYEE HEADER CARD -->
    ...
    
    <!-- Employee Information Grid -->
    <div class="info-grid">
```

---

## ⚠️ CRITICAL CHANGES

### Color Replacements (in CSS)
```
FIND & REPLACE:
1. #FFA240  ──→  #37474F
2. #F57C00  ──→  #37474F (untuk h4, h3)
3. #E8F5E9  ──→  #37474F (untuk button)
4. #2E7D32  ──→  white (untuk button text)
```

### Specific Classes Updated
```
✓ .info-card border-left color
✓ .info-card h4 color
✓ .cert-section border-left color
✓ .cert-header h3 color
✓ .btn background
✓ .btn-secondary:hover background
```

### New Classes Added
```
+ .employee-header-card (with gradient)
+ .employee-header-content (flex layout)
+ .employee-avatar (circle)
+ .employee-header-info (text styling)
+ .alert (base styling)
+ .alert-success (green)
+ .alert-error (red)
```

---

## ✅ VERIFICATION CHECKLIST

After making changes, verify:

- [ ] Avatar circle displays correctly (120x120px)
- [ ] Avatar background is white with grey text
- [ ] Header card has dark grey gradient background
- [ ] Employee name, ID, position display in header
- [ ] All info cards have grey left border (not orange)
- [ ] All card headings are grey (not orange)
- [ ] Info cards display in 3-column grid on desktop
- [ ] Layout is responsive on mobile
- [ ] All buttons look correct
- [ ] Status badges display correctly
- [ ] No CSS conflicts with header.php or other includes
- [ ] Color scheme matches user/employee_detail.php

---

## 🐛 COMMON ISSUES & FIXES

### Issue 1: Avatar doesn't display
**Cause:** Missing Font Awesome icon library
**Fix:** Verify `<i class="fas fa-*"></i>` work in header.php

### Issue 2: Colors still orange
**Cause:** CSS not saved or cached
**Fix:** Hard refresh browser (Ctrl+Shift+R), clear cache

### Issue 3: Layout broken
**Cause:** Flexbox not supported or conflicting
**Fix:** Check for older CSS rules overriding, use browser dev tools

### Issue 4: Avatar circle looks like square
**Cause:** border-radius not applied
**Fix:** Verify width/height are equal (120px each)

### Issue 5: Alerts don't show
**Cause:** $message or $error variables not initialized
**Fix:** Add initialization at top of PHP

---

## 📦 FILES INVOLVED

**Main File to Edit:**
- `pages/dept/employee_detail.php` ← ONLY THIS FILE

**Reference File (for comparison):**
- `pages/user/employee_detail.php` ← Don't modify

**Include Files (not changed):**
- `includes/header.php`
- `includes/footer.php`
- `includes/db.php`

---

## 🎯 IMPLEMENTATION TIME ESTIMATE

- CSS Update: 15 minutes
- HTML Update: 10 minutes
- Testing: 15 minutes
- Refinement: 10 minutes
- **Total: ~50 minutes**

---

## 📞 QUICK REFERENCE

### Key CSS Colors
```css
Grey Primary: #37474F
Grey Secondary: #616161
Alert Success: #d4edda
Alert Error: #f8d7da
Avatar Background: white
Avatar Text: #37474F
```

### Key HTML Elements
```html
<!-- Avatar Circle -->
<div class="employee-avatar">
    <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
</div>

<!-- Alert -->
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> Message here
</div>
```

### Key CSS Dimensions
```css
Avatar: 120px x 120px
Border-radius: 50% (for circle)
Header padding: 30px
Header gap: 30px
Card padding: 20px
Grid gap: 20px
```

---
