<?php
/**
 * RSS 2.0 feed — events.sherwoodadventure.com/events.rss
 *
 * RSS-readers and integrations (Zapier, IFTTT) consume this. Mirrors
 * the public list (events_public_upcoming) so subscribers see exactly
 * what's on the website. Pretty URL via public/.htaccess.
 *
 * <atom:link rel="self"> is included for feed-validator compliance.
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/events.php';

header('Content-Type: application/rss+xml; charset=utf-8');

$events = events_public_upcoming();

$now = (new DateTimeImmutable('now'))->format(DateTime::RSS);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= e(SITE_NAME) ?></title>
    <link><?= e(SITE_URL) ?>/</link>
    <description><?= e(SITE_TAGLINE) ?></description>
    <language>en-us</language>
    <lastBuildDate><?= $now ?></lastBuildDate>
    <atom:link href="<?= e(url('/events.rss')) ?>" rel="self" type="application/rss+xml" />
    <?php foreach ($events as $ev): ?>
      <item>
        <title><?= e($ev['title']) ?></title>
        <link><?= e(url('/event.php?slug=' . $ev['slug'])) ?></link>
        <guid isPermaLink="true"><?= e(url('/event.php?slug=' . $ev['slug'])) ?></guid>
        <pubDate><?= (new DateTimeImmutable($ev['created_at']))->format(DateTime::RSS) ?></pubDate>
        <description><![CDATA[
          <p><strong><?= e(fmt_event_when($ev)) ?></strong>
          <?php if (!empty($ev['location_name'])): ?> &middot; <?= e($ev['location_name']) ?><?php endif; ?></p>
          <p><?= e(short_desc($ev['description'])) ?></p>
        ]]></description>
      </item>
    <?php endforeach; ?>
  </channel>
</rss>
