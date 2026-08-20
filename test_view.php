<?php
require_once __DIR__ . '/bootstrap/app.php';
$db = new Database();
$res = $db->query("CREATE VIEW test_view AS SELECT * FROM users WHERE deleted_at IS NULL");
var_dump($res);
$res2 = $db->query("INSERT INTO test_view (username, password) VALUES ('test_view_user', '123')");
var_dump($res2);
$res3 = $db->query("UPDATE test_view SET deleted_at = CURRENT_TIMESTAMP WHERE username = 'test_view_user'");
var_dump($res3);
$res4 = $db->query("SELECT * FROM test_view WHERE username = 'test_view_user'");
var_dump($res4->num_rows);
$res5 = $db->query("SELECT * FROM users WHERE username = 'test_view_user'");
var_dump($res5->num_rows);
$db->query("DELETE FROM users WHERE username = 'test_view_user'");
$db->query("DROP VIEW test_view");
