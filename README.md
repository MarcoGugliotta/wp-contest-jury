# WP Contest Jury

A generic, reusable jury panel for WordPress photography contests.  
Works alongside **Contest Gallery PRO** (PCG): reads entries from PCG tables (read-only) and stores votes in its own tables.

---

## Requirements

- WordPress 6.0+
- Contest Gallery PRO installed and configured with at least one gallery
- PCG must require WordPress login for uploads so that `WpUserId` is populated on every entry

---

## How author identity works

Author name and contact data are read from the **WordPress user account** (`wp_users` + `wp_usermeta`) via the `WpUserId` column in PCG's `wp_contest_gal1ery` table.

This approach is stable across festival editions: WP registration data does not change between editions, while PCG form fields and their IDs may differ from one gallery configuration to the next.

**Consequence for PCG form setup:** the upload form does not need to include name or surname fields. Author identity is already captured at registration time.

---

## PCG upload form — field naming convention

If for any reason you choose to add name or surname fields to the PCG upload form, you **must** set their **Field Name** (the label in PCG's form builder) to exactly:

| Field | Required value |
|-------|----------------|
| First name | `field_name` |
| Surname | `field_surname` |

The jury plugin reads `wp_contest_gal1ery_f_input` and excludes from the entry detail panel any field whose titel matches these exact strings. This prevents the author name from appearing twice (once as the author badge, once inside the detail panel).

**Recommended setup:** do not add name/surname fields to the upload form at all. Use the WordPress registration form for identity data and reserve the PCG upload form for artwork metadata only (title, synopsis, photo titles, etc.).

---

## Settings

| Setting | Description |
|---------|-------------|
| Galleries | Auto-populated from PCG. Set a display label for each gallery. |
| Show author name to jurors | Off by default (anonymous voting). When on, the uploader's name is shown below each entry card. |
| Jury Page ID | ID of the WordPress page containing the `[wpcj_jury_panel]` shortcode. Jurors are redirected here after login. |

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

Place this shortcode on any WordPress page. Jurors navigate to that URL, log in, and vote. The login redirect filter automatically sends jury users to this page after authentication.

---

## Database tables (created by this plugin)

| Table | Description |
|-------|-------------|
| `{prefix}jury_rounds` | Voting rounds — name, gallery ID, status (draft / open / closed) |
| `{prefix}jury_votes` | One row per juror × entry × round — score 1–5, optional notes |
| `{prefix}jury_shortlist` | Entries promoted to a shortlist within a round |

PCG tables are never written to by this plugin.

---

## Entry detail panel

Each entry card in the jury panel shows:

- Thumbnail (click to open full size)
- Entry ID
- **Title** — first Short_Text field from `wp_contest_gal1ery_entries`
- Author name (only in transparent mode)
- **Details** toggle — expands all PCG form fields for that entry, excluding any field whose titel is `field_name` or `field_surname`
