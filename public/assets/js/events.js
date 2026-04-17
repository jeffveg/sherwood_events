/* Minimal progressive enhancement for events.sherwoodadventure.com
   The main site's main.js (loaded just before this) already handles
   nav toggle and dropdowns. This file is a fallback for when that
   fails to load and also adds a tiny bit of form convenience. */

(function () {
    // --- Fallback mobile nav toggle (only wires up if not already wired) ---
    var toggle = document.getElementById('navToggle');
    var links  = document.getElementById('navLinks');
    if (toggle && links && !toggle.dataset.wired) {
        toggle.dataset.wired = '1';
        toggle.addEventListener('click', function () {
            var open = links.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        // Tap-to-expand for dropdowns on mobile/touch
        document.querySelectorAll('.dropdown > .nav-link').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (window.matchMedia('(max-width: 860px)').matches) {
                    e.preventDefault();
                    btn.parentElement.classList.toggle('open');
                }
            });
        });
    }

    // --- When "all day" is checked in the admin form, blank the time fields ---
    var allDay = document.querySelector('input[name="all_day"]');
    if (allDay) {
        var start = document.querySelector('input[name="start_datetime"]');
        var end   = document.querySelector('input[name="end_datetime"]');
        allDay.addEventListener('change', function () {
            if (allDay.checked) {
                [start, end].forEach(function (el) {
                    if (el && el.value) el.value = el.value.slice(0, 10) + 'T00:00';
                });
            }
        });
    }
})();
