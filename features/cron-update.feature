Feature: ee cron update
  Every update rewrites the ofelia config from the DB and restarts the scheduler.

  Scenario: Updating the command wraps it and regenerates the config
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-old' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-old" as "ID"
      And I note the start time of the docker container "services_global-cron_1" as "STARTED"
      And I run "bin/ee cron update {ID} --command='echo bh-cron-command-upd-new'"
    Then the command should succeed
      And STDOUT should contain "Success: Cron update Successfully"
      And the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-upd-new'"
      And the cron job "{ID}" should have schedule "@hourly"
      And the cron config should have a "job-exec" job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-new"
      And the cron config should not have a job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-old"
      And the docker container "services_global-cron_1" should have been restarted since "{STARTED}"
      And the docker container "services_global-cron_1" should be running

  Scenario: Updating the user
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-user' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-user" as "ID"
      And I run "bin/ee cron update {ID} --user=root"
    Then the command should succeed
      And the cron job "{ID}" should have user "root"
      And the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-upd-user'"
      And the cron config job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-user" should have user "root"

  Scenario Outline: Updating to a schedule helper stores it verbatim
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo <marker>' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "<marker>" as "ID"
      And I run "bin/ee cron update {ID} --schedule='<schedule>'"
    Then the command should succeed
      And the cron job "{ID}" should have schedule "<schedule>"
      And the cron config job for "bh-cron-command-6.test" with command containing "<marker>" should have schedule "<schedule>"

    Examples:
      | schedule    | marker                       |
      | @daily      | bh-cron-command-upd-daily    |
      | @every 1m   | bh-cron-command-upd-every1m  |
      | @weekly     | bh-cron-command-upd-weekly   |

  Scenario: Moving a job to another site
    Given a php site "bh-cron-command-6.test" exists
      And a php site "bh-cron-command-7.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-move' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-move" as "ID"
      And I run "bin/ee cron update {ID} --site=bh-cron-command-7.test"
    Then the command should succeed
      And the cron job "{ID}" should have site_url "bh-cron-command-7.test"
      And the cron config should not have a job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-move"
      And the cron config should have a "job-exec" job for "bh-cron-command-7.test" with command containing "bh-cron-command-upd-move"
      And the cron config job for "bh-cron-command-7.test" with command containing "bh-cron-command-upd-move" should target the php container of "bh-cron-command-7.test"

  Scenario: Updating several fields at once
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-multi' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-multi" as "ID"
      And I run "bin/ee cron update {ID} --command='echo bh-cron-command-upd-multi2' --schedule='@daily' --user=root"
    Then the command should succeed
      And the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-upd-multi2'"
      And the cron job "{ID}" should have schedule "@daily"
      And the cron job "{ID}" should have user "root"

  Scenario: Updating a job twice with the same values is harmless
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-idem' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-idem" as "ID"
      And I run "bin/ee cron update {ID} --schedule='@daily'"
      And I run "bin/ee cron update {ID} --schedule='@daily'"
    Then the command should succeed
      And the cron job "{ID}" should have schedule "@daily"
      And there should be 1 cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-idem"

  Scenario: Updating a host job's command
    When I run "bin/ee cron create host --command='echo bh-cron-command-upd-host' --schedule='@hourly'"
      And I store the id of the latest cron job for "host" with command containing "bh-cron-command-upd-host" as "ID"
      And I run "bin/ee cron update {ID} --command='echo bh-cron-command-upd-host2'"
    Then the command should succeed
      And the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-upd-host2'"
      And the cron config should have a "job-local" job for "host" with command containing "bh-cron-command-upd-host2"

  Scenario: At least one field is required
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-none' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-none" as "ID"
      And I run "bin/ee cron update {ID}"
    Then the command should fail
      And STDERR should contain "You should specify at least one of - site, command, schedule or user to update"

  Scenario Outline: Commands that break the INI config are rejected and nothing changes
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-ini' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-ini" as "ID"
      And I run "bin/ee cron update {ID} --command='<command>'"
    Then the command should fail
      And STDERR should contain "<error>"
      And the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-upd-ini'"

    Examples:
      | command                  | error                                                    |
      | echo a; echo b           | Command chaining using `;` - semi-colon is not supported |
      | echo a # b               | EasyEngine does not support commands with #              |

  Scenario: A four-field schedule is rejected
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-4f' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-4f" as "ID"
      And I run "bin/ee cron update {ID} --schedule='* * * *'"
    Then the command should fail
      And STDERR should contain "Schedule format should be same as Linux cron or schedule helper syntax(Check help for this)"
      And the cron job "{ID}" should have schedule "@hourly"

  Scenario: An unknown flag is rejected
    When I run "bin/ee cron update 999999 --schedules='@daily'"
    Then the command should fail
      And STDERR should contain "unknown --schedules parameter"

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): update counts non-space characters instead of fields, so any valid schedule with a two-digit value or a step is refused. Also needs the seconds-field fix to pass.
  Scenario Outline: Valid five-field schedules are accepted by update like by create
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo <marker>' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "<marker>" as "ID"
      And I run "bin/ee cron update {ID} --schedule='<schedule>'"
    Then the command should succeed
      And the cron job "{ID}" should have schedule "0 <schedule>"

    Examples:
      | schedule      | marker                     |
      | */5 * * * *   | bh-cron-command-upd-step   |
      | 30 2 * * *    | bh-cron-command-upd-2digit |

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): update stores five fields without the seconds field create adds; ofelia reads the first field as seconds, so '* * * * *' runs every second and '0 0 * * *' hourly.
  Scenario: A five-field schedule gets a seconds field on update too
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-seconds' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-seconds" as "ID"
      And I run "bin/ee cron update {ID} --schedule='0 0 * * *'"
    Then the command should succeed
      And the cron job "{ID}" should have schedule "0 0 0 * * *"
      And the cron config job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-seconds" should have schedule "0 0 0 * * *"

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): a five-character token passes the length check.
  Scenario: A garbage schedule is rejected by update
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-garbage' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-garbage" as "ID"
      And I run "bin/ee cron update {ID} --schedule='12345'"
    Then the command should fail
      And the cron job "{ID}" should have schedule "@hourly"

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): update does not check that the id exists and reports success.
  Scenario: Updating an unknown id fails
    When I run "bin/ee cron update 999999 --command='echo bh-cron-command-upd-missing'"
    Then the command should fail
      And STDERR should contain "999999"
      And the cron job "999999" should not exist

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): --site is not validated; the job is written to the config as a job-exec without a container.
  Scenario: Moving a job to an unknown site fails
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-ghost' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-ghost" as "ID"
      And I run "bin/ee cron update {ID} --site=bh-cron-command-ghost.test"
    Then the command should fail
      And the cron job "{ID}" should have site_url "bh-cron-command-6.test"

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): --site accepts an html site that has no php container.
  Scenario: Moving a job to a site without php fails
    Given a php site "bh-cron-command-6.test" exists
      And a html site "bh-cron-command-16.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-html' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-html" as "ID"
      And I run "bin/ee cron update {ID} --site=bh-cron-command-16.test"
    Then the command should fail
      And STDERR should contain "does not have PHP container."
      And the cron job "{ID}" should have site_url "bh-cron-command-6.test"

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): a site job keeps user=www-data when moved to host; ofelia rejects `user` in a job-local and the whole scheduler exits.
  @destructive
  Scenario: Moving a site job to host keeps the scheduler running
    Given a php site "bh-cron-command-6.test" exists
    When I run "bin/ee cron create bh-cron-command-6.test --command='echo bh-cron-command-upd-tohost' --schedule='@yearly'"
      And I store the id of the latest cron job for "bh-cron-command-6.test" with command containing "bh-cron-command-upd-tohost" as "ID"
      And I run "bin/ee cron update {ID} --site=host"
    # Passes whether a fix drops the user or rejects the move.
    Then no "job-local" job in the cron config should have "user"
      And the docker container "services_global-cron_1" should stay up for 20 seconds
    When I run "bin/ee cron delete {ID}"
    Then the command should succeed

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): update writes `user` into a host job's job-local section, which ofelia rejects, so the whole scheduler exits.
  @destructive
  Scenario: Setting a user on a host job keeps the scheduler running
    When I run "bin/ee cron create host --command='echo bh-cron-command-upd-hostuser' --schedule='@yearly'"
      And I store the id of the latest cron job for "host" with command containing "bh-cron-command-upd-hostuser" as "ID"
      And I run "bin/ee cron update {ID} --user=root"
    Then no "job-local" job in the cron config should have "user"
      And the docker container "services_global-cron_1" should stay up for 20 seconds
    When I run "bin/ee cron delete {ID}"
    Then the command should succeed
