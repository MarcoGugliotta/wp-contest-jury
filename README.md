# WP Contest Jury

A generic, reusable jury panel for WordPress photography contests.  
Works alongside **Contest Gallery PRO** (PCG): reads entries from PCG tables (read-only) and stores votes in its own tables.

---

## Requirements

- WordPress 6.0+
- Contest Gallery PRO installed and configured with at least one gallery
- PCG must require WordPress login for uploads so that `WpUserId` is populated on every entry

---

## How it works — overview

1. Admin installs the plugin, creates a WP page, adds `[wpcj_jury_panel]` shortcode
2. Admin configures galleries and settings in **Jury Panel → Settings**
3. Admin creates jury members (WP users with role `jury_member`)
4. Admin creates a voting round in **Jury Panel → Rounds** and opens it
5. Jurors visit the jury page URL, log in, and vote
6. Admin monitors progress and views results in wp-admin

---

## How author identity works

Author name is read from the **WordPress user account** (`wp_users` + `wp_usermeta`) via the `WpUserId` column in PCG's `wp_contest_gal1ery` table.

This approach is stable across festival editions: WP registration data does not change between editions, while PCG form fields and their IDs may differ from one gallery configuration to the next.

**Consequence for PCG form setup:** the upload form does not need name or surname fields. Author identity is already captured at registration time.

---

## PCG upload form — field naming convention

If you add name or surname fields to the PCG upload form, set their **Field Name** (the label in PCG's form builder) to exactly:

| Field | Required value |
|-------|----------------|
| First name | `field_name` |
| Surname | `field_surname` |

The plugin reads `wp_contest_gal1ery_f_input` and excludes fields with these exact titles from the entry detail panel, preventing the author name from appearing twice.

**Recommended:** do not add name/surname fields to the upload form at all.

---

## Settings

| Setting | Description |
|---------|-------------|
| Galleries | Auto-populated from PCG. Set a display label for each gallery. |
| Show author name to jurors | Off by default (anonymous voting). When on, the uploader's name is shown on each entry card. |
| Jury Page | The WP page containing `[wpcj_jury_panel]`. Jurors are redirected here after login. |
| Welcome Message | Text shown to jurors on the round selection screen after login. |
| Require all votes | When on, jurors must vote every entry before they can submit. |

---

## Roles

| Role | Capabilities |
|------|--------------|
| `jury_member` | `wpcj_vote` — access the jury panel and cast votes |
| `jury_chief` | All jury capabilities + manage rounds, jurors, settings, results |

Jurors never access wp-admin. All voting happens on the frontend page with the shortcode.

---

## Shortcode

```
[wpcj_jury_panel]
```

Place this shortcode on any WordPress page. Set that page in **Settings → Jury Page**.

---

## Voting rounds

Each round is linked to one gallery and has a `round_type` that enforces a unique workflow step per gallery:

| Type | Constant |
|------|----------|
| `initial` | `WPCJ_DB::ROUND_INITIAL` |
| `shortlist` | `WPCJ_DB::ROUND_SHORTLIST` |
| `final` | `WPCJ_DB::ROUND_FINAL` |
| `winner` | `WPCJ_DB::ROUND_WINNER` |

Each gallery can have at most one round per type (enforced by a UNIQUE KEY at the database level).

**Round lifecycle:** `draft` → `open` → `closed`

---

## Frontend jury panel — voting flow

1. Juror visits the jury page URL
2. If not logged in: embedded WP login form
3. After login: **Selection screen** — lists all open rounds with individual progress (voted/total) and state badge (Not Started / In Progress / Submitted)
4. Juror selects a round → **Voting screen**:
   - Progress bar: "Voted: 45 / 230"
   - Filter tabs: **All / To vote / Voted**
   - Paginated grid (20 entries per page), responsive (2 columns on mobile → 5 on desktop)
   - Each card: thumbnail, entry ID, title, star rating (1–5), optional notes
   - Voted cards show a green border and a ✓ badge
   - Every star click auto-saves via AJAX — no save button needed
   - **Submit Votes** button finalises the round for that juror (irreversible)
5. After submission: round appears as "Submitted" in the selection screen; juror can re-open to view their votes in read-only mode

---

## Database tables (created by this plugin)

| Table | Description |
|-------|-------------|
| `{prefix}jury_rounds` | Voting rounds — name, gallery ID, round_type, status (draft/open/closed) |
| `{prefix}jury_votes` | One row per juror × entry × round — score 1–5, optional notes |
| `{prefix}jury_shortlist` | Entries promoted to a shortlist within a round |
| `{prefix}jury_submissions` | One row per juror × round once the juror submits — marks votes as final |

PCG tables are never written to by this plugin.

---

## Entry detail panel

Each entry card shows:

- Thumbnail (click to open full size)
- Entry ID
- **Title** — first `Short_Text` field from `wp_contest_gal1ery_entries`
- Author name (only in transparent mode, from `wp_users`)
- **Details** toggle — expands all PCG form fields with their labels (read from `wp_contest_gal1ery_f_input.Field_Content`), excluding fields titled `field_name` or `field_surname`

---

## Results (admin only)

Jury Panel → Rounds → Results (available after a round is closed):  
Shows a ranked table — rank, entry, title, author, average score, vote count, total score.

---

## Dev seed

`docs/dev-seed.sql` — run in phpMyAdmin to populate the local dev database with:
- Two CG PRO galleries and their field definitions
- Four photographer WP users
- Five photo entries with titles and synopses
- `SET NAMES utf8mb4` at the top to prevent encoding issues

Run after each reset to restore a known state.
