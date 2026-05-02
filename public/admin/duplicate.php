<?php
/**
 * Admin duplicate — POST /admin/duplicate.php
 *
 * Creates a new draft event by copying everything from the source
 * (title, description, location, image, tags, RSVP settings) and
 * shifting the start/end +1 week. Lands as status='draft' so admin
 * can adjust the date or other details before publishing.
 *
 * This is the closest thing to a "recurring event" feature — works
 * fine for "weekly community day" or "next month's tournament" without
 * a real recurrence engine. For anything more complex, just duplicate
 * repeatedly and adjust each.
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
$src = $id ? event_find_by_id($id) : null;
if (!$src) {
    flash('error', 'Source event not found.');
    redirect('/admin/');
}

// Shift start/end by +1 week as a safe default
$shift = '+1 week';
$newStart = (new DateTimeImmutable($src['start_datetime']))->modify($shift)->format('Y-m-d H:i:s');
$newEnd   = $src['end_datetime']
    ? (new DateTimeImmutable($src['end_datetime']))->modify($shift)->format('Y-m-d H:i:s')
    : null;

$baseSlug  = slugify($src['title']) . '-copy';
$finalSlug = slug_unique($baseSlug);

$newId = event_create([
    'slug'           => $finalSlug,
    'title'          => $src['title'] . ' (copy)',
    'description'    => $src['description'],
    'start_datetime' => $newStart,
    'end_datetime'   => $newEnd,
    'all_day'        => (int)$src['all_day'],
    'location_name'  => $src['location_name'],
    'location_addr'  => $src['location_addr'],
    'map_url'        => $src['map_url'],
    'event_site_url' => $src['event_site_url'],
    'ticket_url'     => $src['ticket_url'],
    'image_path'     => $src['image_path'],
    'image_alt'      => $src['image_alt'],
    'status'         => 'draft',                 // always start duplicates as drafts
    'featured'       => 0,
    'rsvp_enabled'   => (int)$src['rsvp_enabled'],
    'rsvp_capacity'  => $src['rsvp_capacity'],
]);

// Copy tags
$tagIds = array_column(event_tags((int)$src['id']), 'id');
event_set_tags($newId, array_map('intval', $tagIds));

flash('success', 'Event duplicated as a draft. Adjust and publish when ready.');
redirect('/admin/edit.php?id=' . $newId);
