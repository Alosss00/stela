# PERBANDINGAN employee_detail.php
## Dept vs User Version

**File Structure:**
- `pages/dept/employee_detail.php` - 561 baris
- `pages/user/employee_detail.php` - 685 baris (user version lebih lengkap)

---

## PERBEDAAN UTAMA

### 1. HEADER / AVATAR SECTION ❌ DEPT TIDAK PUNYA
**User Version (yang harus ditambahkan ke Dept):**
```php
<!-- Employee Header -->
<div class="employee-header-card">
    <div class="employee-header-content">
        <div class="employee-avatar">
            <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
        </div>
        <div class="employee-header-info">
            <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
            <p><i class="fas fa-id-badge"></i> <span data-lang="id-short">ID</span>: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
            <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['position'] ?? '-'); ?></p>
            <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['contractor_company']); ?></p>
        </div>
    </div>
</div>
```

**CSS untuk Avatar (User Version):**
```css
.employee-header-card {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
```

---

### 2. COLOR SCHEME / STYLING ⚠️ BERBEDA
**Dept Version (Current - ORANGE):**
- Primary Color: `#FFA240` (Orange Light), `#F57C00` (Orange Dark)
- Used in: `.info-card` border-left, headings, buttons

**User Version (Target - DARK GREY):**
- Primary Color: `#37474F` (Dark Grey), `#616161` (Grey)
- Used in: `.employee-header-card`, buttons gradient

**Perubahan yang diperlukan di Dept:**

#### Info Card Styling
```css
/* DARI (Dept - Orange): */
.info-card {
    border-left: 4px solid #FFA240;
}
.info-card h4 {
    color: #F57C00;
}

/* KE (User - Grey): */
.info-card {
    border-left: 4px solid #37474F;
}
.info-card h4 {
    color: #37474F;
}
```

#### Cert Header Styling
```css
/* DARI (Dept): */
.cert-header h3 {
    color: #F57C00;
}

/* KE (User): */
.cert-header h3 {
    color: #333;  /* atau #37474F */
}
```

#### Button Styling
```css
/* DARI (Dept): */
.btn-secondary:hover {
    background: #F57C00;
}

/* KE (User): */
.btn-secondary:hover {
    background: #37474F;  /* atau remove hover override */
}
```

---

### 3. INFO GRID LAYOUT
**Struktur HTML:** SAMA ✅
- Keduanya menggunakan `.info-grid` dengan 3 cards
- Basic Info, Competency Info, Status & Verification

**Perbedaan kecil - Competency Info Label:**
```php
/* Dept: */
<span class="info-label" data-lang="scope-of-work">Scope:</span>

/* User: */
<span class="info-label" data-lang="scope">Scope:</span>
```

**Perbedaan Content - Status Section:**

Dept version menampilkan status dengan complex logic:
```php
<?php
$status_badges = [
    'verified' => 'verified',
    'pending' => 'pending',
    'rejected' => 'rejected'
];
$status_labels = [...];
$status_lang_keys = [... ];
// Complex status display logic
?>
```

User version lebih simple:
```php
<span class="status-badge <?php echo $status_class[$employee['verification_status']] ?? 'badge-secondary'; ?>">
    <?php echo strtoupper($employee['verification_status']); ?>
</span>
```

---

### 4. MESSAGE / ERROR ALERTS
**User Version (TAMBAHAN di Dept):**
```php
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
```

**CSS untuk alerts:**
```css
.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.alert-info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}
```

---

### 5. MODAL / FORM COMPONENTS
**User Version (TAMBAHAN - tidak ada di Dept):**
- Modal form untuk add certificate
- Form fields: certification select, cert_number, issue_date, validity_years, no_expiry checkbox, expiry_date, document_file, notes
- JavaScript functions: `openAddCertModal()`, `calculateExpiryDate()`, `toggleExpiryField()`
- File upload handling

**Note:** Dept version tidak perlu ini karena view-only

---

### 6. DETAIL FIELDS DITAMPILKAN

**User Version memiliki:**
- Message/Error handling (POST form success/error)
- Complete header card dengan avatar
- All info cards dengan struktur yang sama
- Sidebar/appointment section (mungkin)
- Certificate add form dengan modal

**Dept Version memiliki:**
- Direct employee info cards (tanpa header)
- Certificate/appointment display
- Simplified status handling

---

### 7. DATABASE QUERY STYLE
**Dept:**
```php
$db = new Database();
$conn = $db->getConnection();
$conn->real_escape_string($department)
```

**User:**
```php
$db = new Database();
$db->query()
$db->escapeString()
```

**Status:** Dept lebih verbose dengan connection object, User lebih clean dengan Database class methods

---

## REKOMENDASI PERUBAHAN UNTUK DEPT VERSION

### Priority 1 - VISUAL (CSS)
- [ ] Ubah color scheme dari ORANGE (#FFA240) ke GREY (#37474F)
- [ ] Update `.info-card` border-left color
- [ ] Update `.info-card h4` color
- [ ] Update button hover colors
- [ ] Update `.cert-header h3` color

### Priority 2 - STRUCTURE (HTML)
- [ ] Tambahkan `<div class="employee-header-card">` dengan avatar circle
- [ ] Tambahkan message/error alert divs di atas employee-detail-container
- [ ] Update `.employee-header-card` styles dengan gradient

### Priority 3 - STYLING (Additional CSS)
- [ ] Tambahkan `.employee-header-content` flexbox
- [ ] Tambahkan `.employee-avatar` circle styles
- [ ] Tambahkan `.employee-header-info` styles
- [ ] Tambahkan alert styles

### Priority 4 - OPTIONAL (Enhancements)
- [ ] Simplify status badge logic (opsional, tergantung logic)
- [ ] Update database query style ke Database class methods (opsional)
- [ ] Add form handling untuk add certificate (hanya jika dept perlu functionality ini)

---

## SUMMARY PERUBAHAN

| Aspek | Dept (Current) | User (Target) | Action |
|-------|---|---|---|
| **Header/Avatar** | ❌ Tidak ada | ✅ Ada circle avatar | TAMBAH |
| **Color Scheme** | 🟠 Orange (#FFA240) | 🟦 Grey (#37474F) | UBAH |
| **Message/Alerts** | ❌ Tidak ada | ✅ Ada success/error | TAMBAH |
| **Info Grid** | ✅ Ada | ✅ Ada | SAMA |
| **Status Display** | Complex logic | Simple badge | UBAH (optional) |
| **Modal/Form** | ❌ Tidak ada | ✅ Ada modal | TIDAK perlu (view-only) |
| **Layout** | Minimal | Complete | UPDATE |

---

## FILE CHANGES NEEDED

**Dept File (`pages/dept/employee_detail.php`):**

1. **Line ~80-150** - Update `<style>` section:
   - Ubah warna orange ke grey
   - Tambahkan `.employee-header-card` styles
   - Tambahkan `.employee-avatar` styles
   - Tambahkan alert styles

2. **Line ~150+** - HTML section:
   - Tambahkan message/error alerts sebelum employee-detail-container
   - Tambahkan employee-header-card dengan avatar

3. **Line ~200+** - Update info-card sections:
   - Ubah styling references
   - Update label consistency

---

## CSS MIGRATION CHECKLIST

### Colors to Replace
- `#FFA240` → `#37474F` (info-card border-left)
- `#F57C00` → `#37474F` (headings, buttons)
- `#E8F5E9` → `#37474F` (buttons)
- `#2E7D32` → `white` (button text)

### Components to Add
- [ ] `.employee-header-card` with gradient
- [ ] `.employee-header-content` flexbox
- [ ] `.employee-avatar` circle
- [ ] `.employee-header-info` styling
- [ ] `.alert` and `.alert-*` classes

---
