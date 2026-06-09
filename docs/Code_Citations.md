# Code Citations

## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```


## License: unknown
https://github.com/Athulya666/lazytools/blob/826d5f83d7622ff3ecb0873f708a72c3387235f0/iframe-demo/iframe.html

```
Saya melihat masalahnya. Query SQL masih mengambil `e.position` yang tidak ada di tabel employees. Dan kemungkinan file `get_appointment_details.php` juga perlu diperbaiki karena data tidak muncul. Mari saya perbaiki keduanya.

### c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

Hapus field `e.position` dari query karena field tersebut tidak ada di tabel employees.

````php
// filepath: c:\Users\USER\Downloads\windy-4-2-26 revisi\windy 4-2-26\approval.php

// ...existing code...

// Get pending appointments that haven't been decided by current KTT user
// MODIFIED: Only show pending appointments that haven't been decided by this KTT user
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code,
           e.contractor_company,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications 
            WHERE employee_id = a.employee_id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka 
            WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending' 
    AND NOT EXISTS (
        SELECT 1 FROM ktt_approvals ka 
        WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id
    )
    ORDER BY a.created_at ASC
");

// ...existing code...

<script>
// ...existing code...

function showReviewModal(appointmentId) {
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">Memuat data...</p></div>';
    
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data); // Debug
            
            if (data.success) {
                const
```

