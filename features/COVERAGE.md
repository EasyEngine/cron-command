# cron-command Behat coverage

Run like the other package suites: replace a core checkout's `features/` with this package's (`rm -rf features && cp -R <package>/features features`, as the workflow does; copying over it would keep core's own `cli.feature`, whose scenario runs `ee cli self-uninstall --yes`) and run `vendor/bin/behat` from the core root as root. Useful tag filter: `--tags='~@destructive'` (never stops, removes or deliberately breaks the shared scheduler; every cron change still restarts it and regenerates the config, and before v4.13.1, on a machine with no other cron jobs, the scheduler was removed or restart-looped when the suite's last job went). Don't use `--stop-on-failure` or interrupt a run: Behat then skips the `@AfterFeature` cleanup that restores the scheduler and deletes the suite's sites.

Totals: 7 features, 112 scenarios (outline examples expanded). `@destructive`: 8, `@le`: 0, `@remote`: 0. No `@known-bug` scenarios ship here: the 8 scenario rows written for the open bugs listed under Known bugs are held back until their fixes land and then return as regular scenarios. Neither Let's Encrypt nor rclone is involved in cron-command.

Estimated runtime: about 30 minutes for the full suite, dominated by site creation (about 18 php/html sites and one WordPress site), site deletion, the scheduler restart on every cron change, and the schedule-driven waits (two minute-boundary waits, several 10 to 30 second stay-up checks).

Environment: sites are `bh-cron-command-<n>.test` (n = 1 to 16). Host jobs carry `bh-cron-command-` in their command. The `@AfterFeature` hook deletes only those jobs (in SQL, then one `ee cron delete` so the config is regenerated and the scheduler restarted once) and sites, removes `/tmp/bh-cron-command-*` markers, and starts or recreates `services_global-cron_1` if it existed before the feature or a step removed it (`ee cron delete` of the last job removed it before v4.13.1). After every `@destructive` scenario a hook deletes the suite's host jobs that have a user (they crash-looped the scheduler before v4.13.1) and starts or recreates the scheduler; after every scenario another deletes the suite's jobs that fire every few seconds, in case the scenario failed before its own delete step. The two empty-scheduler scenarios and the empty-table `list --all` scenario need an empty cron table and fail on their guard step when jobs not owned by the suite exist (any WordPress site has one).

## Inventory

### Commands, arguments and flags

| Item | Covered by |
|---|---|
| `ee cron` (usage, subcommand list) | cron.feature: "ee cron lists its subcommands", "An unknown cron subcommand is rejected" |
| `ee help cron <sub>` | cron.feature: "ee help documents every cron subcommand" |
| `cron create [<site-name>]` site | cron-create.feature: "A five-field schedule gets a seconds field ..." and most others |
| `cron create host` | cron-create.feature: "A host job is a job-local without container or user"; cron-scheduler.feature: host scenarios |
| `cron create` site from cwd (auto_site_name) | cron-create.feature: "The site is detected from the current directory", "A wrong site name inside a site directory suggests the detected site" |
| `cron create` without site / unknown site | cron-create.feature: "Without a site name outside a site directory ...", "An unknown site is rejected" |
| `cron create` trailing slash | cron-create.feature: "A trailing slash on the site name is ignored" |
| `cron create` too many positionals / unknown flag | cron-create.feature: "Too many positional arguments are rejected", "An unknown flag is rejected" |
| `--command` required, `sh -c` wrapping, pre-wrapped command, `&&`/pipes/redirects | cron-create.feature: "Required flags are enforced", "A five-field schedule ...", "A command that already uses sh -c is not wrapped again", "Pipes, redirects and && are accepted" |
| `--command` with `;` or `#` rejected | cron-create.feature and cron-update.feature: "Commands that break the INI config are rejected" |
| `--command` with a single quote | not covered yet: a single quote breaks the `sh -c` wrapping (its scenario comes back with the fix) |
| `--schedule` required | cron-create.feature: "Required flags are enforced" |
| `--schedule` five fields (seconds field prepended), whitespace | cron-create.feature: "A five-field schedule ...", "Extra whitespace between schedule fields is tolerated" |
| `--schedule` helpers `@yearly @annually @monthly @weekly @daily @midnight @hourly @every <d>` | cron-create.feature: "Schedule helpers are stored verbatim" (9 examples) |
| `--schedule` wrong field count / empty | cron-create.feature: "Schedules without exactly five fields are rejected", "An empty schedule is rejected" |
| `--schedule` invalid helper or values | cron-create.feature: "Invalid schedules are rejected at creation" (fixed in v4.13.1) |
| `--user` (default www-data for sites, custom) | cron-create.feature: "A five-field schedule ...", "A custom user is stored ..."; cron-run-now.feature: user scenarios |
| `--user` on host jobs | cron-scheduler.feature: "A host job with --user keeps the scheduler running"; cron-update.feature: "Setting a user on a host job keeps the scheduler running" (both `@destructive`, fixed in v4.13.1) |
| `create` on html site (no php) | cron-create.feature: "A site without a php container is rejected" |
| `create` on disabled site | cron-create.feature: "A disabled site is rejected" |
| `create` duplicate job | cron-create.feature: "Creating the same job twice stores two independent jobs" |
| `cron list <site>` | cron-list.feature: "Listing a site shows its jobs with every column", "Listing a site shows only that site's jobs" |
| `cron list host` | cron-list.feature: "Listing host shows only host jobs" (fixed in v4.13.1: any listed host job without a user fails with "Invalid field: user.") |
| `cron list --all`, site + `--all` | cron-list.feature: "--all lists site and host jobs together" (fixed in v4.13.1), "A site argument wins over --all" |
| `cron list` from cwd / no site / unknown site | cron-list.feature: "The site is detected from the current directory", "Without a site name ...", "An unknown site is rejected" |
| `cron list` empty result | cron-list.feature: "A site without jobs reports that nothing was found" |
| `cron list` disabled site | cron-list.feature: "Listing a disabled site still works" |
| `cron list <site>/` | not covered yet: a trailing slash makes list find no jobs (its scenario comes back with the fix) |
| `cron list --all` with an empty table | cron-list.feature: "--all with an empty cron table reports that nothing was found" (`@destructive`) |
| `http://`/`https://` site names, `update --site=<site>/`, extra positionals on update/run-now/list, `list host --all` | not covered: minor argument handling (`auto_site_name()` strips the scheme only for its lookup, so create/list then fail or find nothing) |
| `cron update <id>` usage without id | cron.feature: "Subcommands that need an id print their usage without one" |
| `update --command` | cron-update.feature: "Updating the command wraps it and regenerates the config", "Updating a host job's command" |
| `update --user` | cron-update.feature: "Updating the user"; on a host job "Setting a user on a host job keeps the scheduler running" (`@destructive`, fixed in v4.13.1) |
| `update --schedule` helpers | cron-update.feature: "Updating to a schedule helper stores it verbatim" |
| `update --schedule` five fields | cron-update.feature: "Valid five-field schedules are accepted by update like by create", "A garbage schedule is rejected by update" (fixed in v4.13.1), "A five-field schedule gets a seconds field on update too" (fixed in v4.13.1), "A four-field schedule is rejected"; cron-scheduler.feature: "A job updated to a five-field schedule still fires once a minute" (fixed in v4.13.1) |
| `update --site` | cron-update.feature: "Moving a job to another site"; unknown site and html site (fixed in v4.13.1); `--site=host` (`@destructive`, fixed in v4.13.1) |
| `update` several flags, idempotency | cron-update.feature: "Updating several fields at once", "Updating a job twice with the same values is harmless" |
| `update` without flags / unknown flag | cron-update.feature: "At least one field is required", "An unknown flag is rejected" |
| `update` unknown id | cron-update.feature: "Updating an unknown id fails" (fixed in v4.13.1) |
| `cron delete <cron-id>` | cron-delete.feature: "Deleting a job removes it from the DB and the config and keeps the others", "Deleting a host job", "Deleting a job of a disabled site" |
| `delete` twice / unknown / non-numeric / no id / extra args | cron-delete.feature: "Deleting the same job twice fails the second time", "Unknown ids are rejected", "Too many positional arguments are rejected"; cron.feature usage outline |
| `delete` of the last job | cron-scheduler.feature: "Deleting the last job keeps the scheduler running" (`@destructive`, regression check, fixed in v4.13.1) |
| `cron run-now <cron-id>` site job, default and custom user, webroot, repeat | cron-run-now.feature: first four scenarios |
| `run-now` host job (runs on the host) | cron-run-now.feature: "A host job runs on the host" |
| `run-now` unknown / non-numeric / no id | cron-run-now.feature: "Unknown ids are rejected"; cron.feature usage outline |
| `run-now` exit code of a failing job | not covered yet: run-now exits 0 when the job fails (its scenario comes back with the fix) |
| `run-now` disabled site | not covered yet: run-now runs with an empty container name (its scenario comes back with the fix) |
| `run-now` host PHP warning | not covered yet: a host job prints PHP warnings (its scenario comes back with the fix) |

### Behaviours and side effects

| Behaviour | Covered by |
|---|---|
| `cron` table rows (site_url, command, schedule, user) | every create/update/delete scenario via sqlite steps |
| Config `{EE_ROOT_DIR}/services/cron/conf/config.ini`: job type, schedule, command, user, container, no-overlap | cron-create.feature, cron-update.feature, cron-delete.feature, cron-scheduler.feature config steps |
| Scheduler restart on create/update/delete | cron-scheduler.feature: first scenario; cron-update.feature and cron-delete.feature first scenarios |
| Ofelia registers the job (and doesn't log "Failed to register job" for it) | cron-scheduler.feature: "Creating a job restarts the scheduler with the new config and ofelia registers it" |
| Jobs actually fire on schedule (as the configured user, second-zero semantics) | cron-scheduler.feature: "A site job runs on schedule ...", "A five-field schedule fires at second zero, not every second" |
| Invalid schedules silently dropped by ofelia | cron-scheduler.feature: "The scheduler never drops a job for an invalid schedule" (fixed in v4.13.1) |
| `create` boots a stopped or removed scheduler (`init_global_container`) | cron-scheduler.feature: "Creating a job starts a stopped scheduler", "Creating a job after the scheduler was removed brings it back" (both `@destructive`) |
| Scheduler with zero jobs (restart loop) | cron-scheduler.feature: "The scheduler stays up when the last site with jobs is deleted" (`@destructive`, regression check, fixed in v4.13.1) |
| `ee site delete` removes the site's jobs and regenerates config (site-command) | cron-scheduler.feature: "Deleting a site removes its jobs from the DB and the config", "Deleting a WordPress site removes its wp-cron job" |
| WordPress site create adds `wp cron event run --due-now` every 10 minutes (site-type-wp), `cron list` shows it and it runs | cron-scheduler.feature: "A WordPress site gets a wp-cron job that runs", "cron list shows a WordPress site's wp-cron job" |
| Disable/enable leaves a stale config | not covered yet: `ee site enable` doesn't regenerate the cron config (its scenario comes back with the fix) |
| Host jobs run inside the scheduler container, and the documented `wp` example | not covered yet: host jobs run inside the Alpine scheduler container, not on the host (its 2 scenarios come back with the fix) |
| Migrations (`create_table_cron`, `no_overlap`, `update_cron_user`, `cleanup_old_entries`, `recreate_crons`) and the core `ee-update-cron-config` update step | not covered: they run once during `ee cli update`/migrate and need a crafted pre-upgrade DB; belongs to a core migration suite |
| macOS paths | not covered: Linux CI only |

Coverage estimate: about 88% of commands, flags and behaviours (every subcommand, positional and flag, every validation branch in `Cron_Command.php`, both helper functions and the site-command/site-type-wp integration points). The gaps are the migrations and minor argument handling (scheme-prefixed site names, extra positionals).

## Known bugs

Fixed and covered by regular scenarios (regression checks):

| Bug | Scenario(s) |
|---|---|
| ofelia exits on zero jobs, so the scheduler restart-looped or was removed | cron-scheduler.feature: "Deleting the last job keeps the scheduler running", "The scheduler stays up when the last site with jobs is deleted" |
| weak schedule validation (ofelia silently dropped invalid schedules; update refused valid ones) | cron-create.feature: "Invalid schedules are rejected at creation"; cron-update.feature: "Valid five-field schedules are accepted by update like by create" (also needs the seconds-field fix), "A garbage schedule is rejected by update"; cron-scheduler.feature: "The scheduler never drops a job for an invalid schedule" |
| `user` in a job-local makes ofelia exit ("can't store data at section job-local ... variable user") | cron-scheduler.feature "A host job with --user keeps the scheduler running", cron-update.feature "Moving a site job to host keeps the scheduler running", "Setting a user on a host job keeps the scheduler running" |
| `update` stores five-field schedules without the seconds field create adds, so ofelia reads `* * * * *` as every second and `0 0 * * *` as hourly | cron-update.feature "A five-field schedule gets a seconds field on update too", cron-scheduler.feature "A job updated to a five-field schedule still fires once a minute" |
| `update` didn't check the id or validate `--site` | cron-update.feature: "Updating an unknown id fails", "Moving a job to an unknown site fails", "Moving a job to a site without php fails" |
| `list host` / `list --all` failed with "Invalid field: user." on a host job without a user | cron-list.feature: "Listing host shows only host jobs", "--all lists site and host jobs together" |

All of them landed in cron-command v2.1.1 (EasyEngine/cron-command#60, core v4.13.1). The keepalive `ee-keepalive` section never matches the config steps (they select jobs by `<site>-` name prefix).

Open (scenarios held back until fixed):

| Bug | Held-back scenario(s) |
|---|---|
| host jobs run inside the Alpine scheduler container, not on the host | cron-scheduler.feature: "A host job runs on the host on schedule", "The documented host wp example has the tools it needs" |
| a single quote in the command breaks the `sh -c` wrapping | cron-run-now.feature: "A command with a single quote runs correctly" |
| run-now ignores the job's exit code and doesn't refuse a disabled site | cron-run-now.feature: "A failing job makes run-now fail", "Running a job of a disabled site fails with a clear error" |
| run-now on a host job looks up a php container first and prints PHP warnings | cron-run-now.feature: "Running a host job prints no PHP warnings" |
| `ee site enable` doesn't regenerate the cron config | cron-scheduler.feature: "A site's jobs target its php container again after disable and enable" |
| `list <site>/` ignores the normalised site name | cron-list.feature: "A trailing slash on the site name is ignored" |
