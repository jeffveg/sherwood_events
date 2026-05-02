<?php
/** Expects: $pageTitle (string), $pageDescription (string),
 *           $pageCanonical (?string), $pageImage (?string), $pageRobots (?string) */
$pageTitle       = $pageTitle       ?? SITE_NAME;
$pageDescription = $pageDescription ?? SITE_TAGLINE;
$pageCanonical   = $pageCanonical   ?? url($_SERVER['REQUEST_URI'] ?? '/');
$pageImage       = $pageImage       ?? FALLBACK_EVENT_IMAGE;
$pageRobots      = $pageRobots      ?? 'index, follow';
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="robots" content="<?= e($pageRobots) ?>">
<link rel="canonical" href="<?= e($pageCanonical) ?>">
<link rel="shortcut icon" type="image/x-icon" href="<?= e(MAIN_SITE_URL) ?>/favicon.ico">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($pageCanonical) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:image" content="<?= e($pageImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e($pageImage) ?>">

<!-- Brand tokens + main-site styles are hosted on the main domain -->
<link rel="stylesheet" href="<?= e(MAIN_SITE_URL) ?>/css/brand.css">
<link rel="stylesheet" href="<?= e(MAIN_SITE_URL) ?>/css/style.css">
<!-- Events-specific overrides and components -->
<link rel="stylesheet" href="/assets/css/events.css">

<!-- Calendar / RSS discovery -->
<link rel="alternate" type="application/rss+xml" title="Sherwood Events RSS" href="/events.rss">
</head>
<body>
<a href="#main-content" class="skip-nav">Skip to main content</a>
