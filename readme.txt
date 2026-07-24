=== Tabletop Events Calendar — Resources ===
Contributors: tabarccode
Tags: events, calendar, tabletop, resources, lending
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shops and organisers list terrain, tables, and spare dice/minis available to borrow for a session. Requires the Tabletop Events Calendar plugin.

== Description ==

A lot of clubs and shops have a cupboard full of terrain, a spare folding table, or a box of dice and minis nobody's using — this plugin gives them a simple public list of what's available to borrow, tied to their organiser identity on the calendar.

Listing something requires the same email address already on record as the organiser for one of their events — checked directly against the core plugin's own data, no separate login or verification queue. Every listing starts pending, same moderation model as an event submission, and the organiser gets a magic link to edit or remove their own listings afterwards, no account needed.

One shortcode:

* `[tabletop_organiser_resources organiser="123"]` — an organiser's full lending list, anchored on any one of their own event IDs, plus a form to list something new.

== Installation ==

1. Install and activate **Tabletop Events Calendar** first.
2. Upload the `tabletop-events-resources` folder to `/wp-content/plugins/` and activate it.
3. Add `[tabletop_organiser_resources organiser="123"]` to an organiser's profile page (or anywhere you want their lending list shown).
4. New listings land in **Events Calendar ▸ Resources** for approval.

== Frequently Asked Questions ==

= Does this need the Venues & Organisers plugin? =

No. It stands on its own, using the same "anchor on any one event ID" trick the core plugin's own `[tabletop_organiser_events]` shortcode uses — no dependency on any other companion plugin.

= Can anyone list an item under someone else's name? =

Only if they know the exact organiser email already on record for that event, which the submission is checked against before anything is accepted.

== Changelog ==

= 1.0.0 =
* Initial release: tres_resource CPT, organiser-anchored lending list, magic-link self-service management.
