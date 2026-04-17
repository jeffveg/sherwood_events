<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/events.php';

auth_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    event_delete($id);   // soft-delete (status=cancelled)
    flash('success', 'Event cancelled. It is hidden from the public list; RSVPs are preserved.');
}
redirect('/admin/');
