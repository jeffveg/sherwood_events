<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/events.php';

auth_require();

$events = events_admin_all();
$flash  = flash_pop();

$pageTitle = 'Admin | Sherwood Events';
include __DIR__ . '/../_partials/head.php';
include __DIR__ . '/../_partials/nav.php';
?>

<main class="content-body admin-page" id="main-content">
  <div class="admin-header">
    <h1>Events Admin</h1>
    <div class="admin-actions">
      <a class="btn btn-gold" href="/admin/edit.php">+ New Event</a>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <table class="admin-table">
    <thead>
      <tr>
        <th>When</th><th>Title</th><th>Status</th><th>Featured</th><th>RSVPs</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$events): ?>
        <tr><td colspan="6" class="empty">No events yet. <a href="/admin/edit.php">Create one</a>.</td></tr>
      <?php else: foreach ($events as $ev): ?>
        <tr class="status-<?= e($ev['status']) ?>">
          <td><?= e(fmt_date($ev['start_datetime'])) ?></td>
          <td>
            <a href="/event.php?slug=<?= e($ev['slug']) ?>" target="_blank"><?= e($ev['title']) ?></a>
          </td>
          <td><span class="status-pill"><?= e($ev['status']) ?></span></td>
          <td><?= $ev['featured'] ? '★' : '' ?></td>
          <td>
            <?php if ($ev['rsvp_enabled']): ?>
              <a href="/admin/rsvps.php?event=<?= (int)$ev['id'] ?>"><?= (int)$ev['rsvp_count'] ?></a>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="/admin/edit.php?id=<?= (int)$ev['id'] ?>">Edit</a>
            <form method="POST" action="/admin/duplicate.php" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
              <button type="submit" class="link-btn">Duplicate</button>
            </form>
            <?php if ($ev['status'] !== 'cancelled'): ?>
              <form method="POST" action="/admin/delete.php" style="display:inline"
                    onsubmit="return confirm('Cancel this event? It will be hidden from the public list but RSVPs are kept.');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                <button type="submit" class="link-btn link-btn-danger">Cancel</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</main>

<?php include __DIR__ . '/../_partials/footer.php'; ?>
