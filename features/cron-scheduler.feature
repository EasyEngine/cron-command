Feature: Global cron scheduler (services_global-cron_1, ofelia)
  The scheduler reads {EE_ROOT_DIR}/services/cron/conf/config.ini, generated from the cron table on every change.
  Scenarios tagged @destructive stop, empty or break the shared scheduler; they only touch jobs this suite owns and restore it.

  Scenario: Creating a job restarts the scheduler with the new config and ofelia registers it
    Given a php site "bh-cron-command-11.test" exists
    # Makes sure the scheduler exists (an earlier cleanup may have removed it) and keeps the table non-empty for this feature.
    When I run "bin/ee cron create bh-cron-command-11.test --command='echo bh-cron-command-sched-anchor' --schedule='@yearly'"
      And I note the start time of the docker container "services_global-cron_1" as "STARTED"
      And I run "bin/ee cron create bh-cron-command-11.test --command='echo bh-cron-command-reg' --schedule='@hourly'"
    Then the command should succeed
      And the docker container "services_global-cron_1" should have been restarted since "{STARTED}"
      And the docker container "services_global-cron_1" should be running
      And the logs of the docker container "services_global-cron_1" should contain within 15 seconds:
        """
        New job registered "bh-cron-command-11.test-sh-c-echo-bh-cron-command-reg-
        """
      And the logs of the docker container "services_global-cron_1" should not contain:
        """
        Failed to register job "bh-cron-command-11.test-sh-c-echo-bh-cron-command-reg-
        """

  Scenario: A site job runs on schedule inside the php container as its user
    Given a php site "bh-cron-command-11.test" exists
      And the file "{EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-sched-whoami" does not exist
    When I run "bin/ee cron create bh-cron-command-11.test --command='id -un > /var/www/htdocs/bh-cron-command-sched-whoami' --schedule='@every 5s'"
      And I store the id of the latest cron job for "bh-cron-command-11.test" with command containing "bh-cron-command-sched-whoami" as "ID"
      And I wait up to 60 seconds until the command "grep -qx www-data {EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-sched-whoami" succeeds
      And I run "bin/ee cron delete {ID}"
    Then the command should succeed

  Scenario: A five-field schedule fires at second zero, not every second
    Given a php site "bh-cron-command-11.test" exists
      And the file "{EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-minutely.log" does not exist
    When I run "bin/ee cron create bh-cron-command-11.test --command='date +%s >> /var/www/htdocs/bh-cron-command-minutely.log' --schedule='* * * * *'"
      And I store the id of the latest cron job for "bh-cron-command-11.test" with command containing "bh-cron-command-minutely" as "ID"
      And I wait up to 75 seconds until the command "test -s {EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-minutely.log" succeeds
      # Long enough for an every-second job to write more than two lines.
      And After delay of 5 seconds
      And I run "bin/ee cron delete {ID}"
      And I run "test $(wc -l < {EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-minutely.log) -le 2"
    Then the command should succeed

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): update drops the seconds field, so '* * * * *' becomes every second in ofelia.
  Scenario: A job updated to a five-field schedule still fires once a minute
    Given a php site "bh-cron-command-11.test" exists
      And the file "{EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-updminutely.log" does not exist
    When I run "bin/ee cron create bh-cron-command-11.test --command='date +%s >> /var/www/htdocs/bh-cron-command-updminutely.log' --schedule='@yearly'"
      And I store the id of the latest cron job for "bh-cron-command-11.test" with command containing "bh-cron-command-updminutely" as "ID"
      And I run "bin/ee cron update {ID} --schedule='* * * * *'"
      And I wait up to 75 seconds until the command "test -s {EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-updminutely.log" succeeds
      And After delay of 5 seconds
      And I run "bin/ee cron delete {ID}"
      And I run "test $(wc -l < {EE_ROOT_DIR}/sites/bh-cron-command-11.test/app/htdocs/bh-cron-command-updminutely.log) -le 2"
    Then the command should succeed

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): invalid schedules are accepted and ofelia silently skips the job.
  Scenario: The scheduler never drops a job for an invalid schedule
    Given a php site "bh-cron-command-11.test" exists
    When I run "bin/ee cron create bh-cron-command-11.test --command='echo bh-cron-command-sched-anchor2' --schedule='@yearly'"
      And I run "bin/ee cron create bh-cron-command-11.test --command='echo bh-cron-command-badsched' --schedule='99 * * * *'"
    Then the logs of the docker container "services_global-cron_1" should contain "Starting scheduler" within 15 seconds
      And the logs of the docker container "services_global-cron_1" should not contain:
      """
      Failed to register job "bh-cron-command-11.test-sh-c-echo-bh-cron-command-badsched-
      """

  Scenario: Deleting a site removes its jobs from the DB and the config
    Given a php site "bh-cron-command-12.test" exists
    When I run "bin/ee cron create bh-cron-command-12.test --command='echo bh-cron-command-sitedel-a' --schedule='@hourly'"
      And I run "bin/ee cron create bh-cron-command-12.test --command='echo bh-cron-command-sitedel-b' --schedule='@daily'"
    Then there should be 2 cron jobs for "bh-cron-command-12.test"
    When I run "bin/ee site delete bh-cron-command-12.test --yes"
    Then the command should succeed
      And STDOUT should contain "Deleting cron entry"
      And the site "bh-cron-command-12.test" should not exist
      And there should be 0 cron jobs for "bh-cron-command-12.test"
      And the cron config should not have any job for "bh-cron-command-12.test"

  Scenario: A WordPress site gets a wp-cron job that runs
    Given a wp site "bh-cron-command-13.test" exists
    Then there should be 1 cron job for "bh-cron-command-13.test" with command containing "wp cron event run --due-now"
    When I store the id of the latest cron job for "bh-cron-command-13.test" with command containing "wp cron event run --due-now" as "ID"
    Then the cron job "{ID}" should have command "sh -c 'wp cron event run --due-now'"
      And the cron job "{ID}" should have user "www-data"
      And the cron job "{ID}" should have schedule matching "#^0 [0-9]/10 \* \* \* \*$#"
      And the cron config should have a "job-exec" job for "bh-cron-command-13.test" with command containing "wp cron event run --due-now"
      And the cron config job for "bh-cron-command-13.test" with command containing "wp cron event run --due-now" should target the php container of "bh-cron-command-13.test"
    When I run "bin/ee cron run-now {ID}"
    Then the command should succeed
      And STDOUT should contain "Executed a total of"

  Scenario: cron list shows a WordPress site's wp-cron job
    Given a wp site "bh-cron-command-13.test" exists
    When I store the id of the latest cron job for "bh-cron-command-13.test" with command containing "wp cron event run --due-now" as "ID"
      And I run "bin/ee cron list bh-cron-command-13.test"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | id   | site_url                | user     | command                             |
        | {ID} | bh-cron-command-13.test | www-data | sh -c 'wp cron event run --due-now' |
      And STDOUT should be a table with 1 row
    When I run "bin/ee cron list --all"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | id   | site_url                | command                             |
        | {ID} | bh-cron-command-13.test | sh -c 'wp cron event run --due-now' |

  Scenario: Deleting a WordPress site removes its wp-cron job
    Given a wp site "bh-cron-command-13.test" exists
    When I run "bin/ee site delete bh-cron-command-13.test --yes"
    Then the command should succeed
      And there should be 0 cron jobs for "bh-cron-command-13.test"
      And the cron config should not have any job for "bh-cron-command-13.test"

  @destructive
  Scenario: Creating a job starts a stopped scheduler
    Given a php site "bh-cron-command-11.test" exists
    When I stop the docker container "services_global-cron_1"
      And I run "bin/ee cron create bh-cron-command-11.test --command='echo bh-cron-command-boot' --schedule='@hourly'"
    Then the command should succeed
      And the docker container "services_global-cron_1" should be running
      And the docker container "services_global-cron_1" should stay up for 10 seconds

  # Fixed in v4.13.1 (EasyEngine/cron-command#60), regression check: `ee cron delete` of the last job used to run `docker rm -f` on the scheduler.
  @destructive
  Scenario: Deleting the last job keeps the scheduler running
    Given there are no cron jobs other than the ones owned by this suite
      And a php site "bh-cron-command-14.test" exists
    When I delete every cron job owned by this suite
      And I run "bin/ee cron create bh-cron-command-14.test --command='echo bh-cron-command-last' --schedule='@hourly'"
      And I store the id of the latest cron job for "bh-cron-command-14.test" with command containing "bh-cron-command-last" as "ID"
      And I run "bin/ee cron delete {ID}"
    Then the command should succeed
      And the docker container "services_global-cron_1" should be running
      And the docker container "services_global-cron_1" should stay up for 30 seconds

  # Fixed in v4.13.1 (EasyEngine/cron-command#60), regression check: with no jobs ofelia used to exit "unable to start a empty scheduler" and restart: always looped it.
  @destructive
  Scenario: The scheduler stays up when the last site with jobs is deleted
    Given there are no cron jobs other than the ones owned by this suite
      And a php site "bh-cron-command-14.test" exists
    When I delete every cron job owned by this suite
      And I run "bin/ee cron create bh-cron-command-14.test --command='echo bh-cron-command-lastsite' --schedule='@hourly'"
      And I run "bin/ee site delete bh-cron-command-14.test --yes"
    Then the command should succeed
      And the file "{CRON_CONFIG}" should not contain "bh-cron-command-lastsite"
      And the docker container "services_global-cron_1" should stay up for 30 seconds

  # Removes the scheduler itself instead of relying on `ee cron delete` removing it, so it runs next to other sites' jobs and still tests init_global_container().
  @destructive
  Scenario: Creating a job after the scheduler was removed brings it back
    Given a php site "bh-cron-command-14.test" exists
    When I remove the docker container "services_global-cron_1"
      And I run "bin/ee cron create bh-cron-command-14.test --command='echo bh-cron-command-back-b' --schedule='@hourly'"
    Then the command should succeed
      And the docker container "services_global-cron_1" should be running
      And the docker container "services_global-cron_1" should stay up for 10 seconds
      And the cron config should have a "job-exec" job for "bh-cron-command-14.test" with command containing "bh-cron-command-back-b"

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): host jobs keep --user, ofelia rejects `user` in a job-local and the whole scheduler exits, stopping every site's cron.
  # Passes whether a fix drops the user or rejects the flag; the @destructive hook deletes the broken job.
  @destructive
  Scenario: A host job with --user keeps the scheduler running
    When I run "bin/ee cron create host --command='echo bh-cron-command-host-user' --schedule='@yearly' --user=root"
    Then no "job-local" job in the cron config should have "user"
      And the docker container "services_global-cron_1" should stay up for 20 seconds
