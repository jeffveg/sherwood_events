<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/events.php';

auth_require();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isNew   = $id === 0;
$event   = $isNew ? null : event_find_by_id($id);
$allTags = tags_all();

if (!$isNew && !$event) {
    http_response_code(404);
    exit('Event not found.');
}

// -----------------------------------------------------------------------------
// Handle POST (save)
// -----------------------------------------------------------------------------
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $title    = trim((string)($_POST['title'] ?? ''));
    $desc     = trim((string)($_POST['description'] ?? ''));
    $start    = trim((string)($_POST['start_datetime'] ?? ''));
    $end      = trim((string)($_POST['end_datetime'] ?? ''));
    $allDay   = !empty($_POST['all_day']) ? 1 : 0;
    $slug     = trim((string)($_POST['slug'] ?? ''));
    $status   = in_array(($_POST['status'] ?? ''), ['draft','published','cancelled'], true)
                ? $_POST['status'] : 'published';
    $featured = !empty($_POST['featured']) ? 1 : 0;
    $rsvp_on  = !empty($_POST['rsvp_enabled']) ? 1 : 0;
    $cap      = $_POST['rsvp_capacity'] === '' ? null : max(0, (int)$_POST['rsvp_capacity']);

    $imageUrl = trim((string)($_POST['image_url'] ?? ''));
    $imageAlt = trim((string)($_POST['image_alt'] ?? ''));

    // Validate
    if ($title === '')          $errors[] = 'Title is required.';
    if ($desc === '')           $errors[] = 'Description is required.';
    if ($start === '')          $errors[] = 'Start date/time is required.';
    if ($end !== '' && $end < $start) $errors[] = 'End must be after start.';

    // Normalize datetime-local values (YYYY-MM-DDTHH:MM) to DB format
    $normalize = fn(string $v) => $v === '' ? null : str_replace('T', ' ', $v) . ':00';
    $startDb = $normalize($start);
    $endDb   = $normalize($end);

    // Slug: default from title, ensure unique
    $slugBase = $slug !== '' ? slugify($slug) : slugify($title);
    $slugFinal = slug_unique($slugBase, $isNew ? null : $id);

    // Image handling — upload OR URL (upload takes precedence)
    $imagePathDb = $event['image_path'] ?? null;
    if (!empty($_FILES['image_file']) && is_uploaded_file($_FILES['image_file']['tmp_name'] ?? '')) {
        $err = $_FILES['image_file']['error'];
        if ($err === UPLOAD_ERR_OK) {
            $saved = handle_image_upload($_FILES['image_file']);
            if ($saved instanceof Throwable) {
                $errors[] = $saved->getMessage();
            } else {
                $imagePathDb = $saved;
            }
        } elseif ($err !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Image upload failed (code ' . (int)$err . ').';
        }
    } elseif ($imageUrl !== '') {
        if (!preg_match('#^https?://#i', $imageUrl)) {
            $errors[] = 'Image URL must start with http:// or https://';
        } else {
            $imagePathDb = $imageUrl;
        }
    } elseif (!empty($_POST['clear_image'])) {
        $imagePathDb = null;
    }

    if (!$errors) {
        $data = [
            'slug'           => $slugFinal,
            'title'          => $title,
            'description'    => $desc,
            'start_datetime' => $startDb,
            'end_datetime'   => $endDb,
            'all_day'        => $allDay,
            'location_name'  => trim((string)($_POST['location_name'] ?? '')) ?: null,
            'location_addr'  => trim((string)($_POST['location_addr'] ?? '')) ?: null,
            'map_url'        => trim((string)($_POST['map_url'] ?? '')) ?: null,
            'event_site_url' => trim((string)($_POST['event_site_url'] ?? '')) ?: null,
            'ticket_url'     => trim((string)($_POST['ticket_url'] ?? '')) ?: null,
            'image_path'     => $imagePathDb,
            'image_alt'      => $imageAlt ?: null,
            'status'         => $status,
            'featured'       => $featured,
            'rsvp_enabled'   => $rsvp_on,
            'rsvp_capacity'  => $cap,
        ];

        if ($isNew) {
            $newId = event_create($data);
            event_set_tags($newId, array_map('intval', $_POST['tags'] ?? []));
            flash('success', 'Event created.');
            header('Location: /admin/edit.php?id=' . $newId);
            exit;
        } else {
            event_update($id, $data);
            event_set_tags($id, array_map('intval', $_POST['tags'] ?? []));
            flash('success', 'Event saved.');
            header('Location: /admin/edit.php?id=' . $id);
            exit;
        }
    }

    // Re-render with errors: merge posted values into $event
    $event = array_merge($event ?? [], $data ?? [], $_POST);
}

/**
 * Safely handle an uploaded image:
 * - MIME whitelist
 * - Re-encode via GD to strip metadata / embedded payloads
 * - Resize down to UPLOAD_MAX_WIDTH
 * Returns new filename (relative) on success, or Throwable on failure.
 */
function handle_image_upload(array $file)
{
    try {
        if ($file['size'] > UPLOAD_MAX_BYTES) {
            throw new RuntimeException('Image too large (max ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB).');
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new RuntimeException('Uploaded file is not a valid image.');
        }
        $mime = $info['mime'];
        $map = [
            'image/jpeg' => ['ext' => 'jpg', 'read' => 'imagecreatefromjpeg', 'write' => 'imagejpeg', 'q' => 85],
            'image/png'  => ['ext' => 'png', 'read' => 'imagecreatefrompng',  'write' => 'imagepng',  'q' => 6],
            'image/webp' => ['ext' => 'webp','read' => 'imagecreatefromwebp', 'write' => 'imagewebp', 'q' => 85],
        ];
        if (!isset($map[$mime])) {
            throw new RuntimeException('Image must be JPEG, PNG, or WEBP.');
        }
        $src = $map[$mime]['read']($file['tmp_name']);
        if (!$src) {
            throw new RuntimeException('Failed to decode image.');
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        if ($w > UPLOAD_MAX_WIDTH) {
            $ratio = UPLOAD_MAX_WIDTH / $w;
            $nw = UPLOAD_MAX_WIDTH;
            $nh = (int)round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $map[$mime]['ext'];
        $path = UPLOAD_DIR . '/' . $name;
        $map[$mime]['write']($src, $path, $map[$mime]['q']);
        imagedestroy($src);
        return $name;
    } catch (Throwable $ex) {
        return $ex;
    }
}

// -----------------------------------------------------------------------------
// Render
// -----------------------------------------------------------------------------
$v = fn(string $k, $d = '') => $event[$k] ?? $d;
$currentTagIds = [];
if (!$isNew) {
    foreach (event_tags($id) as $t) $currentTagIds[] = (int)$t['id'];
}
// If POST failed, keep user-submitted tags
if (!empty($_POST['tags'])) {
    $currentTagIds = array_map('intval', $_POST['tags']);
}

$pageTitle = ($isNew ? 'New Event' : 'Edit Event') . ' | Sherwood Events Admin';
include __DIR__ . '/../_partials/head.php';
include __DIR__ . '/../_partials/nav.php';

// datetime-local wants YYYY-MM-DDTHH:MM
$dt_local = function($v) {
    if (!$v) return '';
    return (new DateTimeImmutable($v))->format('Y-m-d\TH:i');
};
?>

<main class="content-body admin-page admin-edit" id="main-content">
  <p><a href="/admin/">&larr; Back to events</a></p>
  <h1><?= $isNew ? 'New Event' : 'Edit Event' ?></h1>

  <?php if ($errors): ?>
    <div class="flash flash-error">
      <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>

    <fieldset>
      <legend>Basics</legend>

      <label>Title
        <input type="text" name="title" required maxlength="200" value="<?= e((string)$v('title')) ?>">
      </label>

      <label>Slug (URL) — optional, auto-generated from title if blank
        <input type="text" name="slug" maxlength="120" value="<?= e((string)$v('slug')) ?>"
               placeholder="summer-open-2026">
      </label>

      <label>Description
        <textarea name="description" rows="8" required><?= e((string)$v('description')) ?></textarea>
      </label>
    </fieldset>

    <fieldset>
      <legend>When</legend>
      <div class="form-row">
        <label>Start
          <input type="datetime-local" name="start_datetime" required
                 value="<?= e($dt_local($v('start_datetime'))) ?>">
        </label>
        <label>End (optional)
          <input type="datetime-local" name="end_datetime"
                 value="<?= e($dt_local($v('end_datetime'))) ?>">
        </label>
      </div>
      <label class="checkbox">
        <input type="checkbox" name="all_day" value="1" <?= $v('all_day') ? 'checked' : '' ?>>
        All-day event
      </label>
    </fieldset>

    <fieldset>
      <legend>Where</legend>
      <label>Location name
        <input type="text" name="location_name" maxlength="200" value="<?= e((string)$v('location_name')) ?>">
      </label>
      <label>Address
        <input type="text" name="location_addr" maxlength="300" value="<?= e((string)$v('location_addr')) ?>">
      </label>
      <label>Map URL (Google Maps, Apple Maps, etc.)
        <input type="url" name="map_url" maxlength="500" value="<?= e((string)$v('map_url')) ?>">
      </label>
    </fieldset>

    <fieldset>
      <legend>Links</legend>
      <label>Event host's website
        <input type="url" name="event_site_url" maxlength="500" value="<?= e((string)$v('event_site_url')) ?>">
      </label>
      <label>Ticket URL (Eventbrite, signup app, etc.) — leave blank to use built-in RSVP
        <input type="url" name="ticket_url" maxlength="500" value="<?= e((string)$v('ticket_url')) ?>">
      </label>
    </fieldset>

    <fieldset>
      <legend>Image</legend>
      <?php if ($v('image_path')): ?>
        <div class="current-image">
          <img src="<?= e(event_image_src($v('image_path'))) ?>" alt="" style="max-width:240px;max-height:160px;border-radius:8px;">
          <label class="checkbox"><input type="checkbox" name="clear_image" value="1"> Remove current image</label>
        </div>
      <?php endif; ?>

      <label>Upload a new image (JPEG / PNG / WEBP, max <?= (UPLOAD_MAX_BYTES/1024/1024) ?> MB)
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
      </label>

      <label>…or paste an image URL
        <input type="url" name="image_url" placeholder="https://..." maxlength="500"
               value="<?= preg_match('#^https?://#i', (string)$v('image_path', '')) ? e((string)$v('image_path')) : '' ?>">
      </label>

      <label>Image alt text (for accessibility)
        <input type="text" name="image_alt" maxlength="200" value="<?= e((string)$v('image_alt')) ?>">
      </label>
    </fieldset>

    <fieldset>
      <legend>Tags</legend>
      <div class="tag-checkboxes">
        <?php foreach ($allTags as $t): ?>
          <label class="checkbox">
            <input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>"
              <?= in_array((int)$t['id'], $currentTagIds, true) ? 'checked' : '' ?>>
            <?= e($t['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset>
      <legend>Publishing</legend>
      <div class="form-row">
        <label>Status
          <select name="status">
            <?php foreach (['published','draft','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $v('status')===$s ? 'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="checkbox">
          <input type="checkbox" name="featured" value="1" <?= $v('featured') ? 'checked' : '' ?>>
          Pin as featured (appears above the regular list)
        </label>
      </div>
    </fieldset>

    <fieldset>
      <legend>RSVP</legend>
      <label class="checkbox">
        <input type="checkbox" name="rsvp_enabled" value="1" <?= $v('rsvp_enabled') ? 'checked' : '' ?>>
        Accept RSVPs on the event page
      </label>
      <label>Capacity (blank = unlimited)
        <input type="number" name="rsvp_capacity" min="0" value="<?= e((string)$v('rsvp_capacity', '')) ?>">
      </label>
    </fieldset>

    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Save</button>
      <a href="/admin/" class="btn">Cancel</a>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../_partials/footer.php'; ?>
