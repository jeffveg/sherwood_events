<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/events.php';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="sherwood-events.ics"');

$events = db()->query("
    SELECT * FROM events
    WHERE status = 'published'
      AND (end_datetime >= NOW() OR (end_datetime IS NULL AND start_datetime >= NOW() - INTERVAL 30 DAY))
    ORDER BY start_datetime ASC
")->fetchAll();

function ical_escape(string $s): string {
    $s = str_replace(["\\", ",", ";", "\r\n", "\r", "\n"], ["\\\\", "\\,", "\\;", "\\n", "\\n", "\\n"], $s);
    return $s;
}

function ical_dt(string $dt): string {
    return (new DateTimeImmutable($dt, new DateTimeZone(TIMEZONE)))
        ->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

$out = [];
$out[] = "BEGIN:VCALENDAR";
$out[] = "VERSION:2.0";
$out[] = "PRODID:-//Sherwood Adventure//Events//EN";
$out[] = "CALSCALE:GREGORIAN";
$out[] = "METHOD:PUBLISH";
$out[] = "X-WR-CALNAME:Sherwood Adventure Events";
$out[] = "X-WR-TIMEZONE:" . TIMEZONE;

foreach ($events as $ev) {
    $uid  = 'event-' . $ev['id'] . '@events.sherwoodadventure.com';
    $url  = url('/event.php?slug=' . $ev['slug']);
    $loc  = trim(($ev['location_name'] ?? '') . (($ev['location_addr'] ?? '') ? ', ' . $ev['location_addr'] : ''));
    $desc = strip_tags($ev['description']) . "\n\n" . $url;

    $out[] = "BEGIN:VEVENT";
    $out[] = "UID:" . $uid;
    $out[] = "DTSTAMP:" . gmdate('Ymd\THis\Z');
    $out[] = "DTSTART:" . ical_dt($ev['start_datetime']);
    if ($ev['end_datetime']) {
        $out[] = "DTEND:" . ical_dt($ev['end_datetime']);
    } else {
        // Default 2-hour event when no end specified
        $end = (new DateTimeImmutable($ev['start_datetime'], new DateTimeZone(TIMEZONE)))->modify('+2 hours');
        $out[] = "DTEND:" . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
    $out[] = "SUMMARY:" . ical_escape($ev['title']);
    $out[] = "DESCRIPTION:" . ical_escape($desc);
    if ($loc) {
        $out[] = "LOCATION:" . ical_escape($loc);
    }
    $out[] = "URL:" . $url;
    if ($ev['status'] === 'cancelled') {
        $out[] = "STATUS:CANCELLED";
    }
    $out[] = "END:VEVENT";
}

$out[] = "END:VCALENDAR";

echo implode("\r\n", $out);
