<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/events.php';

$slug  = (string)($_GET['slug'] ?? '');
$event = $slug ? event_find_by_slug($slug) : null;
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $event['slug'] . '.ics"');

function ical_escape(string $s): string {
    return str_replace(["\\", ",", ";", "\r\n", "\r", "\n"],
                       ["\\\\", "\\,", "\\;", "\\n", "\\n", "\\n"], $s);
}
function ical_dt(string $dt): string {
    return (new DateTimeImmutable($dt, new DateTimeZone(TIMEZONE)))
        ->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

$url = url('/event.php?slug=' . $event['slug']);
$loc = trim(($event['location_name'] ?? '') . (($event['location_addr'] ?? '') ? ', ' . $event['location_addr'] : ''));
$end = $event['end_datetime']
    ?: (new DateTimeImmutable($event['start_datetime']))->modify('+2 hours')->format('Y-m-d H:i:s');

$out = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Sherwood Adventure//Events//EN",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
    "BEGIN:VEVENT",
    "UID:event-" . $event['id'] . '@events.sherwoodadventure.com',
    "DTSTAMP:" . gmdate('Ymd\THis\Z'),
    "DTSTART:" . ical_dt($event['start_datetime']),
    "DTEND:"   . ical_dt($end),
    "SUMMARY:" . ical_escape($event['title']),
    "DESCRIPTION:" . ical_escape(strip_tags($event['description']) . "\n\n" . $url),
    $loc ? "LOCATION:" . ical_escape($loc) : null,
    "URL:" . $url,
    $event['status'] === 'cancelled' ? "STATUS:CANCELLED" : null,
    "END:VEVENT",
    "END:VCALENDAR",
];

// RFC 5545: fold any content line longer than 75 octets.
echo implode("\r\n", array_map('ical_fold', array_filter($out)));
