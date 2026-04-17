-- Sample data for local/dev testing only. Do NOT run in production.

INSERT INTO events
  (slug, title, description, start_datetime, end_datetime, all_day,
   location_name, location_addr, map_url, event_site_url, ticket_url,
   image_path, image_alt, status, featured, rsvp_enabled, rsvp_capacity)
VALUES
  ('summer-open-2026',
   'Sherwood Summer Open 2026',
   'Our flagship summer archery tag tournament. Five-on-five brackets, cash prizes, food trucks. Bring a team or sign up solo and we will place you.',
   '2026-06-14 16:00:00', '2026-06-14 22:00:00', 0,
   'Sherwood Park',
   '123 Sherwood Way, Glendale, AZ',
   'https://maps.google.com/?q=Sherwood+Park+Glendale+AZ',
   NULL,
   'https://signup.sherwoodadventure.com',
   'https://sherwoodadventure.com/images/hero/archery-field.jpg',
   'Archery tag tournament field',
   'published', 1, 1, 120),

  ('community-day-may',
   'Free Community Archery Day',
   'Bring the whole family. Free 15-minute sessions on the archery tag field. No experience needed — we provide all gear and coaching.',
   '2026-05-03 10:00:00', '2026-05-03 14:00:00', 0,
   'Westgate Entertainment District',
   '6770 N Sunrise Blvd, Glendale, AZ',
   'https://maps.google.com/?q=Westgate+Entertainment+District+Glendale+AZ',
   NULL, NULL, NULL, NULL,
   'published', 0, 1, NULL),

  ('st-marys-youth-night',
   'St. Mary''s Youth Group Archery Night',
   'Private event for St. Mary''s youth group, but publicly listed for awareness. Not open to the public.',
   '2026-04-28 18:30:00', '2026-04-28 20:30:00', 0,
   'St. Mary''s Catholic Church',
   '231 N 3rd St, Phoenix, AZ',
   'https://maps.google.com/?q=St+Marys+Phoenix+AZ',
   'https://stmarysphx.org',
   NULL, NULL, NULL,
   'published', 0, 0, NULL);

-- Tag the seeded events
INSERT IGNORE INTO event_tags (event_id, tag_id)
  SELECT e.id, t.id FROM events e JOIN tags t
  ON (e.slug='summer-open-2026'  AND t.slug='tournament')
  OR (e.slug='community-day-may' AND t.slug='community-day')
  OR (e.slug='st-marys-youth-night' AND t.slug IN ('church','youth'));
