<?php
declare(strict_types=1);

/**
 * rsvp.php — query layer for RSVPs.
 *
 * Most functions are straightforward CRUD; the interesting one is
 * rsvp_rate_limited() which gates the public RSVP form. The IP
 * hashing helper (client_ip_hash) lives in helpers.php since both
 * RSVPs and admin login use it.
 *
 * The rsvps table has columns prepared for paid ticketing
 * (ticket_tier, amount_cents, payment_status, payment_ref) but
 * those are currently always NULL/'none'. See ARCHITECTURE.md for
 * the future-tickets-via-Stripe sketch.
 */

function rsvp_count(int $eventId): int
{
    $st = db()->prepare("SELECT COUNT(*) AS c FROM rsvps WHERE event_id = :e AND payment_status <> 'refunded'");
    $st->execute(['e' => $eventId]);
    return (int)($st->fetch()['c'] ?? 0);
}

function rsvp_attendee_count(int $eventId): int
{
    $st = db()->prepare("SELECT COALESCE(SUM(party_size),0) AS s FROM rsvps
                         WHERE event_id = :e AND payment_status <> 'refunded'");
    $st->execute(['e' => $eventId]);
    return (int)($st->fetch()['s'] ?? 0);
}

function rsvp_list(int $eventId): array
{
    $st = db()->prepare("
        SELECT * FROM rsvps WHERE event_id = :e ORDER BY created_at ASC
    ");
    $st->execute(['e' => $eventId]);
    return $st->fetchAll();
}

function rsvp_create(array $data): int
{
    $st = db()->prepare("
        INSERT INTO rsvps
          (event_id, name, email, phone, party_size, notes,
           ticket_tier, amount_cents, payment_status, payment_ref, ip_hash)
        VALUES
          (:event_id, :name, :email, :phone, :party_size, :notes,
           :ticket_tier, :amount_cents, :payment_status, :payment_ref, :ip_hash)
    ");
    $st->execute($data);
    return (int)db()->lastInsertId();
}

/**
 * Rudimentary rate limiting: no more than 5 RSVPs from the same IP hash
 * in the last 10 minutes.
 */
function rsvp_rate_limited(string $ipHash): bool
{
    $st = db()->prepare("
        SELECT COUNT(*) AS c FROM rsvps
        WHERE ip_hash = :h AND created_at > (NOW() - INTERVAL 10 MINUTE)
    ");
    $st->execute(['h' => $ipHash]);
    return (int)($st->fetch()['c'] ?? 0) >= 5;
}
