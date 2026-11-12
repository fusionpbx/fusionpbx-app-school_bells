# School Bells App

The School Bells application gives periodic signals to phones or SIP speakers in a
`cron`-style way. Each bell is a schedule plus an action: the PBX calls an extension
(normally a paging extension or a SIP speaker) and, when that extension answers, plays
a recording. By initial design, the app allows you to ring an extension for a certain
amount of time (via the **Ring Timeout** parameter), or to play back a recording after
the extension answers.

---

## How it works

1. A system cron job runs `school_bells_cron.php` once per minute.
2. The script reads every enabled row of `v_school_bells` whose **Minute** matches the
   current minute (or is set to `*`, i.e. stored as `-1`). Disabled bells are filtered
   out by the query, so they never ring.
3. For each candidate it compares **Hour**, **Day of month**, **Month** and
   **Day of week** against the current date/time, evaluated in the bell's own
   **Timezone** (`school_bells::should_ring()`, which does not change the server's
   default timezone). A field set to `*` always matches; all fields must match
   (AND logic).
4. For every matching bell it sends this command over the FreeSWITCH Event Socket:

```
bgapi originate {ignore_early_media=true,hangup_after_bridge=true,
domain_name=<context>,domain_uuid=<domain_uuid>,call_timeout=<ring timeout>}
loopback/<extension>/<context> &playback(<recording path>)
```

Because the call is placed with the `loopback` application into the domain dialplan,
the target behaves like any normal incoming call: a normal phone rings for
**Ring Timeout** seconds, and an auto-answering SIP speaker or intercom picks up and
plays the selected recording.

The script is limited to 55 seconds of execution time so it always finishes before the
next cron run. It only runs from the command line; opening it in a browser returns
`access denied`.

---

## Requirements

- FusionPBX (with FreeSWITCH) — the app is installed under `/var/www/fusionpbx/app/school_bells`
- A working Event Socket connection (default `127.0.0.1:8021`, credentials taken from
  your FusionPBX / FreeSWITCH configuration, e.g. `switch.event_socket.*`)
- An extension to call — typically a **PAGE** extension, a paging group, or a SIP
  speaker configured to auto-answer
- A recording under **Applications → Recordings** if you want audio played after the
  answer (optional)

---

## Install

Clone the app into the FusionPBX `app` directory (it must end up in
`/var/www/fusionpbx/app/school_bells`, which is also the path used by the cron job and
the menu entry), then run the schema upgrade:

```
cd /var/www/fusionpbx/app
git clone https://github.com/fusionpbx/fusionpbx-app-school_bells school_bells
chown -R www-data:www-data /var/www/fusionpbx
php /var/www/fusionpbx/core/upgrade/upgrade.php
```

If you install apps from the packaged source instead of git:

```
cp -R /usr/src/fusionpbx-apps/school_bells /var/www/fusionpbx/app
chown -R www-data:www-data /var/www/fusionpbx/app/school_bells
php /var/www/fusionpbx/core/upgrade/upgrade.php
```

### Cron

Add a cron job (the bell schedule itself is defined in the web interface; this job only
checks it every minute):

```
crontab -e
```

Add this line:

```
* * * * * php /var/www/fusionpbx/app/school_bells/school_bells_cron.php
```

### Web interface

- Open your web browser and go to your server's URL
- Log in with your username and password
- **Menu → Advanced → Upgrade**
  - Schema
  - App Defaults
  - Menu Defaults
  - Permission Defaults
- Log out and back in

The app then appears under **Menu → Applications → School Bells**.

### Permissions

| Permission            | Granted by default to     | Allows                                    |
|-----------------------|---------------------------|-------------------------------------------|
| `school_bell_view`    | superadmin, admin         | See the School Bells list                 |
| `school_bell_add`     | superadmin, admin         | Create a bell                             |
| `school_bell_edit`    | superadmin, admin         | Change an existing bell                   |
| `school_bell_delete`  | superadmin, admin         | Delete a bell                             |

Bells are stored per domain, so in a multi-tenant system each domain manages its own
schedules.

---

## Using the app

Open **Applications → School Bells** to search, sort and page through your bells, then use
the **+** icon (or click a row) to add/edit one. Tick the check boxes in the list to
**Copy**, **Toggle** or **Delete** several bells at once; the Enabled column can also be
toggled from the list with a single click.

### Fields

| Field                | Description |
|----------------------|-------------|
| **Name**             | Name of this schedule (required). |
| **Called Extension** | Which extension should be called on schedule. Normally it is your PAGE extension or SIP speaker (required). |
| **Ring Timeout**     | Seconds the extension rings before the call gives up, in seconds (0–3600). See *Ring timeout behaviour* below. |
| **Call Answer Action** | Recording to play when the extension answers. Pick any recording from **Applications → Recordings**. Leave it empty to only ring the extension without playing audio. |
| **Minute**           | Minute of the hour, `0–59`, or `*` for every minute. |
| **Hour**             | Hour of the day, `0–23` (shown as 12h AM/PM if your domain uses 12-hour time), or `*` for every hour. |
| **Day of the month** | `1–31`, or `*` for every day. |
| **Month**            | January–December, or `*` for every month. |
| **Day of the week**  | Sunday–Saturday (`0` = Sunday … `6` = Saturday), or `*` for every day of the week. |
| **Timezone**         | Timezone this schedule is evaluated in — set it to the school's local timezone, not necessarily the server's. |
| **Enabled**          | Turns the schedule on or off. Disabled bells are skipped by the cron job and can be toggled from the list without opening the record. |
| **Description**      | Free text note. |

### Scheduling rules

- The schedule works like cron but each field accepts **one value only** — no lists,
  ranges or steps (`*/5`, `1,3,5`, `9-17` are not supported).
- All fields must match at the same time (AND logic), including day of month and day of
  week. In real cron those two are OR'ed; here they are not.
- `-1` (shown as `*`) means "every value" for that field.

### Examples

| Goal                              | Minute | Hour | Day of month | Month | Day of week |
|-----------------------------------|--------|------|--------------|-------|-------------|
| Every day at 08:30                | 30     | 8    | *            | *     | *           |
| Once an hour, on the hour         | 0      | *    | *            | *     | *           |
| Every minute (e.g. a test bell)   | *      | *    | *            | *     | *           |
| Class change at 12:00 in June     | 0      | 12   | *            | June  | *           |
| Christmas break only, daily 09:00 | 0      | 9    | *            | December | *        |
| Mondays at 07:55                  | 55     | 7    | *            | *     | Monday      |

**Weekdays only:** because each field takes a single value, "Monday to Friday" cannot be
expressed by one entry. Create five bells (one per weekday) sharing the same minute,
hour, recording and timezone — name them `Morning bell - Mon`, `Morning bell - Tue`, etc.

### Ring timeout behaviour

The value you enter is passed to FreeSWITCH as `call_timeout`. The cron script applies
these safety adjustments:

- a negative value becomes `3` seconds when the bell is saved; `0` becomes `5` seconds in the cron script
- values greater than `60` are clamped to `55` seconds **when the bell rings every
  minute** (`Minute = *`), so a runaway schedule cannot overlap cron runs

The recording itself is not limited by this value — it plays to its end after the
extension answers.

---

## Testing and troubleshooting

Run the cron script by hand from the command line:

```
php /var/www/fusionpbx/app/school_bells/school_bells_cron.php
```

- `no bells` means nothing matched the current minute — that is normal unless a bell is
  due right now. Set **Minute** to `*` temporarily to force a match on the next run.
- `Cannot connect to Event Socket` means FreeSWITCH is down or the event socket host,
  port or password in your configuration is wrong.
- To see each decision (schedule mismatches, invalid values, the generated originate
  command) without editing the file, pass `debug`:

```
php /var/www/fusionpbx/app/school_bells/school_bells_cron.php debug
```

  Leave the argument off in the crontab entry; with cron it would write to mail every
  minute.
- Check that the **Called Extension** is reachable in the domain dialplan and that the
  recording exists under **Applications → Recordings**.

### Known limitations

- One value per schedule field; no cron lists/ranges/steps.
- Only the `loopback` leg type and recording playback action are implemented today
  (`school_bell_leg_a_type` is fixed to `loopback/`, `school_bell_leg_b_type` holds the
  full path of the chosen recording: `<recordings dir>/<domain>/<filename>`).

---

## Files

| Path | Purpose |
|------|---------|
| `school_bells.php` | List view: search, sort, paging, and bulk copy / toggle / delete |
| `school_bell_edit.php` | Add / edit form for a single bell, with delete from the form |
| `school_bell_delete.php` | Delete one bell (token protected, domain scoped) |
| `school_bells_cron.php` | Command line worker run every minute by cron |
| `resources/classes/school_bells.php` | Bulk copy / toggle / delete, schedule matching and formatting |
| `resources/classes/school_bell_selector.php` | Builds the Minute/Hour/Day/Month dropdowns |
| `app_config.php` | App definition: permissions and the `v_school_bells` schema |
| `app_menu.php` | Menu entry under Applications |
| `app_languages.php` | Translations for the user interface |

## Uninstall

```
crontab -e                                  # remove the school_bells_cron.php line
rm -rf /var/www/fusionpbx/app/school_bells
```

Optionally drop the data (irreversible): delete the `v_school_bells` table and the app's
menu/permission/default entries from the database, then run
`php /var/www/fusionpbx/core/upgrade/upgrade.php`.

## License

Mozilla Public License 1.1 — see the headers in the source files.
