<?php
/**
 * Admin event cancel — POST /admin/delete.php
 *
 * Soft-delete only: sets status='cancelled', preserves the row and
 * any RSVPs tied to it. The event keeps its public URL but renders
 * with a "this event has been cancelled" banner and noindex meta.
 *
 * To un-cancel: open the event in edit.php and switch status back
 * to "Published". There is no admin UI for hard delete by design —
 * cancelled events still exist in case anyone has a bookmark.
 */

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
