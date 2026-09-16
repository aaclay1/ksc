# KSC Report (standalone)

A plain PHP + MySQL rebuild of the Kearney Senior Center Report app — no WordPress,
no login required. Every page is a self-contained `.php` file that talks directly
to the database through `db.php`.

## Features

- **Sign-In** — manual, barcode-scan, and group sign-in, plus a sign-in search/print/export tool
- **Members** — add/edit/delete, barcode generation, membership card printing (single or all)
- **Activities** — manage activities, barcode generation, barcode printing
- **Activity Types** — manage activity categories
- **Report** — monthly rollup (visits, volunteers, hours, per-activity counts) with CSV export
- **Kearney Rides Import** — import a CSV/XLSX of ride names as sign-ins
- **Reservations** — lunch reservation sign-up plus search/print/export
- **Volunteers** — members with volunteer hours > 0, filterable by member/activity/date, with totals

## One-time setup

1. **Create the database.** In hPanel, create (or confirm) the `u288510777_kscdb`
   database and note its username/password.

2. **Create the tables.** In phpMyAdmin, select `u288510777_kscdb` and run
   `sql/schema.sql` (SQL tab → paste → Go). This creates `members`, `activities`,
   `activity_types`, `member_signin`, and `lunch_reservations`.

3. **Configure the app.** Copy `config.example.php` to `config.php` and fill in
   the real database host/name/user/password. `config.php` is git-ignored, so
   your real credentials never get committed.

4. **Migrate the existing data** from the WordPress database:
   - Fill in both sets of credentials at the top of `sql/migrate.php` (this file
     is intentionally NOT in the git repo — it's only meant to touch the server once).
   - Upload it to the server and visit it once in your browser. It copies
     `wp_ksc_members`, `wp_ksc_activities`, `wp_ksc_activity_types`,
     `wp_ksc_member_signin`, and `wp_ksc_lunch_reservations` into the new tables,
     preserving the original ids (so it's safe to re-run — it skips rows that
     already exist).
   - **Delete `migrate.php` from the server immediately after** — it contains
     database passwords and should never stay on a live site.

5. **Point `ksc.claysites.com` at this folder** as the document root (in
   Hostinger, under the site's hosting settings), or deploy this repo's
   contents into that domain's public folder.

## Deploying from GitHub

This repo is meant to be cloned/pulled directly onto the server, or deployed
via Hostinger's Git integration (hPanel → Advanced → Git). `config.php` is not
in the repo, so after the first deploy you'll need to create it once directly
on the server (step 3 above) — future git pulls won't touch it.

## Notes on parity with the old WordPress plugin

This is a faithful rebuild of every shortcode/module from the old `update-form`
plugin, with two small bug fixes made along the way:

- The old "update activity" handler referenced an undefined `$id` variable and
  would have fatally errored; this rebuild reads the posted id correctly.
- The old "update member/activity/activity-type" duplicate-name checks didn't
  exclude the record's own id, so re-saving a record unchanged could wrongly be
  blocked as a duplicate; this rebuild excludes the current id.

No WordPress nonces/login are used anywhere, per request — every page is open
to anyone who can reach the URL. If that ever needs to change, the natural
place to add a gate is `includes/header.php`.
