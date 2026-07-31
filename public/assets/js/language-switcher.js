// Local Language Switcher
// Tahap migrasi ringan: menghapus ketergantungan Google Translate

const STORAGE_KEY = 'language';
const SUPPORTED_LANGUAGES = ['id', 'en'];
const runtimeDefaults = {};
const missingKeyWarnings = new Set();

const translations = {
    en: {
        'dashboard': 'Dashboard',
        'request': 'Request',
        'assign-letter': 'Assign Letter',
        'reports': 'Reports',
        'settings': 'Settings',
        'logout': 'Logout',
        'competencies': 'Competencies',
        'certifications': 'Certifications',
        'supervision-areas': 'Supervision Areas',
        'manage-supervision-areas': 'Manage Supervision Areas',
        'manage-supervision-areas-subtitle': 'Add, edit, or manage supervision areas for operational supervisors',
        'add-new-area': 'Add New Area',
        'supervision-areas-list': 'Supervision Areas List',
        'area-name': 'Area Name',
        'area-code': 'Area Code',
        'description': 'Description',
        'usage': 'Usage',
        'information': 'Information',
        'supervision-areas-info': 'Supervision areas are used for Operational Supervisors. Areas marked as inactive will not appear in the form dropdown.',
        'no-description': 'No description',
        'employees': 'employees',
        'no-supervision-areas-yet': 'No supervision areas yet',
        'add-new-supervision-area': 'Add New Supervision Area',
        'edit-supervision-area': 'Edit Supervision Area',
        'supervision-area-full-name-hint': 'Full name of the supervision area',
        'supervision-area-code-hint': 'Short code or abbreviation for the area',
        'approval-ktt': 'Approval KTT',
        'competency-management': 'Competency Management',
        'certification-management': 'Certification Management',
        'manage-organizational-competency-data': 'Manage organizational competency data',
        'add-competency': 'Add Competency',
        'total-active-competencies': 'Total Active Competencies',
        'competency-list': 'Competency List',
        'type-label': 'Type',
        'no-competencies-yet': 'No competencies yet',
    'no-sub-competencies': 'No sub competencies',
    'add-first-competency': 'Add First Competency',
        'add-new-competency': 'Add New Competency',
        'edit-competency': 'Edit Competency',
        'competency-type-required': 'Competency Type',
        'sub-competencies': 'Sub Competencies',
        'add-one-or-more-sub-competencies': 'Add one or more sub competencies',
        'add-or-update-sub-competency-levels': 'Add or update sub competency levels',
        'add-another-level': 'Add Another Level',

        'welcome': 'Welcome to',
        'login-subtitle': 'Login below to get started.',
        'email-placeholder': 'E-mail Address',
        'password-placeholder': 'Your Password',
        'keep-logged-in': 'Keep me logged in',
        'login-button': 'Login',

        'welcome-user': 'Welcome',
        'manage-appointments': 'Manage and monitor all appointment letters easily',
        'employee-verification': 'Employee Verification',
        'certification-competency-list': 'Certification/Competency List',
        'manage-certification-competency-data': 'Manage certification and competency data',
        'add-certification': 'Add Certification',
        'total-active-certifications': 'Total Active Certifications',
        'no-certifications-yet': 'No certifications yet',
        'add-first-certification': 'Add First Certification',
        'add-new-certification': 'Add New Certification',
        'edit-certification': 'Edit Certification',
        'delete-confirmation': 'Delete Confirmation',
        'this-action-cannot-be-undone': 'This action cannot be undone.',
        'active': 'Active',
        'inactive': 'Inactive',
        'save': 'Save',
        'update': 'Update',
        'scope': 'Scope',
        'no-certificate-data': 'No certificate data',
        'add-employee-certificate': 'Add Employee Certificate',
        'certification-type': 'Certification Type',
        'validity-period-years': 'Validity Period (Years)',
        'upload-certificate-document': 'Upload Certificate Document (PDF/JPG/PNG, Max 5MB)',
        'supported-formats': 'Supported formats: PDF, JPG, PNG (Maximum 5MB)',
        'id-short': 'ID',
        'verify-employee-data': 'Verify Employee Data',
        'needs-review-admin': 'Needs Review Admin',
        'needs-review-ktt': 'Needs Review (Reject KTT)',
        'review-ktt-rejections': 'Review KTT Rejections',
        'review-ktt-rejections-subtitle': 'Review letters rejected by KTT and determine next action',
        'waiting-admin-review': 'Waiting for Admin Review',
        'waiting-admin-verification': 'Waiting for Admin Verification',
        'waiting-ktt-approval': 'Waiting for KTT Approval',
        'waiting-approval-title': 'Waiting for Approval',
        'waiting-approval-desc': 'This appointment is waiting for approval from the KTT',
        'admin-review-history': 'Admin Review History',
        'admin-verification': 'Admin Verification',
        'ktt-msm': 'KTT MSM:',
        'ktt-ttn': 'KTT TTN:',
        'number-short': 'No:',
        'document-information': 'Document Information',
        'employee-information': 'Employee Information',
        'competency-name': 'Competency Name',
        'data-certificate-verified': 'Data & Certificate Verified',
        'final-notes': 'Final Notes:',
        'appointment-approved-title': 'Appointment Approved',
        'appointment-approved-desc': 'This appointment has been approved by both KTTs on',
        'accept': 'Accept',
        'accepted': 'Accepted',
        'reject': 'Reject',
        'urgent': 'URGENT',
        'certificate-expiration': 'Certificate Expiration',
        'employees-expiring-certs': 'Employees with certificates expiring within <= 2 months',
        'view-certificate-details': 'View Certificate Details',
        'recent-appointments': 'Recent Appointment Letters History',
        'view-all': 'View All',
        'back': 'Back',
        'admin': 'Admin',
        'hide': 'Hide',
        'accept-send-to-ktt': 'Accept - Send to KTT',
        'accept-send-back-to-ktt': 'Accept and send back to KTT',
        'reject-return-to-user': 'Reject - Return to User',
        'reject-and-return-to-user': 'Reject and return to User',
        'confirm-accept-send-back-ktt': 'Are you sure you want to Accept and send back to KTT?',
        'confirm-reject-return-user': 'Are you sure you want to Reject and return to User?',
        'no-rejection-notes': 'No rejection notes',
        'no-rejection-notes-available': 'No rejection notes available',
        'table-not-found': 'Table not found!',
        'no-data-to-print': 'No data to print!',
        'no-data-to-export': 'No data to export!',
        'ktt-loading-data': 'Loading data...',
        'ktt-failed-load-data': 'Failed to load data',
        'ktt-error-prefix': 'An error occurred:',
        'accept-assign-letter': 'Accept Assign Letter',
        'reject-assign-letter': 'Reject Assign Letter',
        'additional-notes-optional': 'Additional notes (optional)',
        'notes-required-rejection-reason': 'Notes are required with rejection reason.',
        'rejection-reason-required': 'Rejection reason notes must be filled!',
        'no-data-for-company': 'No data for company:',
        'confirm-resubmit-to-ktt': 'Resubmit this appointment letter to KTT for review?',
        'must-have-one-certification': 'You must have at least one certification.',
        'confirm-remove-certification': 'Are you sure you want to remove this certification?',
        'certification': 'Certification',
        'select-sub-competency': '-- Select Sub Competency --',
        'approve': 'Approve',
        'accept': 'Accept',
        'reject': 'Reject',
        'ktt-rejection-reason': 'Rejection Reason:',
        'ktt-admin-notes': 'Admin Notes:',
        'ktt-assign-letter-data': 'Assign Letter Data',
        'ktt-identity-data': 'Identity Data',
        'full-name': 'Full Name',
        'not-specified': 'Not specified',
        'ktt-curriculum-vitae': 'Curriculum Vitae',
        'ktt-view-cv': 'View CV',
        'ktt-statement-letter': 'Statement Letter',
        'ktt-view-statement': 'View Statement',
        'ktt-verified-certs': 'Verified Certifications/Competencies',
        'verified': 'VERIFIED',
        'certificate-number': 'Certificate Number',
        'issuer': 'Issuer',
        'issue-date': 'Issue Date',
        'expiry-date': 'Expiry Date',
        'expired': 'EXPIRED',
        'ktt-view-certificate': 'View Certificate',
        'ktt-no-verified-certs': 'No verified certifications yet',
        'ktt-already-made-decision': 'You have already made a decision for this assign letter!',
        'ktt-assign-letter-successfully-approved': 'Assign letter successfully approved!',
        'ktt-approved-sent-admin-review': 'You have approved this assign letter. It has been sent to Admin for review.',
        'ktt-approved-assign-letter': 'You have approved this assign letter.',
        'ktt-approved-but-other-rejected-sent-admin-review': 'You have approved this assign letter, but another KTT has rejected it. It has been sent to Admin for review.',
        'ktt-rejected-sent-admin-review': 'You have rejected this assign letter. It has been sent to Admin for review.',
        'ktt-failed-approve-assign-letter': 'Failed to approve assign letter!',
        'ktt-failed-reject-assign-letter': 'Failed to reject assign letter!',
        'lifetime': 'Lifetime',
        'showing-data-from': 'Showing',

        'welcome-assign-letter': 'Welcome to the Assign Letter Toka System',
        'data-not-showing': 'Data Not Showing?',
        'data-not-showing-message': 'Try hard refresh with Ctrl+F5 or clear browser cache.',
        'rejected-data': 'Rejected Data!',
        'rejected': 'Rejected',
        'rejected-count-message-1': 'There are',
        'rejected-count-message-2': 'rejected appointment letters. Please check the details and make corrections.',
        'competency': 'Competency',
        'status': 'Status',
        'appointment-rejected-message': 'This appointment was rejected and needs to be revised before resubmission.',
        'last-updated': 'Last updated',
        'view-details': 'View Details',
        'all-employees-label': 'All employees:',
        'upload-correction': 'Upload Correction',
        'print': 'Print',
        'resubmit-to-ktt': 'Resubmit to KTT',
        'rejection-reason': 'Rejection Reason:',
        'from-admin-review-ktt-rejection': 'From Admin (KTT Rejection Review):',
        'from-admin': 'From Admin:',
        'from-ktt': 'From KTT:',
        'on': 'on',
        'id-badge-cannot-be-changed': 'ID BADGE cannot be changed',
        'id-badge-required': 'ID BADGE',
        'full-name-required': 'Full Name',
        'position-required': 'Position',
        'scope-of-work-required': 'Scope of Work',
        'competency-type-required': 'Competency Type',
        'supervision-area-required': 'Supervision Area',
        'company-required': 'Company',
        'employee-full-name-placeholder': 'Employee full name',
        'full-name-of-employee-placeholder': 'Full name of the employee',
        'position-example-placeholder': 'Example: Rigger, HSE Superintendent',
        'company-cannot-be-changed': 'Company cannot be changed',
        'contractor-company-name-placeholder': 'Contractor company name',
        'fix-rejected-data-reupload': 'Fix rejected data and re-upload for verification',
        'optional': '(Optional)',
        'optional-leave-blank-no-change': '(Optional - leave blank if no changes needed)',
        'optional-leave-blank-if-no-change': '(Optional - Leave blank if no changes)',
        'current-file': 'Current file:',
        'view-statement-letter': 'View Statement Letter',
        'wet-signature-pdf-instruction': 'Statement letter must be signed with wet signature (original) and scanned in PDF format',
        'remove-this-certification': 'Remove this certification',
        'certification-name-required': 'Certification Name',
        'certificate-number-required': 'Certificate Number',
        'issuer-required': 'Issuer',
        'issue-date-required': 'Issue Date',
        'validity-period-required': 'Validity Period',
        'expiry-date-required': 'Expiry Date',
        'upload-certificate-file-required': 'Upload Certificate File',
        'certificate-number-placeholder': 'Certificate number',
        'issuer-name-placeholder': 'Issuer name',
        'lifetime-certificate-example': 'Example: Lifetime Certificate',
        'no-expiry-reason': 'No Expiry Reason',
        'expiry-date-manual-edit-note': 'You can manually edit the expiry date if needed',
        'validity-years-hint': 'Enter in years, e.g.: 3 or 2.5 for 2 years 6 months',
        'click-or-drag-certificate-file': 'Click or drag certificate file (PDF, Max 5MB)',
        'click-or-drag-new-certificate-file': 'Click or drag new certificate file (PDF, Max 5MB)',
        'click-or-drag-new-cv-file': 'Click or drag new CV file (PDF, Max 5MB)',
        'click-or-drag-new-statement-letter-file': 'Click or drag new statement letter file (PDF, Max 5MB)',
        'badge-example-placeholder': 'Example: BADGE001',
        'auto-filled-from-account': 'Automatically filled from your account',
        'enter-certificate-type': 'Enter certificate type',
        'issuer-certification-body-name': 'Name of issuer/certification body',
        'explain-the-reason': 'Explain the reason...',
        'enter-certificate-number': 'Enter certificate number',
        'validity-years-example': 'Example: 1, 2, 3',
        'additional-notes-if-needed': 'Additional notes if needed',
        'enter-notes-or-reason': 'Enter notes or reason...',
        'notes-required-if-rejecting': 'Notes are required if rejecting',
        'unique-id-badge-hint': 'Unique ID for employee badge identification',
        'upload-cv-file': 'Upload file CV',
        'pdf-max-5mb': '(PDF, Max 5MB)',
        'upload-tt-mgt-frs-008d': 'Upload TT-MGT-FRS-008D',
        'save-submit-verification': 'Save & Submit for Verification',
        'upload-certificate-file': 'Upload Certificate File',
        'click-drag-certificate-file': 'Click or drag certificate file (PDF, Max 5MB)',
        'add-another-certification': 'Add Another Certification',
        'important-note': 'Important Note',
        'ktt-approval-assign-letter': 'Assignment Letter Approval',
        'assign-letter-approval': 'Assign Letter Approval',
        'success-rejected': 'Success Rejected!',
        'no-assign-letters-pending-approval': 'No assign letters pending your approval',
        'all-letters-processed-info': 'All letters have been processed by you or are being processed by other KTT',
        'review-assign-letter': 'Review Assign Letter',
        'review': 'Review',
        'important-statement-letter': 'Important - Statement Letter:',
        'statement-letter-original-signature-note': 'The statement letter must be signed with an <strong>original wet signature</strong> by the concerned party, then scanned in PDF format.',
        'download-statement-letter-template': 'Download Statement Letter Template',
        'certification-number-1': 'Certification #1',
        'certification-name': 'Certification Name',
        'select-certification': '-- Select Certification --',
        'upload-new-certificate-file': 'Upload New Certificate File',
        'important-information': 'Important Information:',
        'resubmit-file-upload-optional-info': 'File uploads are OPTIONAL. You do not need to re-upload CV, signature, or certificate files if existing data is correct. Existing files will continue to be used.',
        'reupload-only-if': 'Re-upload only if:',
        'resubmit-reupload-condition': 'Admin specified in rejection notes that certain files need to be corrected/replaced.',
        'after-resubmit-status-pending-note': 'After uploading corrections, the status will return to "Pending" and await re-verification from Admin.',
        'appointment-letter-number-will-remain': 'Appointment Letter Number',
        'will-remain-the-same': 'will remain the same.',
        'attendance-participant': 'Attendance/Participant',
        'competent': 'Competent',
        'training': 'Training',
        'other-type': 'Other Type',
        'no-expiry': 'No Expiry',
        'upload-certificate-file-pdf': 'Upload certificate file (PDF, Max 5MB)',
        'important-notes': 'Important Notes',
        'view-all-rejected-data': 'View All Rejected Data',
        'employee-statistics': 'Employee Statistics',
        'all-employees': 'All Employees'
        ,
        'confirm-submit-to-ktt-approval': 'Submit to KTT for approval?',
        'admin-notes-required': 'Admin notes are required!',
        'are-you-sure-want-to': 'Are you sure you want to',
        'no-certifications-recorded': 'No certifications recorded.',
        'admin-review': 'Admin Review:',
        'date-label': 'Date:',
        'failed-load-appointment-details': 'Failed to load appointment details',
        'error-loading-appointment-details': 'Error loading appointment details',
        'admin-notes-required-when-rejecting': 'Admin notes are required when rejecting!',
        'verification-notes-required-rejecting': 'Verification notes are required when rejecting data!',
        'confirm-reject-workforce-data': 'Reject this workforce data?',
        'confirm-verify-and-create-appointment': 'Verify employee and all certifications, then automatically create appointment letter?',
        'modify-letter': 'Modify Letter',
        'submit-to-ktt': 'Submit to KTT',
        'print-appointment-letter': 'Print Appointment Letter',
        'view-rejection-details': 'View Rejection Details',
        'appointment-letter-content-placeholder': 'Enter appointment letter content...\n\nExample:\nBased on the Decision of the Minister of ESDM of the Republic of Indonesia Number ... regarding ...\n\nWe hereby appoint:\nName: ...\nPosition: ...\nCompetency: ...\n\nTo carry out duties as ... in the area ...\n\nThis appointment letter is made to be used accordingly.',
        'additional-notes-placeholder': 'Additional notes...',
        'enter-notes-or-reason-decision': 'Enter notes or reason for decision...',
        'open': 'Open',
        'new': 'NEW',
        'resubmit': 'Resubmit',
        'export-not-configured-table': 'Export not configured for this table.',
        'add-verification-notes-required-reject': 'Add verification notes (required if rejected)',
        'filter-placeholder': 'Filter...',
        'back-to-top': 'Back to Top',
        'rejection-date-label': 'Rejection date:',
        'sent-back-to-ktt': 'Sent back to KTT',
        'returned-to-user': 'Returned to User',
        'accepted-assign-letters': 'Accepted Assign Letters',
        'accepted-requests': 'Accepted Requests',
        'action': 'Action',
        'activate': 'Activate',
        'deactivate': 'Deactivate',
        'actions': 'Actions',
        'admin-action': 'Admin Action',
        'active-filter': 'Active Filter:',
        'add-contractor-workforce': 'Add Contractor Workforce Data',
        'al-accepted': 'AL Accepted',
        'all-letters': 'All Letters',
        'all-assign-letter': 'All Assign Letter',
        'al-rejected': 'AL Rejected',
        'appointment-letter-list': 'Appointment Letter List',
        'appointment-letter-management': 'Appointment Letter Management',
        'assign-letter-report': 'Assign Letter Report',
        'assign-letters': 'Assign Letters',
        'all-requests-section': 'All Requests',
        'all-statuses': 'Select Status',
        'request-date': 'Request Date',
        'verified-date': 'Verification Date',
        'brief-description-supervision-area': 'Brief description of this supervision area',
        'certificate-skill-example': 'Example: Skill Certificate',
        'certifications-competencies': 'Certifications/Competencies',
        'clear-filter': 'Clear Filter',
        'company': 'Company',
        'competency-name-example-mining-ops': 'e.g., Mining Operational Supervision',
        'competency-type': 'Competency Type',
        'technical-supervisor': 'Technical Supervisor',
        'technical-personnel': 'Technical Personnel',
        'operational-supervisor': 'Operational Supervisor',
        'complete-workforce-list': 'Complete Workforce List',
        'confirm-change-status': 'Are you sure you want to change the status?',
        'confirm-delete-area-cannot-undo': 'Are you sure you want to delete this area? This action cannot be undone!',
        'confirm-delete-certification': 'Are you sure you want to delete this certification?',
        'confirm-delete-competency': 'Are you sure you want to delete this competency?',
        'contractor-workforce-data': 'Contractor Workforce Data',
        'data-rejected': 'Rejected Data!',
        'delete': 'Delete',
        'describe-this-competency': 'Describe this competency...',
        'displaying-employees-status': 'Displaying employees with status:',
        'displaying-letters-status': 'Displaying letters with status:',
        'draft': 'Draft',
        'edit': 'Edit',
        'employee': 'Employee',
        'employee-full-name': 'Employee full name',
        'enter-current-password': 'Enter current password',
        'enter-new-password-min-6': 'Enter new password (min. 6 characters)',
        'error': 'Error!',
        'example-badge': 'Example: BADGE001',
        'example-position': 'Example: Rigger, HSE Superintendent',
        'id-badge': 'ID BADGE',
        'identity-competency-data': 'Identity & Competency Data',
        'identity-data': 'Identity Data',
        'jump-to-section': 'Jump to Section:',
        'leadership-example-placeholder': 'Example: Leadership',
        'letter-number': 'Letter Number',
        'manage-appointment-letters': 'Manage creation and submission of expertise appointment letters',
        'manage-verify-workforce': 'Manage and verify contractor workforce data',
        'name': 'Name',
        'needs-review': 'Needs Review',
        'new-request': 'New Request',
        'no-workforce-data': 'No workforce data yet',
        'overall-statistics': 'Overall Statistics',
        'pending': 'Pending',
        'please-select-competency-type-first': 'Please select competency type first',
        'position': 'Position',
        'target-position': 'Target Position',
        'address': 'Address',
        'rejected-employees-message-1': 'There are',
        'rejected-employees-message-2': 'rejected employee data that needs correction. Click the button',
        'rejected-employees-message-3': 'on rejected rows to upload corrections.',
        'remove-filter': 'Remove Filter',
        'repeat-new-password': 'Repeat new password',
        'report-summary': 'Summary and details of assign letter processing results',
        'assign-letters-processed': 'Assign Letters Processed',
        'export-pdf-report': 'Export PDF Report',
        'export-to-excel': 'Export to Excel',
        'showing-all-data': 'Showing all data',
        'view-notes': 'View Notes',
        'details': 'Details',
        'certificate-expiration-2-months': 'Certificate Expiration (<=2 Months)',
        'expiring-certs-renew-immediately': 'The following is a list of employees with certificates expiring within <=2 months. Please renew certificates immediately.',
        'days-left': 'Days Left',
        'days': 'days',
        'very-urgent': 'Very Urgent',
        'warning': 'Warning',
        'expiring-soon': 'Expiring soon',
        'no-expiration-date': 'No expiration date',
        'assign-letter-no-label': 'Assign Letter No.:',
        'req-accepted': 'Req. Accepted',
        'req-rejected': 'Req. Rejected',
        'requests': 'Requests',
        'rejected-requests': 'Rejected Requests',
        'scope-of-work': 'Scope of Work',
        'select-competency': '-- Select Competency --',
        'select-competency-type': '-- Select Competency Type --',
        'select-scope-of-work': '-- Select Scope of Work --',
        'sub-competency': 'Sub Competency',
        'sub-competency-example-industrial-hygiene': 'e.g., Junior Industrial Hygiene Expert',
        'success': 'Success!',
        'supervision-area-code-example': 'Example: MSM',
        'supervision-area-company-example': 'Example: PT Meares Soputan Mining (MSM)',
        'total-employees': 'Total Employees',
        'total-letters': 'Total Letters',
        'type-or-select-competency': 'Type or select competency',
        'unique-id-badge': 'Unique ID for employee badge identification',
        'verified-by': 'Verified By',
        'waiting': 'Waiting',
        'waiting-approval': 'Waiting Approval',
        'filter-status-label': 'Filter Status:',
        'assign-letter-list': 'Assign Letter List',
        'employee-list': 'Employee List',
        'complete-employee-list': 'Complete Employee List',
        'certification-list': 'Certification List',
        'letter-no': 'Letter No.',
        'appointment-letter': 'Appointment Letter',
        'approval-history': 'Approval History',
        'step': 'Step',
        'name-username': 'Name / Username',
        'certificates-about-to-expire': 'Certificates About to Expire',
        'history-assign-letter': 'History Assign Letter',
        'registration-no': 'Registration No.',
        'employee': 'Employee',
        'approval': 'Approval',
        'add-new-request-employee': 'Add New Request Employee',
        'upload-employee-correction': 'Upload Employee Correction',
        'upload-correction-description': 'Fix rejected data and re-upload for verification',
        'appointment-letter-details': 'Appointment Letter Details',
        'letter-information': 'Letter Information',
        'approval-information': 'Approval Information',
        'created-at': 'Created At',
        'company-contractor': 'Company / Contractor',
        'work-scope': 'Work Scope',
        'no-time-limit': 'No time limit',
        'select-company': '-- Select Company --',
        'rejection-reason': 'Rejection Reason',
        'from-admin': 'From Admin',
        'from-ktt': 'From KTT',
        'rejected-by': 'Rejected by',
        'on-date': 'on',
        'id-badge': 'ID BADGE',
        'id-badge-cannot-change': 'ID BADGE cannot be changed',
        'back': 'Back',
        'select-supervision-area': '-- Select Supervision Area --',
        'click-drag-cv-file': 'Click or drag CV file (PDF, Max 5MB)',
        'click-drag-statement-letter': 'Click or drag Statement Letter (PDF, Max 5MB)',
        'certification-competency': 'Certification/Competency',
        'certificate-type': 'Certificate Type',
        'select-type': '-- Select Type --',
        'certificate-no': 'Certificate No.',
        'validity-period': 'Validity Period',
        'upload-cv-optional-no-change': 'Upload CV <span class="text-muted">(Optional - leave empty if no changes needed)</span>',
        'click-drag-new-cv-file': 'Click or drag new CV file (PDF, Max 5MB)',
        'upload-signature-optional': 'Upload Signature <span class="text-muted">(Optional)</span>',
        'click-drag-new-signature-file': 'Click or drag new signature file (PNG/JPG, Max 2MB)',
        'reason-for-no-expiry-optional': 'Reason for No Expiry <span class="text-muted">(Optional)</span>',
        'upload-new-certificate-file-optional': 'Upload New Certificate File <span class="text-muted">(Optional - Leave empty if no changes needed)</span>',
        'click-drag-new-certificate-file': 'Click or drag new certificate file (PDF, Max 5MB)',
        'appointment-letter-reports': 'Appointment Letter Reports',
        'summary': 'Summary',
        'company-summary': 'Company Summary',
        'detail-assign-letter-accepted': 'Detail Assign Letter Accepted',
        'work-scope-label': 'Work Scope:',
        'all-scopes': '-- All Scopes --',
        'supervision-area-label': 'Supervision Area:',
        'all-areas': '-- All Areas --',
        'assign-letter-no': 'Assign Letter No.',
        'detail-assign-letter-rejected': 'Detail Assign Letter Rejected',
        'rejected-date': 'Rejected Date',
        'assign-letter-rejection-details': 'Assign Letter Rejection Details',
        'letter-number-label': 'Letter Number:',
        'employee-name-label': 'Employee Name:',
        'rejection-notes-from-ktt': 'Rejection Notes from KTT',
        'assign-letters-accept-report': 'Assign Letters Accept Report',
        'printed-on': 'Printed on',
        'time': 'Time',
        'total-accepted-letters': 'Total Accepted Letters',
        'printed-from-system': 'This document is printed from the Competency Appointment Letter System',
        'appointment-letter-report': 'Appointment Letter Report',
        'export-date': 'Export Date',
        'scope-filter': 'Scope Filter',
        'total-data': 'Total Data',
        'employee-statistics': 'Employee Statistics',
        'all-employees': 'All Employees',
        'all-requests': 'All Request',
        'waiting-reviewer': 'Waiting Reviewer',
        'appointment-letter-statistics': 'Appointment Letter Statistics',
        'all-letters': 'All Letters',
        'accepted-ktt': 'Accepted KTT',
        'rejected-ktt': 'Rejected KTT',
        'waiting-ktt': 'Waiting KTT',
        'rejected-by-ktt': 'Rejected by KTT',
        'no-appointment-letter-data': 'No appointment letter data',
        'no-letters-waiting-admin-review': 'No letters waiting for admin review',
        'no-admin-review-yet': 'No admin review has been done yet',
        'no-assign-letters': 'No assign letters',
        'view-all-employees': 'View All Employees',
        'view-all-letters': 'View All Letters',
        'basic-information': 'Basic Information',
        'competency-information': 'Competency Information',
        'status-verification': 'Status & Verification',
        'view-cv': 'View CV',
        'verification-notes': 'Verification Notes:',
        'final-data-verification': 'Final Data Verification',
        'certificate-has-expired': 'Certificate has expired!',
        'no-certifications-uploaded-yet': 'No certifications uploaded yet',
        'no-expiry-date': 'No expiry date',
        'no-appointment-letters-available': 'No appointment letters available',
        'no-employee-data': 'No employee data',
        'back-to-dashboard': 'Back to Dashboard',
        'welcome-competency-appointment-letter-system': 'Welcome to the competency appointment letter system',
        'expiring-certificate-desc': 'Employees with certificates expiring within ≤ 2 months',
        'expiring-certificate-message': 'There are employees with certificates expiring within ≤ 2 months. Please check and update their certificates.',
        'expiring-certificate-message-suffix': 'employees with certificates expiring within ≤ 2 months. Please check and update their certificates.',
        'certificates-expiring-soon': 'Certificates Expiring Soon!',
        'there-are': 'There are',
        'total-processed': 'Total Processed',
        'correct-rejected-data-and-reupload': 'Correct the rejected data and re-upload for verification',
        'rejected-data': 'Rejected Data!',
        'there-is-rejected-data': 'There is Rejected Data!',
        'rejected-user-employee-message-1': 'There are',
        'rejected-user-employee-message-2': 'employee data that have been rejected and need to be corrected. Please click the "Resubmit" button to resubmit the corrected data.',
        'rejected-employee-data-message': 'There are rejected employee data that need to be corrected. Please click the "Upload Correction" button to resubmit the corrected data.',
        'rejected-employee-data-suffix': 'rejected employee data that need to be corrected. Please click the "Upload Correction" button to resubmit the corrected data.',
        'rejection-reason': 'Rejection Reason:',
        'from-admin': 'From Admin:',
        'on-date': 'on',
        'appointment-rejected': 'Appointment Rejected',
        'this-appointment-was-rejected': 'This appointment was rejected',
        'after-employee-data-added-note': 'After the employee data is added, the status will be "Pending" and awaiting verification from Admin before an Appointment Letter can be created.',
        'cancel': 'Cancel',
        'appointment-letters': 'Appointment Letters',
        'pending-your-approval': 'Pending Your Approval',
        'letters': 'Letters',
        'resubmitted': 'Resubmitted',
        'you-rejected-this': 'You Rejected This',
        'previous-rejection': 'Previous Rejection',
        'previous-rejection-details': 'Previous Rejection Details',
        'reviewed-by': 'Reviewed By',
        'by': 'by',
        'valid': 'Valid',
        'until-short': 'to',
        'appointment-date': 'Appointment Date',
        'valid-from': 'Valid From',
        'expires': 'Expires',
        'scope-of-work-msm': 'PT MSM',
        'scope-of-work-ttn': 'PT TTN',
        'competency-type-operational-supervisor': 'Operational Supervisor',
        'competency-type-technical-supervisor': 'Technical Supervisor',
        'competency-type-technical-personnel': 'Technical Personnel',
        'iso-9001-example': 'Example: ISO 9001',
        'national-certification-board-example': 'Example: National Certification Board',
        'showing-all-data-all-companies': 'Showing all data from all companies',
        'all-data': 'All Data',
        'data': 'data',
        'approved': 'Approved',
        'approved-by': 'Approved By',
        'certificate-name': 'Certificate Name',
        'close': 'Close',
        'date': 'Date',
        'department': 'Department',
        'document': 'Document',
        'effective-date': 'Effective Date',
        'expiry': 'Expiry',
        'no-appointment-letter-data-processed': 'No appointment letter data has been processed yet',
        'notes': 'Notes',
        'rejected-by': 'Rejected By',
        'rejection-notes': 'Rejection Notes',
        'review-date': 'Review Date',
        'supervision-area': 'Supervision Area',
        'total': 'Total',
        'upload-cv': 'Upload CV',
        'upload-statement-letter': 'Upload Statement Letter',
        'view': 'View',
        'employee-name': 'Employee Name',
        'employee-code': 'Employee Code',
        'years': 'Years'
    },
    id: {
        'dashboard': 'Menu Utama',
        'request': 'Pengajuan',
        'assign-letter': 'Surat Penunjukan',
        'reports': 'Laporan',
        'settings': 'Pengaturan',
        'logout': 'Keluar',
        'competencies': 'Kompetensi',
        'certifications': 'Sertifikasi',
        'supervision-areas': 'Area Pengawasan',
        'manage-supervision-areas': 'Kelola Area Pengawasan',
        'manage-supervision-areas-subtitle': 'Tambahkan, ubah, atau kelola area pengawasan untuk pengawas operasional',
        'add-new-area': 'Tambah Area Baru',
        'supervision-areas-list': 'Daftar Area Pengawasan',
        'area-name': 'Nama Area',
        'area-code': 'Kode Area',
        'description': 'Deskripsi',
        'usage': 'Penggunaan',
        'information': 'Informasi',
        'supervision-areas-info': 'Area pengawasan digunakan untuk Pengawas Operasional. Area yang ditandai nonaktif tidak akan muncul pada dropdown form.',
        'no-description': 'Tidak ada deskripsi',
        'employees': 'karyawan',
        'inactive': 'Nonaktif',
        'no-supervision-areas-yet': 'Belum ada area pengawasan',
        'add-new-supervision-area': 'Tambah Area Pengawasan Baru',
        'edit-supervision-area': 'Ubah Area Pengawasan',
        'supervision-area-full-name-hint': 'Nama lengkap area pengawasan',
        'supervision-area-code-hint': 'Kode singkat atau singkatan area',
        'approval-ktt': 'Persetujuan KTT',
        'competency-management': 'Manajemen Kompetensi',
        'certification-management': 'Manajemen Sertifikasi',
        'manage-organizational-competency-data': 'Kelola data kompetensi organisasi',
        'add-competency': 'Tambah Kompetensi',
        'total-active-competencies': 'Total Kompetensi Aktif',
        'competency-list': 'Daftar Kompetensi',
        'type-label': 'Tipe',
        'no-competencies-yet': 'Belum ada kompetensi',
        'no-sub-competencies': 'Tidak ada sub kompetensi',
        'add-first-competency': 'Tambah Kompetensi Pertama',
        'add-new-competency': 'Tambah Kompetensi Baru',
        'edit-competency': 'Ubah Kompetensi',
        'competency-type-required': 'Jenis Kompetensi',
        'sub-competencies': 'Sub Kompetensi',
        'add-one-or-more-sub-competencies': 'Tambah satu atau lebih sub kompetensi',
        'add-another-level': 'Tambah Level Lain',
    'remove': 'Remove',
    'remove': 'Hapus',

        'welcome': 'Selamat Datang di',
        'login-subtitle': 'Silakan login untuk memulai.',
        'email-placeholder': 'Alamat Email',
        'password-placeholder': 'Kata Sandi Anda',
        'keep-logged-in': 'Tetap login',
        'login-button': 'Masuk',

        'welcome-user': 'Selamat Datang',
        'manage-appointments': 'Kelola dan pantau semua surat penunjukan dengan mudah',
        'employee-verification': 'Verifikasi Karyawan',
        'certification-competency-list': 'Daftar Sertifikasi/Kompetensi',
        'manage-certification-competency-data': 'Kelola data sertifikasi dan kompetensi',
        'add-certification': 'Tambah Sertifikasi',
        'total-active-certifications': 'Total Sertifikasi Aktif',
        'no-certifications-yet': 'Belum ada sertifikasi',
        'add-first-certification': 'Tambah Sertifikasi Pertama',
        'add-new-certification': 'Tambah Sertifikasi Baru',
        'edit-certification': 'Ubah Sertifikasi',
        'delete-confirmation': 'Konfirmasi Hapus',
        'this-action-cannot-be-undone': 'Tindakan ini tidak dapat dibatalkan.',
        'active': 'Aktif',
        'save': 'Simpan',
        'update': 'Perbarui',
        'scope': 'Ruang Lingkup',
        'no-certificate-data': 'Tidak ada data sertifikat',
        'add-employee-certificate': 'Tambah Sertifikat Karyawan',
        'certification-type': 'Jenis Sertifikasi',
        'validity-period-years': 'Masa Berlaku (Tahun)',
        'upload-certificate-document': 'Unggah Dokumen Sertifikat (PDF/JPG/PNG, Maks 5MB)',
        'supported-formats': 'Format didukung: PDF, JPG, PNG (Maksimal 5MB)',
        'id-short': 'ID',
        'verify-employee-data': 'Verifikasi Data Karyawan',
        'needs-review-admin': 'Perlu Review Admin',
        'needs-review-ktt': 'Perlu Review (Reject KTT)',
        'review-ktt-rejections': 'Tinjau Penolakan KTT',
        'review-ktt-rejections-subtitle': 'Tinjau surat yang ditolak KTT dan tentukan tindakan berikutnya',
        'waiting-admin-review': 'Menunggu Tinjauan Admin',
        'waiting-admin-verification': 'Menunggu Verifikasi Admin',
        'waiting-ktt-approval': 'Menunggu Persetujuan KTT',
        'waiting-approval-title': 'Menunggu Persetujuan',
        'waiting-approval-desc': 'Surat penunjukan ini menunggu persetujuan dari KTT',
        'admin-review-history': 'Riwayat Tinjauan Admin',
        'admin-verification': 'Verifikasi Admin',
        'ktt-msm': 'KTT MSM:',
        'ktt-ttn': 'KTT TTN:',
        'number-short': 'No:',
        'document-information': 'Informasi Dokumen',
        'employee-information': 'Informasi Karyawan',
        'competency-name': 'Nama Kompetensi',
        'data-certificate-verified': 'Data & Sertifikat Terverifikasi',
        'final-notes': 'Catatan Akhir:',
        'appointment-approved-title': 'Surat Penunjukan Disetujui',
        'appointment-approved-desc': 'Surat penunjukan ini telah disetujui oleh kedua KTT pada',
        'accept': 'Disetujui',
        'accepted': 'Disetujui',
        'reject': 'Tidak disetujui',
        'urgent': 'MENDESAK',
        'certificate-expiration': 'Masa Berlaku Sertifikat',
        'employees-expiring-certs': 'Karyawan dengan sertifikat yang akan habis dalam <= 2 bulan',
        'view-certificate-details': 'Lihat Detail Sertifikat',
        'recent-appointments': 'Riwayat Surat Penunjukan Terbaru',
        'view-all': 'Lihat Semua',
        'back': 'Kembali',
        'admin': 'Admin',
        'hide': 'Sembunyikan',
        'accept-send-to-ktt': 'Disetujui - Kirim ke KTT',
        'accept-send-back-to-ktt': 'Disetujui dan kirim kembali ke KTT',
        'reject-return-to-user': 'Tidak Disetujui - Kembalikan ke User',
        'reject-and-return-to-user': 'Tidak Disetujui dan kembalikan ke User',
        'confirm-accept-send-back-ktt': 'Apakah Anda yakin ingin menyetujui dan mengirim kembali ke KTT?',
        'confirm-reject-return-user': 'Apakah Anda yakin ingin menolak dan mengembalikan ke User?',
        'no-rejection-notes': 'Tidak ada catatan penolakan',
        'no-rejection-notes-available': 'Tidak ada catatan penolakan',
        'table-not-found': 'Tabel tidak ditemukan!',
        'no-data-to-print': 'Tidak ada data untuk dicetak!',
        'no-data-to-export': 'Tidak ada data untuk diekspor!',
            'reject': 'Tidak disetujui',
        'ktt-failed-load-data': 'Gagal memuat data',
        'ktt-error-prefix': 'Terjadi kesalahan:',
        'accept-assign-letter': 'Disetujui Surat Penunjukan',
        'reject-assign-letter': 'Tidak Disetujui Surat Penunjukan',
        'additional-notes-optional': 'Catatan tambahan (opsional)',
        'notes-required-rejection-reason': 'Catatan wajib diisi saat menolak.',
        'rejection-reason-required': 'Catatan alasan tidak disetujui harus diisi!',
        'no-data-for-company': 'Tidak ada data untuk perusahaan:',
        'confirm-resubmit-to-ktt': 'Ajukan ulang surat penunjukan ini ke KTT untuk ditinjau?',
        'must-have-one-certification': 'Anda harus memiliki setidaknya satu sertifikasi.',
        'confirm-remove-certification': 'Apakah Anda yakin ingin menghapus sertifikasi ini?',
        'certification': 'Sertifikasi',
        'select-sub-competency': '-- Pilih Sub Competency --',
        'approve': 'Disetujui',
        'accept': 'Disetujui',
        'reject': 'Tidak disetujui',
        'ktt-rejection-reason': 'Alasan Tidak Disetujui:',
        'ktt-admin-notes': 'Catatan Admin:',
        'ktt-assign-letter-data': 'Data Surat Penunjukan',
        'ktt-identity-data': 'Data Identitas',
        'full-name': 'Nama Lengkap',
        'not-specified': 'Tidak ditentukan',
        'ktt-curriculum-vitae': 'Curriculum Vitae',
        'ktt-view-cv': 'Lihat CV',
        'ktt-statement-letter': 'Surat Pernyataan',
        'ktt-view-statement': 'Lihat Surat Pernyataan',
        'ktt-verified-certs': 'Sertifikasi/Kompetensi Terverifikasi',
        'verified': 'TERVERIFIKASI',
        'certificate-number': 'Nomor Sertifikat',
        'issuer': 'Penerbit',
        'issue-date': 'Tanggal Terbit',
        'expiry-date': 'Tanggal Kadaluwarsa',
        'expired': 'Kadaluwarsa',
        'ktt-view-certificate': 'Lihat Sertifikat',
        'ktt-no-verified-certs': 'Belum ada sertifikasi terverifikasi',
        'lifetime': 'Seumur Hidup',
        'showing-data-from': 'Menampilkan',

        'welcome-assign-letter': 'Selamat datang di sistem Assign Letter Toka',
        'data-not-showing': 'Data Tidak Muncul?',
        'data-not-showing-message': 'Coba hard refresh dengan Ctrl+F5 atau bersihkan cache browser.',
        'rejected-data': 'Data Tidak Disetujui!',
        'rejected-count-message-1': 'Terdapat',
        'rejected-count-message-2': 'surat penunjukan yang tidak disetujui. Silakan cek detail dan lakukan perbaikan.',
        'competency': 'Kompetensi',
        'status': 'Status',
        'appointment-rejected-message': 'Surat penunjukan ini tidak disetujui dan perlu direvisi sebelum diajukan kembali.',
        'last-updated': 'Terakhir diperbarui',
        'view-details': 'Lihat Detail',
        'upload-correction': 'Unggah Perbaikan',
        'print': 'Cetak',
        'resubmit-to-ktt': 'Ajukan Ulang ke KTT',
        'id-badge-cannot-be-changed': 'ID BADGE tidak dapat diubah',
        'employee-full-name-placeholder': 'Nama lengkap karyawan',
        'full-name-of-employee-placeholder': 'Nama lengkap karyawan',
        'position-example-placeholder': 'Contoh: Rigger, HSE Superintendent',
        'company-cannot-be-changed': 'Perusahaan tidak dapat diubah',
        'contractor-company-name-placeholder': 'Nama perusahaan kontraktor',
        'wet-signature-pdf-instruction': 'Surat pernyataan harus ditandatangani basah (asli) dan dipindai dalam format PDF',
        'remove-this-certification': 'Hapus sertifikasi ini',
        'certificate-number-placeholder': 'Nomor sertifikat',
        'issuer-name-placeholder': 'Nama penerbit',
        'lifetime-certificate-example': 'Contoh: Sertifikat Seumur Hidup',
        'badge-example-placeholder': 'Contoh: BADGE001',
        'auto-filled-from-account': 'Otomatis terisi dari akun Anda',
        'enter-certificate-type': 'Masukkan jenis sertifikat',
        'issuer-certification-body-name': 'Nama penerbit/lembaga sertifikasi',
        'explain-the-reason': 'Jelaskan alasannya...',
        'enter-certificate-number': 'Masukkan nomor sertifikat',
        'validity-years-example': 'Contoh: 1, 2, 3',
        'additional-notes-if-needed': 'Catatan tambahan jika diperlukan',
        'enter-notes-or-reason': 'Masukkan catatan atau alasan...',
        'notes-required-if-rejecting': 'Catatan wajib diisi jika menolak',
        'unique-id-badge-hint': 'ID unik untuk identifikasi badge karyawan',
        'upload-cv-file': 'Unggah file CV',
        'pdf-max-5mb': '(PDF, Maks 5MB)',
        'upload-tt-mgt-frs-008d': 'Unggah TT-MGT-FRS-008D',
        'save-submit-verification': 'Simpan & Ajukan untuk Verifikasi',
        'upload-certificate-file': 'Unggah File Sertifikat',
        'click-drag-certificate-file': 'Klik atau seret file sertifikat (PDF, Maks 5MB)',
        'add-another-certification': 'Tambah Sertifikasi Lain',
        'important-note': 'Catatan Penting',
        'ktt-approval-assign-letter': 'Persetujuan Surat Penugasan',
        'assign-letter-approval': 'Persetujuan Surat Penugasan',
        'success-rejected': 'Tidak disetujui!',
        'no-assign-letters-pending-approval': 'Tidak ada surat penunjukan yang menunggu persetujuan Anda',
        'all-letters-processed-info': 'Semua surat telah Anda proses atau sedang diproses oleh KTT lain',
        'review-assign-letter': 'Tinjau Surat Penugasan',
        'review': 'Tinjau',
        'important-statement-letter': 'Penting - Surat Pernyataan:',
        'download-statement-letter-template': 'Unduh Template Surat Pernyataan',
        'certification-number-1': 'Sertifikasi #1',
        'certification-name': 'Nama Sertifikasi',
        'select-certification': '-- Pilih Sertifikasi --',
        'attendance-participant': 'Kehadiran/Peserta',
        'competent': 'Kompeten',
        'training': 'Pelatihan',
        'other-type': 'Tipe Lainnya',
        'no-expiry': 'Tanpa Kedaluwarsa',
        'upload-certificate-file-pdf': 'Unggah file sertifikat (PDF, Maks 5MB)',
        'important-notes': 'Catatan Penting',
        'view-all-rejected-data': 'Lihat Semua Data Ditolak',
        'employee-statistics': 'Statistik Karyawan',
        'all-employees': 'Seluruh Karyawan'
        ,
        'confirm-submit-to-ktt-approval': 'Kirim ke KTT untuk persetujuan?',
        'admin-notes-required': 'Catatan admin wajib diisi!',
        'are-you-sure-want-to': 'Apakah Anda yakin ingin',
        'no-certifications-recorded': 'Tidak ada sertifikasi tercatat.',
        'admin-review': 'Tinjauan Admin:',
        'date-label': 'Tanggal:',
        'failed-load-appointment-details': 'Gagal memuat detail surat penunjukan',
        'error-loading-appointment-details': 'Terjadi kesalahan saat memuat detail surat penunjukan',
        'admin-notes-required-when-rejecting': 'Catatan admin wajib diisi saat menolak!',
        'verification-notes-required-rejecting': 'Catatan verifikasi wajib diisi saat menolak data!',
        'confirm-reject-workforce-data': 'Tolak data tenaga kerja ini?',
        'confirm-verify-and-create-appointment': 'Verifikasi karyawan dan semua sertifikasi, lalu otomatis buat surat penunjukan?',
        'modify-letter': 'Ubah Surat',
        'submit-to-ktt': 'Kirim ke KTT',
        'print-appointment-letter': 'Cetak Surat Penunjukan',
        'view-rejection-details': 'Lihat Detail Penolakan',
        'appointment-letter-content-placeholder': 'Masukkan isi surat penunjukan...\n\nContoh:\nBerdasarkan Keputusan Menteri ESDM Republik Indonesia Nomor ... tentang ...\n\nDengan ini menunjuk:\nNama: ...\nJabatan: ...\nKompetensi: ...\n\nUntuk melaksanakan tugas sebagai ... di area ...\n\nSurat penunjukan ini dibuat untuk dipergunakan sebagaimana mestinya.',
        'additional-notes-placeholder': 'Catatan tambahan...',
        'enter-notes-or-reason-decision': 'Masukkan catatan atau alasan keputusan...',
        'open': 'Buka',
        'resubmit': 'Ajukan Ulang',
        'export-not-configured-table': 'Ekspor belum dikonfigurasi untuk tabel ini.',
        'add-verification-notes-required-reject': 'Tambahkan catatan verifikasi (wajib jika ditolak)',
        'filter-placeholder': 'Filter...',
        'back-to-top': 'Kembali ke Atas',
        'rejection-date-label': 'Tanggal penolakan:',
        'sent-back-to-ktt': 'Dikirim kembali ke KTT',
        'returned-to-user': 'Dikembalikan ke User',
        'accepted-assign-letters': 'Surat Penunjukan Disetujui',
        'accepted-requests': 'Pengajuan Disetujui',
        'action': 'Aksi',
        'activate': 'Aktifkan',
        'deactivate': 'Nonaktifkan',
        'actions': 'Aksi',
        'admin-action': 'Aksi Admin',
        'active-filter': 'Filter Aktif:',
        'add-contractor-workforce': 'Tambah Data Tenaga Kerja Kontraktor',
        'al-accepted': 'SP Diterima',
        'all-letters': 'Semua Surat',
        'all-assign-letter': 'Semua Surat Penunjukan',
        'al-rejected': 'SP Ditolak',
        'appointment-letter-list': 'Daftar Surat Penunjukan',
        'appointment-letter-management': 'Manajemen Surat Penunjukan',
        'assign-letter-report': 'Laporan Surat Penunjukan',
        'assign-letters': 'Surat Penunjukan',
        'all-requests-section': 'Seluruh Pengajuan',
        'all-statuses': 'Pilih Status',
        'request-date': 'Tanggal Pengajuan',
        'verified-date': 'Tanggal Verifikasi',
        'brief-description-supervision-area': 'Deskripsi singkat area pengawasan',
        'certificate-skill-example': 'Contoh: Sertifikat Keahlian',
        'certifications-competencies': 'Sertifikasi/Kompetensi',
        'clear-filter': 'Hapus Filter',
        'company': 'Perusahaan',
        'competency-name-example-mining-ops': 'Contoh: Pengawasan Operasional Tambang',
        'competency-type': 'Jenis Kompetensi',
        'complete-workforce-list': 'Daftar Lengkap Tenaga Kerja',
        'confirm-change-status': 'Apakah Anda yakin ingin mengubah status?',
        'confirm-delete-area-cannot-undo': 'Apakah Anda yakin ingin menghapus area ini? Tindakan ini tidak dapat dibatalkan!',
        'confirm-delete-certification': 'Apakah Anda yakin ingin menghapus sertifikasi ini?',
        'confirm-delete-competency': 'Apakah Anda yakin ingin menghapus kompetensi ini?',
        'contractor-workforce-data': 'Data Tenaga Kerja Kontraktor',
        'data-rejected': 'Data Tidak disetujui!',
        'delete': 'Hapus',
        'describe-this-competency': 'Jelaskan kompetensi ini...',
        'displaying-employees-status': 'Menampilkan karyawan dengan status:',
        'displaying-letters-status': 'Menampilkan surat dengan status:',
        'draft': 'Draft',
        'edit': 'Edit',
        'employee': 'Karyawan',
        'employee-full-name': 'Nama lengkap karyawan',
        'enter-current-password': 'Masukkan kata sandi saat ini',
        'enter-new-password-min-6': 'Masukkan kata sandi baru (min. 6 karakter)',
        'error': 'Error!',
        'example-badge': 'Contoh: BADGE001',
        'example-position': 'Contoh: Rigger, HSE Superintendent',
        'id-badge': 'ID BADGE',
        'identity-competency-data': 'Data Identitas & Kompetensi',
        'identity-data': 'Data Identitas',
        'jump-to-section': 'Lompat ke Bagian:',
        'leadership-example-placeholder': 'Contoh: Kepemimpinan',
        'letter-number': 'Nomor Surat',
        'manage-appointment-letters': 'Kelola pembuatan dan pengajuan surat penunjukan keahlian',
        'manage-verify-workforce': 'Kelola dan verifikasi data tenaga kerja kontraktor',
        'name': 'Nama',
        'needs-review': 'Perlu Review',
        'new-request': 'Pengajuan Baru',
        'no-workforce-data': 'Belum ada data tenaga kerja',
        'overall-statistics': 'Statistik Keseluruhan',
        'pending': 'Menunggu',
        'please-select-competency-type-first': 'Pilih jenis kompetensi terlebih dahulu',
        'position': 'Jabatan',
        'target-position': 'Posisi Tujuan',
        'address': 'Alamat',
        'rejected-employees-message-1': 'Terdapat',
        'rejected-employees-message-2': 'data karyawan yang tidak disetujui dan perlu diperbaiki. Klik tombol',
        'rejected-employees-message-3': 'pada data yang tidak disetujui untuk melakukan perbaikan.',
        'remove-filter': 'Hapus Filter',
        'repeat-new-password': 'Ulangi kata sandi baru',
        'report-summary': 'Ringkasan dan detail hasil pemrosesan surat penunjukan',
        'assign-letters-processed': 'Surat Penunjukan Diproses',
        'export-pdf-report': 'Ekspor Laporan PDF',
        'export-to-excel': 'Ekspor ke Excel',
        'showing-all-data': 'Menampilkan semua data',
        'view-notes': 'Lihat Catatan',
        'details': 'Detail',
        'certificate-expiration-2-months': 'Kedaluwarsa Sertifikat (<=2 Bulan)',
        'expiring-certs-renew-immediately': 'Berikut daftar karyawan dengan sertifikat yang akan kedaluwarsa dalam <=2 bulan. Harap segera lakukan perpanjangan.',
        'days-left': 'Sisa Hari',
        'days': 'hari',
        'very-urgent': 'Sangat Mendesak',
        'warning': 'Peringatan',
        'expiring-soon': 'Akan segera kadaluwarsa',
        'no-expiration-date': 'Tidak ada tanggal kadaluwarsa',
        'assign-letter-no-label': 'No. Surat Penunjukan:',
        'req-accepted': 'Pengajuan Disetujui',
        'req-rejected': 'Pengajuan Tidak Disetujui',
        'requests': 'Pengajuan',
        'rejected-requests': 'Pengajuan Tidak Disetujui',
        'scope-of-work': 'Ruang Lingkup Pekerjaan',
        'select-competency': '-- Pilih Kompetensi --',
        'select-competency-type': '-- Pilih Jenis Kompetensi --',
        'select-scope-of-work': '-- Pilih Ruang Lingkup Pekerjaan --',
        'sub-competency': 'Sub Kompetensi',
        'sub-competency-example-industrial-hygiene': 'Contoh: Ahli Hygiene Industri Muda',
        'success': 'Berhasil!',
        'supervision-area-code-example': 'Contoh: MSM',
        'supervision-area-company-example': 'Contoh: PT Meares Soputan Mining (MSM)',
        'total-employees': 'Total Karyawan',
        'total-letters': 'Total Surat',
        'type-or-select-competency': 'Ketik atau pilih kompetensi',
        'unique-id-badge': 'ID unik untuk identifikasi badge karyawan',
        'verified-by': 'Diverifikasi Oleh',
        'waiting': 'Menunggu',
        'waiting-approval': 'Menunggu Persetujuan',
        'filter-status-label': 'Filter Status:',
        'assign-letter-list': 'Daftar Surat Penunjukan',
        'employee-list': 'Daftar Karyawan',
        'complete-employee-list': 'Daftar Karyawan Lengkap',
        'certification-list': 'Daftar Sertifikasi',
        'letter-no': 'No. Surat',
        'appointment-letter': 'Surat Penunjukan',
        'approval-history': 'Riwayat Persetujuan',
        'step': 'Tahap',
        'name-username': 'Nama / Username',
        'certificates-about-to-expire': 'Sertifikat Akan Kedaluwarsa',
        'history-assign-letter': 'Riwayat Surat Penunjukan',
        'registration-no': 'No. Registrasi',
        'employee': 'Karyawan',
        'approval': 'Persetujuan',
        'add-new-request-employee': 'Tambah Pengajuan Karyawan Baru',
        'upload-employee-correction': 'Upload Koreksi Data Karyawan',
        'upload-correction-description': 'Perbaiki data yang ditolak dan upload ulang untuk verifikasi',
        'appointment-letter-details': 'Detail Surat Penunjukan',
        'letter-information': 'Informasi Surat',
        'approval-information': 'Informasi Persetujuan',
        'created-at': 'Dibuat Pada',
        'company-contractor': 'Perusahaan / Kontraktor',
        'work-scope': 'Ruang Lingkup Kerja',
        'no-time-limit': 'Tidak ada batas waktu',
        'select-company': '-- Pilih Perusahaan --',
        'rejection-reason': 'Alasan Penolakan',
        'from-admin': 'Dari Admin',
        'from-ktt': 'Dari KTT',
        'rejected-by': 'Ditolak oleh',
        'on-date': 'pada',
        'id-badge': 'ID BADGE',
        'id-badge-cannot-change': 'ID BADGE tidak dapat diubah',
        'back': 'Kembali',
        'select-supervision-area': '-- Pilih Area Pengawasan --',
        'click-drag-cv-file': 'Klik atau seret file CV (PDF/DOC/DOCX, Maks 5MB)',
        'click-drag-statement-letter': 'Klik atau seret Surat Pernyataan (PDF, Maks 5MB)',
        'certification-competency': 'Sertifikasi/Kompetensi',
        'certificate-type': 'Jenis Sertifikat',
        'select-type': '-- Pilih Jenis --',
        'certificate-no': 'No. Sertifikat',
        'validity-period': 'Masa Berlaku',
        'upload-cv-optional-no-change': 'Unggah CV <span class="text-muted">(Opsional - biarkan kosong jika tidak ada perubahan)</span>',
        'click-drag-new-cv-file': 'Klik atau seret file CV baru (PDF/DOC/DOCX, Maks 5MB)',
        'upload-signature-optional': 'Unggah Tanda Tangan <span class="text-muted">(Opsional)</span>',
        'click-drag-new-signature-file': 'Klik atau seret file tanda tangan baru (PNG/JPG, Maks 2MB)',
        'reason-for-no-expiry-optional': 'Alasan Tanpa Kedaluwarsa <span class="text-muted">(Opsional)</span>',
        'upload-new-certificate-file-optional': 'Unggah File Sertifikat Baru <span class="text-muted">(Opsional - biarkan kosong jika tidak ada perubahan)</span>',
        'click-drag-new-certificate-file': 'Klik atau seret file sertifikat baru (PDF, Maks 5MB)',
        'appointment-letter-reports': 'Laporan Surat Penunjukan',
        'summary': 'Ringkasan',
        'company-summary': 'Ringkasan per Perusahaan',
        'detail-assign-letter-accepted': 'Detail Surat Penunjukan Disetujui',
        'work-scope-label': 'Ruang Lingkup Kerja:',
        'all-scopes': '-- Semua Ruang Lingkup --',
        'supervision-area-label': 'Area Pengawasan:',
        'all-areas': '-- Semua Area --',
        'assign-letter-no': 'No. Surat Penunjukan',
        'detail-assign-letter-rejected': 'Detail Surat Penunjukan Tidak Disetujui',
        'rejected-date': 'Tanggal Tidak Disetujui',
        'assign-letter-rejection-details': 'Detail Penolakan Surat Penunjukan',
        'letter-number-label': 'Nomor Surat:',
        'employee-name-label': 'Nama Karyawan:',
        'rejection-notes-from-ktt': 'Catatan Penolakan dari KTT',
        'assign-letters-accept-report': 'Laporan Surat Penunjukan Disetujui',
        'printed-on': 'Dicetak pada',
        'time': 'Waktu',
        'total-approved-letters': 'Total Surat Disetujui',
        'printed-from-system': 'Dokumen ini dicetak dari Sistem Surat Penunjukan Kompetensi',
        'appointment-letter-report': 'Laporan Surat Penunjukan',
        'export-date': 'Tanggal Ekspor',
        'scope-filter': 'Filter Ruang Lingkup',
        'total-data': 'Total Data',
        'employee-statistics': 'Statistik Karyawan',
        'all-employees': 'Seluruh Karyawan',
        'all-requests': 'Seluruh Pengajuan',
        'waiting-reviewer': 'Menunggu Reviewer',
        'appointment-letter-statistics': 'Statistik Surat Penunjukan',
        'all-letters': 'Semua Surat',
        'accepted-ktt': 'Disetujui KTT',
        'rejected-ktt': 'Tidak Disetujui KTT',
        'waiting-ktt': 'Menunggu KTT',
        'rejected-by-ktt': 'Tidak Disetujui oleh KTT',
        'no-appointment-letter-data': 'Belum ada data surat penunjukan',
        'no-letters-waiting-admin-review': 'Tidak ada surat yang menunggu tinjauan admin',
        'no-admin-review-yet': 'Belum ada tinjauan admin',
        'no-assign-letters': 'Belum ada surat penunjukan',
        'view-all-employees': 'Lihat Semua Karyawan',
        'view-all-letters': 'Lihat Semua Surat',
        'cv-file': 'File CV',
        'basic-information': 'Informasi Dasar',
        'competency-information': 'Informasi Kompetensi',
        'status-verification': 'Status & Verifikasi',
        'view-cv': 'Lihat CV',
        'verification-notes': 'Catatan Verifikasi:',
        'final-data-verification': 'Verifikasi Data Akhir',
        'certificate-has-expired': 'Sertifikat sudah kedaluwarsa!',
        'no-certifications-uploaded-yet': 'Belum ada sertifikasi yang diunggah',
        'no-expiry-date': 'Tidak ada tanggal kedaluwarsa',
        'no-appointment-letters-available': 'Belum ada surat penunjukan',
        'no-employee-data': 'Belum ada data karyawan',
        'back-to-dashboard': 'Kembali ke Dashboard',
        'welcome-competency-appointment-letter-system': 'Selamat datang di sistem surat penunjukan kompetensi',
        'expiring-certificate-desc': 'Karyawan dengan sertifikat yang akan kedaluwarsa dalam ≤ 2 bulan',
        'expiring-certificate-message': 'Ada karyawan dengan sertifikat yang akan kedaluwarsa dalam ≤ 2 bulan. Harap periksa dan perbarui sertifikat mereka.',
        'expiring-certificate-message-suffix': 'karyawan dengan sertifikat yang akan kedaluwarsa dalam ≤ 2 bulan. Harap periksa dan perbarui sertifikat mereka.',
        'certificates-expiring-soon': 'Sertifikat Akan Segera Kadaluwarsa!',
        'there-are': 'Ada',
        'total-processed': 'Total Diproses',
        'correct-rejected-data-and-reupload': 'Perbaiki data yang tidak disetujui dan unggah ulang untuk verifikasi',
        'rejected-data': 'Data Tidak Disetujui!',
        'there-is-rejected-data': 'Ada Data Tidak Disetujui!',
        'rejected-user-employee-message-1': 'Ada',
        'rejected-user-employee-message-2': 'data karyawan yang tidak disetujui dan perlu diperbaiki. Silakan klik tombol "Ajukan Kembali" untuk mengirim ulang data yang sudah diperbaiki.',
        'rejected-employee-data-message': 'Ada data karyawan yang tidak disetujui dan perlu diperbaiki. Silakan klik tombol "Unggah Koreksi" untuk mengirim ulang data yang sudah diperbaiki.',
        'rejected-employee-data-suffix': 'data karyawan yang tidak disetujui dan perlu diperbaiki. Silakan klik tombol "Unggah Koreksi" untuk mengirim ulang data yang sudah diperbaiki.',
        'rejection-reason': 'Alasan Tidak Disetujui:',
        'from-admin': 'Dari Admin:',
        'on-date': 'pada',
        'appointment-rejected': 'Surat Penunjukan Tidak Disetujui',
        'this-appointment-was-rejected': 'Surat penunjukan ini tidak disetujui',
        'after-employee-data-added-note': 'Setelah data karyawan ditambahkan, status akan menjadi "Pending" dan menunggu verifikasi dari Admin sebelum Surat Penunjukan dapat dibuat.',
        'cancel': 'Batal',
        'appointment-letters': 'Surat Penunjukan',
        'pending-your-approval': 'Menunggu Persetujuan Anda',
        'letters': 'Surat',
        'resubmitted': 'Diajukan Ulang',
        'you-rejected-this': 'Anda tidak menyutujui Surat Penunjukan ini',
        'previous-rejection': 'Penolakan Sebelumnya',
        'previous-rejection-details': 'Detail Penolakan Sebelumnya',
        'reviewed-by': 'Ditinjau Oleh',
        'by': 'oleh',
        'valid': 'Berlaku',
        'until-short': 's/d',
        'appointment-date': 'Tanggal Penunjukan',
        'valid-from': 'Berlaku Dari',
        'expires': 'Berakhir',
        'scope-of-work-msm': 'PT MSM',
        'scope-of-work-ttn': 'PT TTN',
        'competency-type-operational-supervisor': 'Pengawas Operasional',
        'competency-type-technical-supervisor': 'Pengawas Teknis',
        'competency-type-technical-personnel': 'Tenaga Teknis',
        'iso-9001-example': 'Contoh: ISO 9001',
        'national-certification-board-example': 'Contoh: Badan Sertifikasi Nasional',
        'showing-all-data-all-companies': 'Menampilkan semua data dari semua perusahaan',
        'all-data': 'Semua Data',
        'data': 'data',
        'approved': 'Disetujui',
        'approved-by': 'Disetujui Oleh',
        'certificate-name': 'Nama Sertifikat',
        'close': 'Tutup',
        'date': 'Tanggal',
        'department': 'Departemen',
        'document': 'Dokumen',
        'effective-date': 'Tanggal Efektif',
        'expiry': 'Kedaluwarsa',
        'no-appointment-letter-data-processed': 'Belum ada data surat penunjukan yang diproses',
        'notes': 'Catatan',
        'rejected-by': 'Tidak disetujui Oleh',
        'rejection-notes': 'Catatan Tidak Disetujui',
        'review-date': 'Tanggal Tinjauan',
        'supervision-area': 'Area Pengawasan',
        'total': 'Total',
        'upload-cv': 'Unggah CV',
        'upload-statement-letter': 'Unggah Surat Pernyataan',
        'view': 'Lihat',
        'view-statement-letter': 'Lihat Surat Pernyataan',
        'view-certificate': 'Lihat Sertifikat',
        'ktt-already-made-decision': 'Anda sudah memberikan keputusan untuk surat penunjukan ini!',
        'ktt-assign-letter-successfully-approved': 'Surat penunjukan berhasil disetujui!',
        'ktt-approved-sent-admin-review': 'Anda telah menyetujui surat penunjukan ini.',
        'ktt-approved-assign-letter': 'Anda telah menyetujui surat penunjukan ini.',
        'ktt-approved-but-other-rejected-sent-admin-review': 'Anda telah menyetujui surat penunjukan ini, namun KTT lain tidak menyetujuinya. Surat telah dikirim ke Admin untuk ditinjau.',
        'ktt-rejected-sent-admin-review': 'Anda tidak menyetujui surat penunjukan ini.',
        'ktt-failed-approve-assign-letter': 'Gagal menyetujui surat penunjukan!',
        'ktt-failed-reject-assign-letter': 'Gagal tidak menyetujui surat penunjukan!',
        'employee-name': 'Nama Karyawan',
        'employee-code': 'ID BADGE',
        'years': 'Tahun',
        'all-employees-label': 'Semua karyawan:',
        'fix-rejected-data-reupload': 'Perbaiki data yang tidak disetujui dan unggah ulang untuk verifikasi',
        'from-admin-review-ktt-rejection': 'Dari Admin (Review Penolakan KTT):',
        'from-ktt': 'Dari KTT:',
        'on': 'pada',
        'id-badge-required': 'ID BADGE',
        'full-name-required': 'Nama Lengkap',
        'position-required': 'Jabatan',
        'scope-of-work-required': 'Ruang Lingkup Kerja',
        'competency-type-required': 'Tipe Kompetensi',
        'supervision-area-required': 'Area Pengawasan',
        'company-required': 'Perusahaan',
        'optional': '(Opsional)',
        'optional-leave-blank-no-change': '(Opsional - biarkan kosong jika tidak ada perubahan)',
        'optional-leave-blank-if-no-change': '(Opsional - biarkan kosong jika tidak ada perubahan)',
        'current-file': 'File saat ini:',
        'click-or-drag-new-cv-file': 'Klik atau seret file CV baru (PDF, Maks 5MB)',
        'click-or-drag-new-statement-letter-file': 'Klik atau seret file surat pernyataan baru (PDF, Maks 5MB)',
        'statement-letter-original-signature-note': 'Surat pernyataan harus ditandatangani dengan <strong>tanda tangan basah asli</strong> oleh pihak terkait, lalu dipindai dalam format PDF.',
        'important-information': 'Informasi Penting:',
        'resubmit-file-upload-optional-info': 'Unggah file bersifat OPSIONAL. Anda tidak perlu mengunggah ulang CV, tanda tangan, atau file sertifikat jika data yang ada sudah benar. File yang ada akan tetap digunakan.',
        'reupload-only-if': 'Unggah ulang hanya jika:',
        'resubmit-reupload-condition': 'Admin menyebutkan pada catatan penolakan bahwa file tertentu perlu diperbaiki/diganti.',
        'certification-name-required': 'Nama Sertifikasi',
        'certificate-number-required': 'Nomor Sertifikat',
        'issuer-required': 'Penerbit',
        'issue-date-required': 'Tanggal Terbit',
        'validity-period-required': 'Masa Berlaku',
        'expiry-date-required': 'Tanggal Kedaluwarsa',
        'expiry-date-manual-edit-note': 'Anda dapat mengubah tanggal kedaluwarsa secara manual jika diperlukan',
        'no-expiry-reason': 'Tidak ada Kadaluwarsa',
        'upload-new-certificate-file': 'Unggah File Sertifikat Baru',
        'upload-certificate-file-required': 'Unggah File Sertifikat',
        'click-or-drag-new-certificate-file': 'Klik atau seret file sertifikat baru (PDF, Maks 5MB)',
        'click-or-drag-certificate-file': 'Klik atau seret file sertifikat (PDF, Maks 5MB)',
        'validity-years-hint': 'Masukkan dalam tahun, contoh: 3 atau 2.5 untuk 2 tahun 6 bulan',
        'after-resubmit-status-pending-note': 'Setelah mengunggah perbaikan, status akan kembali menjadi "Pending" dan menunggu verifikasi ulang dari Admin.',
        'appointment-letter-number-will-remain': 'Nomor Surat Penunjukan',
        'will-remain-the-same': 'akan tetap sama.',
        'new': 'BARU',
        'technical-supervisor': 'Pengawas Teknis',
        'technical-personnel': 'Tenaga Teknis',
        'operational-supervisor': 'Pengawas Operasional'
    }
};

let currentLanguage = detectCurrentLanguage();

const exactAutoIdMap = {
    'Accept': 'Disetujui',
    'Accepted': 'Disetujui',
    'Reject': 'Tidak disetujui',
    'Rejected': 'Tidak disetujui',
    'Pending': 'Menunggu',
    'Draft': 'Draf',
    'Request': 'Pengajuan',
    'Requests': 'Pengajuan',
    'Reports': 'Laporan',
    'Dashboard': 'Menu Utama',
    'Settings': 'Pengaturan',
    'Logout': 'Keluar',
    'Employee': 'Karyawan',
    'Employees': 'Karyawan',
    'Status': 'Status',
    'Actions': 'Aksi',
    'Action': 'Aksi',
    'Company': 'Perusahaan',
    'Position': 'Jabatan',
    'Competency': 'Kompetensi',
    'Sub Competency': 'Sub Kompetensi',
    'Letter Number': 'Nomor Surat',
    'Total Employees': 'Total Karyawan',
    'Total Letters': 'Total Surat',
    'All Employees': 'Semua Karyawan',
    'All Letters': 'Semua Surat',
    'Success!': 'Berhasil!',
    'Error!': 'Error!'
};

const tokenAutoIdMap = {
    approval: 'persetujuan',
    accepted: 'disetujui',
    accept: 'disetujui',
    rejected: 'tidak disetujui',
    reject: 'tidak disetujui',
    pending: 'menunggu',
    draft: 'draf',
    request: 'pengajuan',
    reports: 'laporan',
    report: 'laporan',
    dashboard: 'menu utama',
    settings: 'pengaturan',
    logout: 'keluar',
    employee: 'karyawan',
    employees: 'karyawan',
    management: 'manajemen',
    monitor: 'pantau',
    create: 'buat',
    creation: 'pembuatan',
    submission: 'pengajuan',
    assign: 'penunjukan',
    letter: 'surat',
    letters: 'surat',
    appointment: 'penunjukan',
    history: 'riwayat',
    recent: 'terbaru',
    details: 'detail',
    detail: 'detail',
    view: 'lihat',
    data: 'data',
    showing: 'muncul',
    clear: 'hapus',
    remove: 'hapus',
    filter: 'filter',
    active: 'aktif',
    waiting: 'menunggu',
    workforce: 'tenaga kerja',
    contractor: 'kontraktor',
    competency: 'kompetensi',
    certification: 'sertifikasi',
    supervision: 'supervisi',
    areas: 'area',
    action: 'aksi',
    actions: 'aksi',
    status: 'status',
    company: 'perusahaan',
    position: 'jabatan',
    name: 'nama',
    verified: 'diverifikasi',
    by: 'oleh'
};

function detectCurrentLanguage() {
    const stored = localStorage.getItem(STORAGE_KEY) || 'id';
    return SUPPORTED_LANGUAGES.includes(stored) ? stored : 'id';
}

const requiredLabelKeys = new Set([
    'employee_code',
    'id-badge-required',
    'company',
    'position',
    'competency-type',
    'competency',
    'certificate-no',
    'certificate-type',
    'certification-name',
    'company',
    'department',
    'expiry-date',
    'full-name',
    'issue-date',
    'issuer',
    'notes',
    'no-expiry',
    'other-type',
    //'position' removed: not required in Complete Employee List
    'scope-of-work',
    'sub-competency',
    'supervision-area',
    'upload-certificate-file',
    'upload-cv',
    'upload-statement-letter',
    'validity-period'
]);

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function t(key) {
    const activeMap = translations[currentLanguage] || {};
    const applyRequiredMark = (text) => {
        if (typeof text !== 'string' || !requiredLabelKeys.has(key)) {
            return text;
        }

        return /\*\s*$/.test(text) ? text : `${text} *`;
    };
    if (Object.prototype.hasOwnProperty.call(activeMap, key)) {
        return applyRequiredMark(activeMap[key]);
    }

    const fallbackMap = translations.en || {};
    if (Object.prototype.hasOwnProperty.call(fallbackMap, key)) {
        if (currentLanguage === 'id') {
            return applyRequiredMark(autoTranslateToId(fallbackMap[key]));
        }
        return applyRequiredMark(fallbackMap[key]);
    }

    if (runtimeDefaults[key]) {
        if (currentLanguage === 'id') {
            return applyRequiredMark(autoTranslateToId(runtimeDefaults[key]));
        }
        return applyRequiredMark(runtimeDefaults[key]);
    }

    if (!missingKeyWarnings.has(key)) {
        missingKeyWarnings.add(key);
        console.warn('[i18n] Missing translation key:', key);
    }

    if (currentLanguage === 'id') {
        return applyRequiredMark(autoTranslateToId(humanizeKey(key)));
    }

    return null;
}

function humanizeKey(key) {
    return String(key)
        .replace(/[-_]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, ch => ch.toUpperCase());
}

function autoTranslateToId(text) {
    if (!text) return text;

    if (Object.prototype.hasOwnProperty.call(exactAutoIdMap, text)) {
        return exactAutoIdMap[text];
    }

    const parts = String(text).split(/(\s+|[.,:;!?()"'-])/g);
    const translated = parts.map(part => {
        const normalized = part.toLowerCase();
        if (Object.prototype.hasOwnProperty.call(tokenAutoIdMap, normalized)) {
            const mapped = tokenAutoIdMap[normalized];
            const isUpper = part.toUpperCase() === part && /[A-Z]/.test(part);
            const isCapitalized = /^[A-Z]/.test(part);

            if (isUpper) return mapped.toUpperCase();
            if (isCapitalized) return mapped.charAt(0).toUpperCase() + mapped.slice(1);
            return mapped;
        }
        return part;
    }).join('');

    return translated;
}

function captureRuntimeDefaults(rootElement) {
    const root = rootElement || document;
    const elements = root.querySelectorAll('[data-lang]');

    elements.forEach(el => {
        const key = el.getAttribute('data-lang');
        if (!key) return;

        const tag = el.tagName.toUpperCase();
        if (tag === 'LABEL') {
            const looksRequired =
                el.hasAttribute('data-lang-required') ||
                !!el.querySelector('.text-danger') ||
                /\*\s*$/.test((el.textContent || '').trim());

            if (looksRequired) {
                el.setAttribute('data-lang-required', '1');
            }
        }

        if (runtimeDefaults[key]) return;
        const inputType = (el.getAttribute('type') || '').toLowerCase();

        if ((tag === 'INPUT' || tag === 'TEXTAREA') && el.hasAttribute('placeholder')) {
            runtimeDefaults[key] = el.getAttribute('placeholder') || '';
            return;
        }

        if ((tag === 'INPUT' || tag === 'TEXTAREA') && (inputType === 'button' || inputType === 'submit' || inputType === 'reset')) {
            runtimeDefaults[key] = el.value || '';
            return;
        }

        runtimeDefaults[key] = (el.textContent || '').trim();
    });
}

function applyTranslations(rootElement) {
    const root = rootElement || document;

    const placeholderElements = root.querySelectorAll('[data-lang-placeholder]');
    placeholderElements.forEach(el => {
        const key = el.getAttribute('data-lang-placeholder');
        if (!key) return;

        const translated = t(key);
        if (translated !== null) {
            el.setAttribute('placeholder', translated);
        }
    });

    const titleElements = root.querySelectorAll('[data-lang-title]');
    titleElements.forEach(el => {
        const key = el.getAttribute('data-lang-title');
        if (!key) return;

        const translated = t(key);
        if (translated !== null) {
            el.setAttribute('title', translated);
        }
    });

    const ariaLabelElements = root.querySelectorAll('[data-lang-aria-label]');
    ariaLabelElements.forEach(el => {
        const key = el.getAttribute('data-lang-aria-label');
        if (!key) return;

        const translated = t(key);
        if (translated !== null) {
            el.setAttribute('aria-label', translated);
        }
    });

    const elements = root.querySelectorAll('[data-lang]');

    elements.forEach(el => {
        const key = el.getAttribute('data-lang');
        if (!key) return;

        const translated = t(key);
        if (translated === null) return;

        const tag = el.tagName.toUpperCase();
        const inputType = (el.getAttribute('type') || '').toLowerCase();

        if (tag === 'INPUT' || tag === 'TEXTAREA') {
            if (el.hasAttribute('placeholder')) {
                el.setAttribute('placeholder', translated);
            } else if (inputType === 'button' || inputType === 'submit' || inputType === 'reset') {
                el.value = translated;
            }
            return;
        }

        if (tag === 'LABEL' && (requiredLabelKeys.has(key) || el.hasAttribute('data-lang-required'))) {
            const labelText = String(translated).replace(/\s*\*\s*$/, '').trim();
            el.innerHTML = `${escapeHtml(labelText)} <span class="text-danger">*</span>`;
            return;
        }

        el.textContent = translated;
    });
}

function setLanguage(lang) {
    if (!SUPPORTED_LANGUAGES.includes(lang)) {
        console.error('Invalid language code. Use "en" or "id".');
        return;
    }

    currentLanguage = lang;
    localStorage.setItem(STORAGE_KEY, lang);
    document.cookie = `language=${encodeURIComponent(lang)}; path=/; max-age=31536000; SameSite=Lax`;

    document.documentElement.lang = lang;
    applyTranslations();
    updateLanguageButton();
}

function changeLanguage(lang) {
    setLanguage(lang);
}

function toggleLanguage(lang) {
    if (lang) {
        setLanguage(lang);
        return;
    }

    const newLang = currentLanguage === 'en' ? 'id' : 'en';
    setLanguage(newLang);
}

function updateDropdownActiveState() {
    const dropdownItems = document.querySelectorAll('.language-dropdown-menu .dropdown-item');
    dropdownItems.forEach(item => {
        const itemLang = item.getAttribute('data-lang-code');
        if (itemLang === currentLanguage) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

function updateLanguageButton() {
    const langButtons = document.querySelectorAll('#languageToggle');
    langButtons.forEach(btn => {
        const langText = currentLanguage === 'id' ? 'ID' : 'EN';
        btn.innerHTML = `<span class="lang-text">${langText}</span><i class="fas fa-chevron-down" style="font-size: 10px;"></i>`;
    });

    updateDropdownActiveState();
}

function toggleDropdown(event) {
    event.stopPropagation();
    const container = event.currentTarget.closest('.language-dropdown');
    const dropdown = container ? container.querySelector('.language-dropdown-menu') : null;
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

function closeDropdown() {
    const allDropdowns = document.querySelectorAll('.language-dropdown-menu.show');
    allDropdowns.forEach(menu => menu.classList.remove('show'));
}

function setupLightMutationObserver() {
    let rafId = null;

    const observer = new MutationObserver(mutations => {
        let shouldApply = false;

        for (let i = 0; i < mutations.length; i += 1) {
            const mutation = mutations[i];

            for (let j = 0; j < mutation.addedNodes.length; j += 1) {
                const node = mutation.addedNodes[j];
                if (node.nodeType !== 1) continue;

                const hasDataLang =
                    (typeof node.hasAttribute === 'function' && node.hasAttribute('data-lang')) ||
                    (typeof node.querySelector === 'function' && node.querySelector('[data-lang]'));

                if (hasDataLang) {
                    shouldApply = true;
                    break;
                }
            }

            if (shouldApply) break;
        }

        if (!shouldApply) return;

        if (rafId) {
            cancelAnimationFrame(rafId);
        }

        rafId = requestAnimationFrame(() => {
            captureRuntimeDefaults(document);
            applyTranslations();
            rafId = null;
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

function initLanguageSystem() {
    currentLanguage = detectCurrentLanguage();
    document.documentElement.lang = currentLanguage;
    document.cookie = `language=${encodeURIComponent(currentLanguage)}; path=/; max-age=31536000; SameSite=Lax`;

    captureRuntimeDefaults(document);

    applyTranslations();
    updateLanguageButton();

    const langToggleButtons = document.querySelectorAll('#languageToggle');
    langToggleButtons.forEach(btn => {
        btn.addEventListener('click', toggleDropdown);
    });

    const dropdownItems = document.querySelectorAll('.language-dropdown-menu .dropdown-item');
    dropdownItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const lang = this.getAttribute('data-lang-code');
            if (lang) {
                setLanguage(lang);
            }
            closeDropdown();
        });
    });

    document.addEventListener('click', closeDropdown);

    setupLightMutationObserver();
}

document.addEventListener('DOMContentLoaded', initLanguageSystem);

// Expose functions globally
window.changeLanguage = changeLanguage;
window.toggleLanguage = toggleLanguage;
window.getLanguageText = function(key, fallback) {
    const translated = t(key);
    return translated === null ? fallback : translated;
};
window.applyCurrentLanguage = function() {
    applyTranslations();
    updateLanguageButton();
};
window.getCurrentLanguage = function() {
    return currentLanguage;
};

console.log('Local language switcher loaded. Current language:', currentLanguage);
