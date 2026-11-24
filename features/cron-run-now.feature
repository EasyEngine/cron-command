Feature: ee cron run-now
  Site jobs run through `docker exec` in the site's php container as the job's user; host jobs run on the host itself.

  Scenario: A site job runs in the php container as www-data by default
    Given a php site "bh-cron-command-9.test" exists
    When I run "bin/ee cron create bh-cron-command-9.test --command='echo bh-cron-command-whoami-$(id -un)' --schedule='@yearly'"
      And I store the id of the latest cron job for "bh-cron-command-9.test" with command containing "bh-cron-command-whoami-" as "ID"
      And I run "bin/ee cron run-now {ID}"
    Then the command should succeed
      And STDOUT should contain "bh-cron-command-whoami-www-data"
      And STDOUT should not contain "$(id -un)"

  Scenario: A site job runs as its configured user
    Given a php site "bh-cron-command-9.test" exists
    When I run "bin/ee cron create bh-cron-command-9.test --command='echo bh-cron-command-runas-$(id -un)' --schedule='@yearly' --user=root"
      And I store the id of the latest cron job for "bh-cron-command-9.test" with command containing "bh-cron-command-runas-" as "ID"
      And I run "bin/ee cron run-now {ID}"
    Then the command should succeed
      And STDOUT should contain "bh-cron-command-runas-root"

  Scenario: A site job runs inside the site's webroot volume
    Given a php site "bh-cron-command-9.test" exists
      And the file "{EE_ROOT_DIR}/sites/bh-cron-command-9.test/app/htdocs/bh-cron-command-run-marker" does not exist
    When I run "bin/ee cron create bh-cron-command-9.test --command='touch /var/www/htdocs/bh-cron-command-run-marker' --schedule='@yearly'"
      And I store the id of the latest cron job for "bh-cron-command-9.test" with command containing "bh-cron-command-run-marker" as "ID"
      And I run "bin/ee cron run-now {ID}"
    Then the command should succeed
      And the file "{EE_ROOT_DIR}/sites/bh-cron-command-9.test/app/htdocs/bh-cron-command-run-marker" should exist

  Scenario: Running a job twice runs it twice
    Given a php site "bh-cron-command-9.test" exists
      And the file "{EE_ROOT_DIR}/sites/bh-cron-command-9.test/app/htdocs/bh-cron-command-twice.log" does not exist
    When I run "bin/ee cron create bh-cron-command-9.test --command='echo bh-cron-command-twice >> /var/www/htdocs/bh-cron-command-twice.log' --schedule='@yearly'"
      And I store the id of the latest cron job for "bh-cron-command-9.test" with command containing "bh-cron-command-twice" as "ID"
      And I run "bin/ee cron run-now {ID}"
      And I run "bin/ee cron run-now {ID}"
      And I run "test $(grep -c bh-cron-command-twice {EE_ROOT_DIR}/sites/bh-cron-command-9.test/app/htdocs/bh-cron-command-twice.log) -eq 2"
    Then the command should succeed

  Scenario: A host job runs on the host
    Given the file "/tmp/bh-cron-command-host-runnow" does not exist
    When I run "bin/ee cron create host --command='touch /tmp/bh-cron-command-host-runnow' --schedule='@yearly'"
      And I store the id of the latest cron job for "host" with command containing "bh-cron-command-host-runnow" as "ID"
      And I run "bin/ee cron run-now {ID}"
    Then the command should succeed
      And the file "/tmp/bh-cron-command-host-runnow" should exist

  Scenario Outline: Unknown ids are rejected
    When I run "bin/ee cron run-now <id>"
    Then the command should fail
      And STDERR should contain "No such cron with id <id>"

    Examples:
      | id     |
      | 999999 |
      | abc    |
