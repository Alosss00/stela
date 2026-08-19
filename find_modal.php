<?php
$lines = file('resources/views/admin/appointments.php');
foreach($lines as $i => $l) {
    if (strpos($l, 'rejectionDetailModal') !== false) {
        echo $i . ': ' . trim($l) . "\n";
    }
}
