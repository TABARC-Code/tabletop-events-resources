# Tabletop Events Calendar — Resources

Shops and organisers list terrain, tables, and spare dice/minis available to borrow for a session, for [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar).

Requires [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) — this plugin does nothing without it.

## What it does

- `[tabletop_organiser_resources organiser="123"]` — an organiser's full lending list (terrain, tables, dice/minis, or anything else), anchored on any one of their own event IDs, plus a form to list something new.
- Listing an item requires the same email address already on record as the organiser for that event — checked directly against the core plugin's own data. No separate login, no verification queue.
- Every listing starts pending, same moderation model as an event submission.
- A magic link, emailed on submission, lets the organiser edit or remove their own listings afterwards — no account needed.
- An "Ask about this" button on each item relays a message to the organiser's real email without ever showing it publicly — the same private contact relay the Carpool and LFG plugins use.

## Why anchor on organiser identity rather than depend on the Venues plugin

Because the core plugin already has everything needed: an organiser email tied to at least one published event. Building this on top of Venues & Organisers would mean Resources only works if you've *also* installed a second companion plugin — breaking the whole point of the jigsaw approach, where each piece only ever depends on the core. Same "anchor on data that already exists" reasoning as the Reviews plugin's organiser widget.

## Licence

GPL v2 or later.
