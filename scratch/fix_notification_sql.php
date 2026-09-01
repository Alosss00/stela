<?php
$file = 'app/Services/NotificationService.php';
$content = file_get_contents($file);

// Fix: notifyKttForApproval query with raw $appointment_id
$old = "WHERE a.id = \$appointment_id";
$new = "WHERE a.id = ?";
$count = substr_count($content, $old);
$content = str_replace($old, $new, $content);
echo "Fixed $count occurrence(s) of raw \$appointment_id in WHERE clause\n";

// But we also need to add parameter to the query call.
// The query call pattern is: $this->db->query(" ... WHERE a.id = ?
// ");
// and we need: $this->db->query(" ... WHERE a.id = ? ", [$appointment_id]);
$old2 = "WHERE a.id = ?
        \");";
$new2 = 'WHERE a.id = ?
        ", [$appointment_id]);';
$count2 = substr_count($content, $old2);
$content = str_replace($old2, $new2, $content);
echo "Fixed $count2 occurrence(s) of query closing bracket\n";

file_put_contents($file, $content);
echo "Done\n";
?>
