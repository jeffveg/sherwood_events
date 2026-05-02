<?php
/**
 * Dynamic sitemap — events.sherwoodadventure.com/sitemap.xml
 *
 * Lists the homepage plus every published event so search engines
 * (Google, Bing) can discover them. <lastmod> is set from each
 * event's updated_at so re-edits prompt re-crawl.
 *
 * Pretty URL /sitemap.xml → sitemap.xml.php via public/.htaccess.
 * Referenced by robots.txt with the Sitemap: directive.
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/events.php';

header('Content-Type: application/xml; charset=utf-8');

$events = db()->query("
    SELECT slug, updated_at FROM events
    WHERE status = 'published'
")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= e(SITE_URL) ?>/</loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <?php foreach ($events as $ev): ?>
    <url>
      <loc><?= e(url('/event.php?slug=' . $ev['slug'])) ?></loc>
      <lastmod><?= (new DateTimeImmutable($ev['updated_at']))->format('Y-m-d') ?></lastmod>
    </url>
  <?php endforeach; ?>
</urlset>
