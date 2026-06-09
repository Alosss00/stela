# Extracted Sections from employees.php

**Note:** These are the exact strings with all whitespace preserved for use with `replace_string_in_file`.

---

## SECTION 1: Lines 514-518 (Position field section)

```html
                <div class="form-group-modal full-width">
                    <label>Position <span class="text-danger">*</span></label>
                    <input type="text" name="position" class="form-control-modal" required placeholder="Example: Rigger, HSE Superintendent">
                </div>

```

---

## SECTION 2: Lines 519-525 (Department and Work Scope section)

```html
                <!-- Department disembunyikan, nilai default diatur otomatis -->
                <input type="hidden" name="department" value="General">
                
                <div class="form-group-modal full-width">
                    <label id="scopeLabelEmp">Work Scope <span class="text-danger">*</span></label>
                    <select name="ruang_lingkup" id="scopeSelectEmp" class="form-control-modal" required>
                        <option value="">-- Select Work Scope --</option>
```

---

## SECTION 3: Lines 531-538 (Competency Type field section)

```html
                <div class="form-group-modal full-width">
                    <label>Competency Type <span class="text-danger">*</span></label>
                    <select name="competency_type" class="form-control-modal" id="addCompetencyType" onchange="toggleCompetencyField()" required>
                        <option value="">-- Select Competency Type --</option>
                        <option value="pengawas_operasional">Operational Supervisor</option>
                        <option value="pengawas_teknis">Technical Supervisor</option>
                        <option value="tenaga_teknis">Technical Personnel</option>
                    </select>
```

---

## SECTION 4: Lines 626-635 (CV Upload section)

```html
                        <i class="fas fa-file-upload"></i>
                        <input type="file" name="cv_file" class="file-input-modal" accept=".pdf,.doc,.docx" required>
                        <span class="file-text">Click or drag your CV file (PDF/DOC/DOCX, Max 5MB)</span>
                        <span class="file-name"></span>
                    </div>
                </div>
                
                <div class="form-group-modal full-width">
                    <label>Upload Signature <span class="text-muted">(Optional)</span></label>
                    <div class="file-upload-modal">
```

---

## SECTION 5: Lines 44-49 (Validation section in PHP)

```php
        if (empty($employee_code) || empty($full_name) || empty($position) || empty($department) || empty($contractor_company) || empty($competency_type) || empty($ruang_lingkup)) {
            $error = 'All fields are required!';
        } elseif (empty($competency_name)) {
            $error = 'Competency is required for all competency types!';
        } elseif ($competency_type == 'pengawas_operasional' && empty($supervision_area)) {
            $error = 'Supervision Area is required for Operational Supervisors!';
```

---

## SECTION 6: Lines 51-70 (CV upload handling in PHP)

```php
        
        if (empty($error)) {
            // Handle CV upload
            $cv_file = '';
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                $upload_dir = 'assets/uploads/cv/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION);
                $cv_file = 'cv_' . $employee_code . '_' . time() . '.' . $file_ext;
                
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $cv_file)) {
                    $cv_file = 'uploads/cv/' . $cv_file;
                } else {
                    $cv_file = '';
                }
            }

```

---

## SECTION 7: Lines 72-85 (Area around signature file handling in PHP)

```php
            $signature_file = '';
            if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] == 0) {
                $sig_upload_dir = 'assets/uploads/signatures/';
                if (!file_exists($sig_upload_dir)) {
                    mkdir($sig_upload_dir, 0777, true);
                }
                
                $sig_file_extension = strtolower(pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION));
                $sig_new_filename = 'signature_' . $employee_code . '_' . time() . '.' . $sig_file_extension;
                $sig_upload_path = $sig_upload_dir . $sig_new_filename;
                
                if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $sig_upload_path)) {
                    $signature_file = 'uploads/signatures/' . $sig_new_filename;
                }
```

---

## SECTION 8: Lines 96-110 (Insert fields section in PHP)

```php
            $insert_fields = ['employee_code', 'full_name', 'position', 'department', 'competency_type', 'contractor_company', 'ruang_lingkup', 'cv_file', 'verification_status', 'is_active'];
            $insert_values = ["'$employee_code'", "'$full_name'", "'$position'", "'$department'", "'$competency_type'", "'$contractor_company'", "'$ruang_lingkup'", "'$cv_file'", "'pending'", "1"];
            
            // Add optional fields if they exist in the table
            if (in_array('competency_name', $available_columns) && !empty($competency_name)) {
                $insert_fields[] = 'competency_name';
                $insert_values[] = "'$competency_name'";
            }
            
            if (in_array('supervision_area', $available_columns) && !empty($supervision_area)) {
                $insert_fields[] = 'supervision_area';
                $insert_values[] = "'$supervision_area'";
            }
            
            if (in_array('signature_file', $available_columns) && !empty($signature_file)) {
```

---

## SECTION 9: Lines 747-780 (toggleCompetencyField JavaScript function)

```javascript
                <button type="submit" class="btn btn-primary">Save & Submit for Verification</button>
            </div>
        </form>
    </div>
</div>

<script>
const competenciesData = <?php echo json_encode($competencies_by_type); ?>;
const competenciesTableExists = <?php echo json_encode($competencies_table_exists); ?>;
const certificationsData = <?php echo json_encode($certifications_data); ?>;
const positionsData = <?php echo json_encode($positions_by_type); ?>;
const REQUIRES_COMPETENCY = ['pengawas_teknis', 'tenaga_teknis'];

function updateCompanyType() {
    // No action needed - removed department population logic
}

function updateScopeOptions() {
    // No action needed
}

function toggleCompetencyField() {
    const competencyType = document.getElementById('addCompetencyType').value;
    const supervisionAreaGroup = document.getElementById('supervision_area_group');
    const competencyGroup = document.getElementById('competency_group');
    const competencyInput = document.getElementById('addCompetencyName');
    const supervisionAreaInput = document.getElementById('addSupervisionArea');
    
    // Reset required attributes
    competencyInput.removeAttribute('required');
    supervisionAreaInput.removeAttribute('required');

    if (competencyType === 'pengawas_operasional') {
        // Tampilkan kedua field untuk pengawas operasional
```

---

## Usage Guide

When using these with `replace_string_in_file`, remember to:
1. Include 3-5 lines of context before and after the target string
2. Match whitespace exactly (spaces, tabs, newlines)
3. Ensure line numbers correspond to the current state of the file
