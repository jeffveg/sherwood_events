<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/events.php';
require_once __DIR__ . '/../src/rsvp.php';

$slug = (string)($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    exit('Event not found.');
}

$event = event_find_by_slug($slug);
if (!$event) {
    http_response_code(404);
    include __DIR__ . '/_partials/head.php';
    include __DIR__ . '/_partials/nav.php';
    echo '<main class="content-body"><h1>Event not found</h1><p><a href="/">Back to upcoming events</a></p></main>';
    include __DIR__ . '/_partials/footer.php';
    exit;
}

$tags = event_tags((int)$event['id']);

// Build iCal URL and Google Calendar URL for this event
$startUtc = (new DateTimeImmutable($event['start_datetime'], new DateTimeZone(TIMEZONE)))
            ->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
$endUtc   = $event['end_datetime']
    ? (new DateTimeImmutable($event['end_datetime'], new DateTimeZone(TIMEZONE)))
      ->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z')
    : (new DateTimeImmutable($event['start_datetime'], new DateTimeZone(TIMEZONE)))
      ->modify('+2 hours')->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');

$gcalUrl = 'https://calendar.google.com/calendar/render?' . http_build_query([
    'action'   => 'TEMPLATE',
    'text'     => $event['title'],
    'dates'    => $startUtc . '/' . $endUtc,
    'details'  => strip_tags($event['description']),
    'location' => trim(($event['location_name'] ?? '') . ' ' . ($event['location_addr'] ?? '')),
    'sprop'    => 'website:' . SITE_URL,
]);

$icalUrl  = '/event.ics.php?slug=' . urlencode($event['slug']);
$shareUrl = url('/event.php?slug=' . $event['slug']);

$pageTitle       = $event['title'] . ' | Sherwood Adventure';
$pageDescription = short_desc($event['description'], 160);
$pageCanonical   = $shareUrl;
$pageImage       = event_image_src($event['image_path']);
// Tell Google to drop cancelled events from search results — the page
// still works for visitors who have the URL bookmarked or got it in
// an email, but we don't want it as an evergreen search hit.
$pageRobots      = $event['status'] === 'cancelled' ? 'noindex, follow' : 'index, follow';

// JSON-LD
$jsonLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'Event',
    'name'       => $event['title'],
    'description'=> strip_tags($event['description']),
    'startDate'  => (new DateTimeImmutable($event['start_datetime'], new DateTimeZone(TIMEZONE)))->format(DateTime::ATOM),
    'endDate'    => $event['end_datetime']
        ? (new DateTimeImmutable($event['end_datetime'], new DateTimeZone(TIMEZONE)))->format(DateTime::ATOM)
        : null,
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'eventStatus' => $event['status'] === 'cancelled'
        ? 'https://schema.org/EventCancelled'
        : 'https://schema.org/EventScheduled',
    'location' => [
        '@type' => 'Place',
        'name'  => $event['location_name'] ?: 'Phoenix Metro Area',
        'address' => $event['location_addr'] ?: 'Phoenix, AZ',
    ],
    'image'     => $pageImage,
    'url'       => $shareUrl,
    'organizer' => [
        '@type' => 'Organization',
        'name'  => 'Sherwood Adventure',
        'url'   => MAIN_SITE_URL,
    ],
];
if ($event['ticket_url']) {
    $jsonLd['offers'] = [
        '@type' => 'Offer',
        'url'   => $event['ticket_url'],
        'availability' => 'https://schema.org/InStock',
    ];
}

include __DIR__ . '/_partials/head.php';
?>
<script type="application/ld+json"><?= json_encode(array_filter($jsonLd), JSON_UNESCAPED_SLASHES) ?></script>
<?php include __DIR__ . '/_partials/nav.php'; ?>

<main class="content-body event-detail" id="main-content">

  <?php include __DIR__ . '/_partials/flashes.php'; ?>

  <?php if ($event['status'] === 'cancelled'): ?>
    <div class="event-cancelled-banner"><strong>This event has been cancelled.</strong></div>
  <?php endif; ?>

  <article class="event-detail-card">
    <div class="event-detail-hero">
      <img src="<?= e($pageImage) ?>" alt="<?= e($event['image_alt'] ?: $event['title']) ?>">
    </div>

    <div class="event-detail-body">
      <?php if ($tags): ?>
        <div class="event-tags">
          <?php foreach ($tags as $t): $tc = safe_css_color($t['color']); ?>
            <span class="tag-pill tag-pill--sm" <?= $tc ? 'style="--tag-color: '.e($tc).'"' : '' ?>>
              <?= e($t['name']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h1><?= e($event['title']) ?></h1>
      <p class="event-detail-when"><?= e(fmt_event_when($event)) ?></p>

      <?php if (!empty($event['location_name']) || !empty($event['location_addr'])): ?>
        <p class="event-detail-where">
          <?= e($event['location_name']) ?>
          <?php if (!empty($event['location_addr'])): ?>
            <br><span class="event-detail-addr"><?= e($event['location_addr']) ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <div class="event-detail-desc"><?= nl2br(e($event['description'])) ?></div>

      <div class="event-detail-actions">
        <?php if (!empty($event['map_url'])): ?>
          <a class="btn" href="<?= e($event['map_url']) ?>" target="_blank" rel="noopener">Open Map &#8599;</a>
        <?php endif; ?>
        <?php if (!empty($event['event_site_url'])): ?>
          <a class="btn" href="<?= e($event['event_site_url']) ?>" target="_blank" rel="noopener">Event Site &#8599;</a>
        <?php endif; ?>
        <?php if (!empty($event['ticket_url'])): ?>
          <a class="btn btn-gold" href="<?= e($event['ticket_url']) ?>" target="_blank" rel="noopener">Get Tickets &#8599;</a>
        <?php endif; ?>
        <a class="btn" href="<?= e($gcalUrl) ?>" target="_blank" rel="noopener">Add to Google Calendar</a>
        <a class="btn" href="<?= e($icalUrl) ?>">Download .ics</a>
      </div>

      <div class="share-row" aria-label="Share this event">
        <span class="share-row-label">Share:</span>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener">Facebook</a>
        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($event['title']) ?>" target="_blank" rel="noopener">X</a>
        <a href="sms:?body=<?= rawurlencode($event['title'] . ' — ' . $shareUrl) ?>">SMS</a>
        <a href="mailto:?subject=<?= urlencode($event['title']) ?>&body=<?= urlencode($event['title'] . "\n\n" . $shareUrl) ?>">Email</a>
      </div>
    </div>
  </article>

  <?php if (!empty($event['rsvp_enabled']) && $event['status'] !== 'cancelled'): ?>
    <section class="rsvp-block" id="rsvp">
      <h2>RSVP</h2>
      <?php
        $count = rsvp_attendee_count((int)$event['id']);
        $cap   = $event['rsvp_capacity'];
        $full  = $cap !== null && $count >= (int)$cap;
      ?>
      <?php if ($cap): ?>
        <p class="rsvp-count"><?= (int)$count ?> of <?= (int)$cap ?> spots taken.</p>
      <?php else: ?>
        <p class="rsvp-count"><?= (int)$count ?> people attending.</p>
      <?php endif; ?>

      <?php if (!empty($_GET['rsvped'])): ?>
        <div class="rsvp-success">Thanks! We've got you down. See you there.</div>
      <?php elseif (!empty($_GET['already'])): ?>
        <div class="rsvp-success">Looks like you're already on the list for this event. See you there!</div>
      <?php elseif ($full): ?>
        <p><strong>This event is full.</strong> Check back — spots sometimes open up.</p>
      <?php else: ?>
        <form class="rsvp-form" method="POST" action="/rsvp.php">
          <?= csrf_field() ?>
          <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
          <!-- Honeypot -->
          <div class="honeypot" aria-hidden="true">
            <label>Leave this blank
              <input type="text" name="_gotcha" tabindex="-1" autocomplete="off">
            </label>
          </div>
          <input type="hidden" name="_ts" value="<?= (int)(microtime(true)*1000) ?>">
          <div class="form-row">
            <label>Name
              <input type="text" name="name" required maxlength="120">
            </label>
            <label>Email
              <input type="email" name="email" required maxlength="200">
            </label>
          </div>
          <div class="form-row">
            <label>Phone (optional)
              <input type="tel" name="phone" maxlength="40">
            </label>
            <label>How many in your party?
              <input type="number" name="party_size" value="1" min="1" max="20">
            </label>
          </div>
          <label>Notes (optional)
            <textarea name="notes" rows="2" maxlength="500"></textarea>
          </label>
          <button type="submit" class="btn btn-gold">Count Me In</button>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php include __DIR__ . '/_partials/book-cta.php'; ?>

  <p class="back-link"><a href="/">&larr; All upcoming events</a></p>

</main>

<?php include __DIR__ . '/_partials/footer.php'; ?>
