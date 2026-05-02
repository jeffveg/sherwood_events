<?php
declare(strict_types=1);

/**
 * Event queries. All take a PDO connection implicitly via db().
 */

/**
 * "Upcoming" rule, used by both the public list and the iCal feed:
 *   - has an end_datetime in the future, OR
 *   - has no end_datetime and started within the last 4 hours
 *
 * The 4-hour grace period covers the common "Saturday 6 PM" case where
 * admin didn't set an end time but the event is actually still happening.
 * Events with explicit end times are exact.
 */
const UPCOMING_GRACE_HOURS = 4;

function events_public_upcoming(?int $tagId = null): array
{
    // UPCOMING_GRACE_HOURS is a defined constant (not user input), so it's
    // safe to inline. INTERVAL X HOUR doesn't accept prepared-statement
    // placeholders reliably across MariaDB versions.
    $grace = (int)UPCOMING_GRACE_HOURS;

    $sql = "SELECT e.* FROM events e ";
    $params = [];
    if ($tagId) {
        $sql .= "INNER JOIN event_tags et ON et.event_id = e.id AND et.tag_id = :tag ";
        $params['tag'] = $tagId;
    }
    $sql .= "WHERE e.status = 'published'
             AND (
                  e.end_datetime >= NOW()
               OR (e.end_datetime IS NULL
                   AND e.start_datetime >= NOW() - INTERVAL $grace HOUR)
             )
             ORDER BY e.featured DESC, e.start_datetime ASC";

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function events_public_past(int $limit = 20): array
{
    $st = db()->prepare("
        SELECT * FROM events
        WHERE status = 'published'
          AND (end_datetime < NOW() OR (end_datetime IS NULL AND start_datetime < NOW()))
        ORDER BY start_datetime DESC
        LIMIT :lim
    ");
    $st->bindValue('lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function event_find_by_slug(string $slug): ?array
{
    $st = db()->prepare("SELECT * FROM events WHERE slug = :slug AND status <> 'draft'");
    $st->execute(['slug' => $slug]);
    $row = $st->fetch();
    return $row ?: null;
}

function event_find_by_id(int $id): ?array
{
    $st = db()->prepare("SELECT * FROM events WHERE id = :id");
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
}

function events_admin_all(): array
{
    return db()->query("
        SELECT e.*,
               (SELECT COUNT(*) FROM rsvps r WHERE r.event_id = e.id) AS rsvp_count
        FROM events e
        ORDER BY e.start_datetime DESC
    ")->fetchAll();
}

// -----------------------------------------------------------------------------
// Mutations
// -----------------------------------------------------------------------------
function event_create(array $data): int
{
    $st = db()->prepare("
        INSERT INTO events
          (slug, title, description, start_datetime, end_datetime, all_day,
           location_name, location_addr, map_url, event_site_url, ticket_url,
           image_path, image_alt, status, featured, rsvp_enabled, rsvp_capacity)
        VALUES
          (:slug, :title, :description, :start_datetime, :end_datetime, :all_day,
           :location_name, :location_addr, :map_url, :event_site_url, :ticket_url,
           :image_path, :image_alt, :status, :featured, :rsvp_enabled, :rsvp_capacity)
    ");
    $st->execute($data);
    return (int)db()->lastInsertId();
}

/**
 * Insert an event coming in via /api/intake.php, with the originating
 * system's reference stored in intake_ref (used as the idempotency key).
 */
function event_create_with_intake_ref(array $data, string $intakeRef): int
{
    $data['intake_ref'] = $intakeRef;
    $st = db()->prepare("
        INSERT INTO events
          (slug, title, description, start_datetime, end_datetime, all_day,
           location_name, location_addr, map_url, event_site_url, ticket_url,
           image_path, image_alt, status, featured, rsvp_enabled, rsvp_capacity,
           intake_ref)
        VALUES
          (:slug, :title, :description, :start_datetime, :end_datetime, :all_day,
           :location_name, :location_addr, :map_url, :event_site_url, :ticket_url,
           :image_path, :image_alt, :status, :featured, :rsvp_enabled, :rsvp_capacity,
           :intake_ref)
    ");
    $st->execute($data);
    return (int)db()->lastInsertId();
}

function event_find_by_intake_ref(string $intakeRef): ?array
{
    $st = db()->prepare("SELECT * FROM events WHERE intake_ref = :r LIMIT 1");
    $st->execute(['r' => $intakeRef]);
    $row = $st->fetch();
    return $row ?: null;
}

function event_update(int $id, array $data): void
{
    $data['id'] = $id;
    $st = db()->prepare("
        UPDATE events SET
          slug=:slug, title=:title, description=:description,
          start_datetime=:start_datetime, end_datetime=:end_datetime, all_day=:all_day,
          location_name=:location_name, location_addr=:location_addr,
          map_url=:map_url, event_site_url=:event_site_url, ticket_url=:ticket_url,
          image_path=:image_path, image_alt=:image_alt,
          status=:status, featured=:featured,
          rsvp_enabled=:rsvp_enabled, rsvp_capacity=:rsvp_capacity
        WHERE id=:id
    ");
    $st->execute($data);
}

function event_delete(int $id): void
{
    // Soft-delete: mark cancelled, keep RSVPs.
    $st = db()->prepare("UPDATE events SET status='cancelled' WHERE id=:id");
    $st->execute(['id' => $id]);
}

function slug_unique(string $base, ?int $ignoreId = null): string
{
    $slug = $base;
    $i = 1;
    $st = db()->prepare("SELECT id FROM events WHERE slug=:slug AND id <> :ignore");
    while (true) {
        $st->execute(['slug' => $slug, 'ignore' => $ignoreId ?? 0]);
        if (!$st->fetch()) {
            return $slug;
        }
        $i++;
        $slug = $base . '-' . $i;
    }
}

// -----------------------------------------------------------------------------
// Tags
// -----------------------------------------------------------------------------
function tags_all(): array
{
    return db()->query("SELECT * FROM tags ORDER BY name")->fetchAll();
}

function tag_find_by_slug(string $slug): ?array
{
    $st = db()->prepare("SELECT * FROM tags WHERE slug=:s");
    $st->execute(['s' => $slug]);
    $r = $st->fetch();
    return $r ?: null;
}

function event_tags(int $eventId): array
{
    $st = db()->prepare("
        SELECT t.* FROM tags t
        INNER JOIN event_tags et ON et.tag_id = t.id
        WHERE et.event_id = :e
        ORDER BY t.name
    ");
    $st->execute(['e' => $eventId]);
    return $st->fetchAll();
}

function event_set_tags(int $eventId, array $tagIds): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM event_tags WHERE event_id=:e")
            ->execute(['e' => $eventId]);
        if ($tagIds) {
            $st = $pdo->prepare("INSERT INTO event_tags (event_id, tag_id) VALUES (:e, :t)");
            foreach ($tagIds as $tid) {
                $st->execute(['e' => $eventId, 't' => (int)$tid]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// -----------------------------------------------------------------------------
// Bulk tag lookup for list view (avoid N+1)
// -----------------------------------------------------------------------------
function tags_for_events(array $eventIds): array
{
    if (!$eventIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($eventIds), '?'));
    $st = db()->prepare("
        SELECT et.event_id, t.id, t.slug, t.name, t.color
        FROM event_tags et
        INNER JOIN tags t ON t.id = et.tag_id
        WHERE et.event_id IN ($in)
        ORDER BY t.name
    ");
    $st->execute($eventIds);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(int)$row['event_id']][] = $row;
    }
    return $out;
}
