<nav class="site-nav" role="navigation" aria-label="Main navigation">
  <div class="nav-inner">
    <a href="<?= e(MAIN_SITE_URL) ?>/" class="nav-brand" aria-label="Sherwood Adventure — Home">
      <img src="<?= e(MAIN_LOGO_URL) ?>" alt="Sherwood Adventure" class="nav-logo" width="160" height="48">
    </a>

    <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="navLinks" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>

    <ul class="nav-links" id="navLinks" role="list">
      <li class="dropdown">
        <button class="nav-link" aria-expanded="false" aria-haspopup="true">Events &#9660;</button>
        <ul class="dropdown-menu" role="menu">
          <li><a href="<?= e(SITE_URL) ?>/" role="menuitem">Upcoming Events</a></li>
          <li><a href="<?= e(MAIN_SITE_URL) ?>/vendor/" role="menuitem">Festival &amp; Large Events</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <button class="nav-link" aria-expanded="false" aria-haspopup="true">For Your Group &#9660;</button>
        <ul class="dropdown-menu" role="menu">
          <li><a href="<?= e(MAIN_SITE_URL) ?>/church.html" role="menuitem">Church &amp; Faith Groups</a></li>
          <li><a href="<?= e(MAIN_SITE_URL) ?>/youth-leaders.html" role="menuitem">Youth Leaders</a></li>
          <li><a href="<?= e(MAIN_SITE_URL) ?>/bachelor-party.html" role="menuitem">Bachelor &amp; Bachelorette</a></li>
          <li><a href="<?= e(MAIN_SITE_URL) ?>/corporate.html" role="menuitem">Corporate Events</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <button class="nav-link" aria-expanded="false" aria-haspopup="true">Tournaments &#9660;</button>
        <ul class="dropdown-menu" role="menu">
          <li><a href="<?= e(MAIN_SITE_URL) ?>/host-a-tournament.html" role="menuitem">Host a Tournament</a></li>
          <li><a href="https://signup.sherwoodadventure.com" role="menuitem" target="_blank" rel="noopener noreferrer">Current Tournaments &#8599;</a></li>
        </ul>
      </li>
      <li><a href="<?= e(MAIN_SITE_URL) ?>/contact.html" class="nav-link">Contact</a></li>
      <li class="dropdown">
        <button class="nav-link" aria-expanded="false" aria-haspopup="true">More &#9660;</button>
        <ul class="dropdown-menu" role="menu">
          <li><a href="<?= e(MAIN_SITE_URL) ?>/faq.html" role="menuitem">FAQ</a></li>
          <li><a href="<?= e(MAIN_SITE_URL) ?>/resources.html" role="menuitem">Resources</a></li>
          <li><a href="<?= e(MAIN_SITE_URL) ?>/email-signup.html" role="menuitem">Golden Arrow Email List</a></li>
          <li><a href="<?= e(MAIN_SITE_URL) ?>/terms.html" role="menuitem">Terms &amp; Conditions</a></li>
        </ul>
      </li>
      <li><a href="<?= e(BOOKING_URL) ?>" target="_blank" rel="noopener noreferrer" class="nav-cta">Book Your Adventure</a></li>
    </ul>
  </div>
</nav>
