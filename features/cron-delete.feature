Feature: ee cron delete

  Scenario: Deleting a job removes it from the DB and the config and keeps the others
    Given a php site "bh-cron-command-8.test" exists
    When I run "bin/ee cron create bh-cron-command-8.test --command='echo bh-cron-command-del-keep' --schedule='@hourly'"
      And I run "bin/ee cron create bh-cron-command-8.test --command='echo bh-cron-command-del-gone' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-8.test" with command containing "bh-cron-command-del-keep" as "KEEP"
      And I store the id of the latest cron job for "bh-cron-command-8.test" with command containing "bh-cron-command-del-gone" as "GONE"
      And I note the start time of the docker container "services_global-cron_1" as "STARTED"
      And I run "bin/ee cron delete {GONE}"
    Then the command should succeed
      And STDOUT should contain "Success: Deleted cron with id {GONE}"
      And the cron job "{GONE}" should not exist
      And the cron job "{KEEP}" should exist
      And the cron config should not have a job for "bh-cron-command-8.test" with command containing "bh-cron-command-del-gone"
      And the cron config should have a "job-exec" job for "bh-cron-command-8.test" with command containing "bh-cron-command-del-keep"
      And the docker container "services_global-cron_1" should have been restarted since "{STARTED}"
      And the docker container "services_global-cron_1" should be running

  Scenario: Deleting the same job twice fails the second time
    Given a php site "bh-cron-command-8.test" exists
    When I run "bin/ee cron create bh-cron-command-8.test --command='echo bh-cron-command-del-twice' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-8.test" with command containing "bh-cron-command-del-twice" as "ID"
      And I run "bin/ee cron delete {ID}"
    Then the command should succeed
    When I run "bin/ee cron delete {ID}"
    Then the command should fail
      And STDERR should contain "Unable to find cron with id {ID}"

  Scenario: Deleting a host job
    Given a php site "bh-cron-command-8.test" exists
    When I run "bin/ee cron create bh-cron-command-8.test --command='echo bh-cron-command-del-anchor' --schedule='@hourly'"
      And I run "bin/ee cron create host --command='echo bh-cron-command-del-host' --schedule='@hourly'"
      And I store the id of the latest cron job for "host" with command containing "bh-cron-command-del-host" as "ID"
      And I run "bin/ee cron delete {ID}"
    Then the command should succeed
      And the cron job "{ID}" should not exist
      And the cron config should not have a job for "host" with command containing "bh-cron-command-del-host"

  Scenario: Deleting a job of a disabled site
    Given a php site "bh-cron-command-8.test" exists
    When I run "bin/ee cron create bh-cron-command-8.test --command='echo bh-cron-command-del-disabled' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-8.test" with command containing "bh-cron-command-del-disabled" as "ID"
      And the site "bh-cron-command-8.test" is disabled
      And I run "bin/ee cron delete {ID}"
    Then the command should succeed
      And the cron job "{ID}" should not exist
    Given the site "bh-cron-command-8.test" is enabled

  Scenario Outline: Unknown ids are rejected
    When I run "bin/ee cron delete <id>"
    Then the command should fail
      And STDERR should contain "Unable to find cron with id <id>"

    Examples:
      | id     |
      | 999999 |
      | abc    |
      | 0      |

  Scenario: Too many positional arguments are rejected
    When I run "bin/ee cron delete 999998 999999"
    Then the command should fail
      And STDERR should contain "Too many positional arguments: 999999"
