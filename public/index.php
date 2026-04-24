<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/events.php';
require_once __DIR__ . '/../src/rsvp.php';

// Tag filter (?tag=slug)
$activeTag = null;
if (!empty($_GET['tag'])) {
    $activeTag = tag_find_by_slug((string)$_GET['tag']);
}

$events   = events_public_upcoming($activeTag['id'] ?? null);
$past     = events_public_past(8);
$allTags  = tags_all();
$tagsByEv = tags_for_events(array_column($events, 'id'));

$pageTitle       = 'Upcoming Events | Sherwood Adventure';
$pageDescription = SITE_TAGLINE;

include __DIR__ . '/_partials/head.php';
?>
<?php include __DIR__ . '/_partials/nav.php'; ?>

<header class="page-header">
  <h1>Upcoming Events</h1>
  <p class="page-subtitle"><?= e(SITE_TAGLINE) ?></p>
</header>

<main class="content-body events-list-page" id="main-content">

  <?php if ($allTags): ?>
    <nav class="tag-filter" aria-label="Filter events by category">
      <a href="/" class="tag-pill <?= $activeTag ? '' : 'is-active' ?>">All</a>
      <?php foreach ($allTags as $t): $tc = safe_css_color($t['color']); ?>
        <a href="/?tag=<?= e($t['slug']) ?>"
           class="tag-pill <?= ($activeTag && $activeTag['id']==$t['id']) ? 'is-active' : '' ?>"
           <?= $tc ? 'style="--tag-color: '.e($tc).'"' : '' ?>>
          <?= e($t['name']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if (!$events): ?>
    <div class="events-placeholder">
      <p class="section-title">No Upcoming Events Just Yet</p>
      <p>
        Check back soon, or join the Golden Arrow Email List to be the first to
        hear about open events and tournaments.
      </p>
      <div class="btn-wrap" style="justify-content:center;">
        <a href="<?= e(MAIN_SITE_URL) ?>/email-signup.html" class="btn btn-gold">Join the Email List</a>
        <a href="<?= e(BOOKING_URL) ?>" class="btn" target="_blank" rel="noopener">Book Your Adventure</a>
      </div>
    </div>
  <?php else: ?>

    <?php
      $lastMonth = null;
      $featuredHeaderShown = false;
      $nonFeaturedShown = 0;
      foreach ($events as $ev):
        $month = fmt_month_key($ev['start_datetime']);
        $monthLabel = fmt_month_label($ev['start_datetime']);
        $showMonthHeader = !$ev['featured'] && $month !== $lastMonth;
        if ($showMonthHeader) {
            $lastMonth = $month;
        }
        $showFeaturedHeader = $ev['featured'] && !$featuredHeaderShown;
        if ($showFeaturedHeader) {
            $featuredHeaderShown = true;
        }
    ?>
      <?php if ($showFeaturedHeader): ?>
        <div class="month-header featured-header">Featured</div>
      <?php elseif ($showMonthHeader): ?>
        <div class="month-header"><?= e($monthLabel) ?></div>
      <?php endif; ?>

      <article class="event-card <?= $ev['featured'] ? 'is-featured' : '' ?>">
        <a class="event-card-img" href="/event.php?slug=<?= e($ev['slug']) ?>">
          <img src="<?= e(event_image_src($ev['image_path'])) ?>"
               alt="<?= e($ev['image_alt'] ?: $ev['title']) ?>"
               loading="lazy">
        </a>
        <div class="event-card-body">
          <?php $tags = $tagsByEv[$ev['id']] ?? []; ?>
          <?php if ($tags): ?>
            <div class="event-tags">
              <?php foreach ($tags as $t): $tc = safe_css_color($t['color']); ?>
                <span class="tag-pill tag-pill--sm" <?= $tc ? 'style="--tag-color: '.e($tc).'"' : '' ?>>
                  <?= e($t['name']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <h2 class="event-card-title">
            <a href="/event.php?slug=<?= e($ev['slug']) ?>"><?= e($ev['title']) ?></a>
          </h2>
          <p class="event-card-when"><?= e(fmt_event_when($ev)) ?></p>
          <?php if (!empty($ev['location_name'])): ?>
            <p class="event-card-where"><?= e($ev['location_name']) ?></p>
          <?php endif; ?>
          <p class="event-card-desc"><?= e(short_desc($ev['description'])) ?></p>

          <div class="event-card-btns">
            <a class="btn btn-sm" href="/event.php?slug=<?= e($ev['slug']) ?>">Details</a>
            <?php if (!empty($ev['map_url'])): ?>
              <a class="btn btn-sm" href="<?= e($ev['map_url']) ?>" target="_blank" rel="noopener">Map &#8599;</a>
            <?php endif; ?>
            <?php if (!empty($ev['event_site_url'])): ?>
              <a class="btn btn-sm" href="<?= e($ev['event_site_url']) ?>" target="_blank" rel="noopener">Event Site &#8599;</a>
            <?php endif; ?>
            <?php if (!empty($ev['ticket_url'])): ?>
              <a class="btn btn-sm btn-gold" href="<?= e($ev['ticket_url']) ?>" target="_blank" rel="noopener">Tickets &#8599;</a>
            <?php elseif (!empty($ev['rsvp_enabled'])): ?>
              <a class="btn btn-sm btn-gold" href="/event.php?slug=<?= e($ev['slug']) ?>#rsvp">RSVP</a>
            <?php endif; ?>
          </div>
        </div>
      </article>

      <?php
        // Inject booking CTA every 3 non-featured events
        if (!$ev['featured']) {
            $nonFeaturedShown++;
            if ($nonFeaturedShown % 3 === 0) {
                include __DIR__ . '/_partials/book-cta.php';
            }
        }
      ?>
    <?php endforeach; ?>

    <?php
      // Ensure at least one CTA appears even if <3 events
      if ($nonFeaturedShown > 0 && $nonFeaturedShown < 3) {
          include __DIR__ . '/_partials/book-cta.php';
      }
    ?>

  <?php endif; ?>

  <?php if ($past): ?>
    <details class="past-events">
      <summary>Past events</summary>
      <ul class="past-events-list">
        <?php foreach ($past as $p): ?>
          <li>
            <a href="/event.php?slug=<?= e($p['slug']) ?>">
              <span class="past-events-date"><?= e(fmt_date($p['start_datetime'])) ?></span>
              <span class="past-events-title"><?= e($p['title']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>

  <section class="subscribe-block">
    <h2>Stay in the loop</h2>
    <p>
      Subscribe to the calendar feed and never miss an event, or join the
      Golden Arrow Email List for early notice of new events and special promotions.
    </p>
    <div class="btn-wrap">
      <a class="btn" href="/events.ics">Subscribe (iCal)</a>
      <a class="btn" href="/events.rss">RSS Feed</a>
      <a class="btn btn-gold" href="<?= e(MAIN_SITE_URL) ?>/email-signup.html">Join the Email List</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/_partials/footer.php'; ?>
