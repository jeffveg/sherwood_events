<?php
/**
 * Admin RSVP list — /admin/rsvps.php?event=N
 *                — /admin/rsvps.php?event=N&format=csv  (download)
 *
 * Lists every RSVP for one event with name, email (mailto link),
 * phone, party size, notes, and submitted-at. Linked from the event
 * row in the admin dashboard.
 *
 * CSV export: format=csv adds a UTF-8 BOM (so Excel renders accents)
 * and runs each cell through csv_safe() to prevent formula-injection
 * (a name like "=HYPERLINK(...)" would otherwise execute when the
 * file opens).
 */

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/events.php';
require_once __DIR__ . '/../../src/rsvp.php';

auth_require();

$eventId = (int)($_GET['event'] ?? 0);
$event   = $eventId ? event_find_by_id($eventId) : null;
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$rsvps = rsvp_list((int)$event['id']);

// CSV export
if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rsvps-' . $event['slug'] . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM so Excel opens UTF-8 cleanly
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name','Email','Phone','Party size','Notes','Submitted']);
    foreach ($rsvps as $r) {
        fputcsv($out, [
            csv_safe($r['name']),
            csv_safe($r['email']),
            csv_safe($r['phone']),
            (int)$r['party_size'],
            csv_safe($r['notes']),
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'RSVPs: ' . $event['title'] . ' | Admin';
include __DIR__ . '/../_partials/head.php';
include __DIR__ . '/../_partials/nav.php';
?>

<main class="content-body admin-page" id="main-content">
  <p><a href="/admin/">&larr; Back to events</a></p>
  <h1>RSVPs: <?= e($event['title']) ?></h1>
  <p><?= e(fmt_event_when($event)) ?></p>
  <p>
    <strong><?= count($rsvps) ?></strong> submissions &middot;
    <strong><?= rsvp_attendee_count((int)$event['id']) ?></strong> attendees
    <?php if ($event['rsvp_capacity']): ?> of <?= (int)$event['rsvp_capacity'] ?> capacity<?php endif; ?>
    &middot; <a href="?event=<?= (int)$event['id'] ?>&amp;format=csv">Export CSV</a>
  </p>

  <table class="admin-table">
    <thead>
      <tr><th>Name</th><th>Email</th><th>Phone</th><th>Party</th><th>Notes</th><th>Submitted</th></tr>
    </thead>
    <tbody>
      <?php if (!$rsvps): ?>
        <tr><td colspan="6" class="empty">No RSVPs yet.</td></tr>
      <?php else: foreach ($rsvps as $r): ?>
        <tr>
          <td><?= e($r['name']) ?></td>
          <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= (int)$r['party_size'] ?></td>
          <td><?= e($r['notes']) ?></td>
          <td><?= e(fmt_date($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</main>

<?php include __DIR__ . '/../_partials/footer.php'; ?>
