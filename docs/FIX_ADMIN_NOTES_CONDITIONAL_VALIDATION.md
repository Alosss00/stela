# Admin Notes Conditional Validation Fix

## Problem
User reported that when admin/reviewer reviews data returned by KTT:
- **Accept/Approve** action should NOT require notes (optional)
- **Reject** action should require notes (mandatory)

Previously, notes were required for BOTH actions.

## Solution Implemented

### Files Modified

#### 1. `appointments.php` (Rejection Detail Modal)

**Changes Made**:

1. **Removed `required` attribute from textarea** (Line 842):
```php
<textarea name="admin_notes" id="reviewActionNotes"
          class="form-control-appt" rows="3"
          placeholder="Enter notes or decision reason..."></textarea>
```

2. **Added help text** (Line 845):
```php
<small class="text-muted">Notes are required when rejecting, optional when accepting.</small>
```

3. **Changed buttons to `type="button"`** (Lines 851-859):
- Prevents default form submission
- Calls JavaScript function instead

4. **Added `submitReview()` JavaScript function** (Lines 2183-2214):
```javascript
function submitReview(action) {
    const form = document.getElementById('reviewActionForm');
    const notesTextarea = document.getElementById('reviewActionNotes');
    const actionType = document.getElementById('reviewActionType');

    // Set action type
    actionType.value = action;

    // Validate notes only when rejecting (send_to_user)
    if (action === 'send_to_user') {
        if (!notesTextarea.value.trim()) {
            alert('Admin notes are required when rejecting!');
            notesTextarea.focus();
            return false;
        }
    }

    // Show confirmation dialog
    let confirmMessage = '';
    if (action === 'send_to_ktt') {
        confirmMessage = 'Are you sure you want to Accept and send back to KTT?';
    } else {
        confirmMessage = 'Are you sure you want to Reject and return to User?';
    }

    if (confirm(confirmMessage)) {
        form.submit();
    }

    return false;
}
```

#### 2. `admin_review_rejection.php` (KTT Rejection Review Cards)

**Changes Made**:

1. **Removed `required` attribute from textarea** (Line 286):
```php
<textarea name="admin_notes" id="admin_notes_<?php echo $row['id']; ?>"
         placeholder="Enter notes or reason for decision..."></textarea>
```

2. **Added help text** (Line 288):
```php
<small class="text-muted">Notes are required when rejecting, optional when accepting.</small>
```

3. **Modified form onsubmit handlers** (Lines 292 & 301):
```php
// Accept form
<form method="POST" class="inline-form" onsubmit="return confirmAccept(this);">
    ...
</form>

// Reject form
<form method="POST" class="inline-form" onsubmit="return confirmReject(this);">
    ...
</form>
```

4. **Added two validation functions** (Lines 1058-1092):
```javascript
// Confirm accept action (notes optional)
function confirmAccept(form) {
    const card = form.closest('.approval-card');
    const textarea = card.querySelector('textarea[name="admin_notes"]');
    const hiddenInput = form.querySelector('input[name="admin_notes_value"]');

    // Sync textarea value to hidden input
    if (textarea && hiddenInput) {
        hiddenInput.value = textarea.value;
    }

    // Notes are optional for accept
    return confirm('Are you sure you want to accept and send back to KTT?');
}

// Confirm reject action (notes required)
function confirmReject(form) {
    const card = form.closest('.approval-card');
    const textarea = card.querySelector('textarea[name="admin_notes"]');
    const hiddenInput = form.querySelector('input[name="admin_notes_value"]');

    // Sync textarea value to hidden input
    if (textarea && hiddenInput) {
        hiddenInput.value = textarea.value;
    }

    // Validate textarea is not empty for reject
    if (!textarea.value.trim()) {
        alert('Admin notes are required when rejecting!');
        textarea.focus();
        return false;
    }

    return confirm('Are you sure you want to reject and return to User?');
}
```

## Validation Logic

### Accept Action (send_to_ktt):
- ✅ Notes field is **optional**
- ✅ Empty notes are allowed
- ✅ Form can be submitted without notes
- ✅ Only shows confirmation dialog

### Reject Action (send_to_user):
- ✅ Notes field is **required**
- ✅ Empty notes trigger alert
- ✅ Focus returns to textarea if empty
- ✅ Form cannot be submitted without notes
- ✅ Shows confirmation after validation passes

## User Experience

### Before Fix:
```
Admin clicks "Accept - Send to KTT"
  ↓
Browser validation: "Please fill out this field" ❌
  ↓
Admin forced to enter notes even when accepting
```

### After Fix:
```
Accept Path:
Admin clicks "Accept - Send to KTT"
  ↓
JavaScript: Notes optional ✓
  ↓
Confirmation dialog
  ↓
Submit (with or without notes)

Reject Path:
Admin clicks "Reject - Return to User"
  ↓
JavaScript: Validate notes ✓
  ↓
If empty: Alert "Admin notes are required when rejecting!"
If filled: Show confirmation dialog
  ↓
Submit (with notes)
```

## Testing Checklist

- [ ] **Accept with notes**: Should work
- [ ] **Accept without notes**: Should work (optional)
- [ ] **Reject with notes**: Should work
- [ ] **Reject without notes**: Should show alert and prevent submission
- [ ] Test on `appointments.php` rejection detail modal
- [ ] Test on `admin_review_rejection.php` card forms

## Benefits

1. ✅ **More flexible**: Admin can accept without mandatory notes
2. ✅ **Clear guidance**: Help text explains when notes are required
3. ✅ **Better UX**: Less friction when approving
4. ✅ **Data quality**: Still enforces notes when rejecting (important for user feedback)
5. ✅ **Consistent**: Same behavior across both admin review pages

## Notes

- Server-side validation still exists in PHP (lines 62-79 in appointments.php)
- This is client-side validation enhancement
- Backend will receive empty string for notes when accepting without notes
- Backend code handles empty notes gracefully (uses default "No notes" if empty)
