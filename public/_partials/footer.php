<footer class="site-footer">
  <div class="footer-inner">
    <img src="<?= e(MAIN_LOGO_URL) ?>" alt="Sherwood Adventure" class="footer-logo" width="200" height="107">
    <p class="footer-contact">
      <a href="tel:6232272233">623-227-2233</a> &nbsp;|&nbsp;
      <a href="<?= e(MAIN_SITE_URL) ?>/contact.html">Contact Us</a>
    </p>
    <div class="footer-social">
      <a href="https://www.facebook.com/SherwoodAdventure/" target="_blank" rel="noopener noreferrer">Facebook</a>
      <a href="https://www.instagram.com/sherwoodadventure" target="_blank" rel="noopener noreferrer">Instagram</a>
      <a href="https://x.com/Sherwood_Advent" target="_blank" rel="noopener noreferrer">X</a>
    </div>
    <nav aria-label="Footer navigation">
      <ul class="footer-nav">
        <li><a href="<?= e(BOOKING_URL) ?>" target="_blank" rel="noopener noreferrer">Book Your Adventure</a></li>
        <li><a href="<?= e(SITE_URL) ?>/">Upcoming Events</a></li>
        <li><a href="<?= e(MAIN_SITE_URL) ?>/host-a-tournament.html">Host a Tournament</a></li>
        <li><a href="https://signup.sherwoodadventure.com" target="_blank" rel="noopener">Current Tournaments</a></li>
        <li><a href="<?= e(MAIN_SITE_URL) ?>/vendor/">Festival &amp; Large Events</a></li>
        <li><a href="<?= e(MAIN_SITE_URL) ?>/email-signup.html">Email Signup</a></li>
        <li><a href="<?= e(MAIN_SITE_URL) ?>/resources.html">Resources</a></li>
        <li><a href="<?= e(MAIN_SITE_URL) ?>/terms.html">Terms &amp; Conditions</a></li>
        <li><a href="<?= e(MAIN_SITE_URL) ?>/contact.html">Contact Us</a></li>
        <li><a href="/events.ics">iCal Feed</a></li>
      </ul>
    </nav>
    <p class="footer-copy">&copy; <?= date('Y') ?> Sherwood Adventure LLC &mdash; All rights reserved.</p>
  </div>
</footer>
<script src="<?= e(MAIN_SITE_URL) ?>/js/main.js" defer></script>
<script src="/assets/js/events.js" defer></script>
</body>
</html>
