<?php
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
