Feature: ee cron create
  Site jobs run in the site's php container (ofelia job-exec), host jobs in the scheduler itself (job-local).
  Site bh-cron-command-1.test is a php site, 2 is an html site (no php container) and 3 is a disabled php site.

  Scenario: A five-field schedule gets a seconds field and the job defaults to www-data
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-c1' --schedule='*/5 * * * *'"
    Then the command should succeed
      And STDOUT should contain "Success: Cron created successfully"
    When I store the id of the latest cron job for "bh-cron-command-1.test" with command containing "bh-cron-command-c1" as "ID"
    Then the cron job "{ID}" should have site_url "bh-cron-command-1.test"
      And the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-c1'"
      And the cron job "{ID}" should have schedule "0 */5 * * * *"
      And the cron job "{ID}" should have user "www-data"
      And the cron config should have a "job-exec" job for "bh-cron-command-1.test" with command containing "bh-cron-command-c1"
      And the cron config job for "bh-cron-command-1.test" with command containing "bh-cron-command-c1" should have schedule "0 */5 * * * *"
      And the cron config job for "bh-cron-command-1.test" with command containing "bh-cron-command-c1" should have command "sh -c 'echo bh-cron-command-c1'"
      And the cron config job for "bh-cron-command-1.test" with command containing "bh-cron-command-c1" should have user "www-data"
      And the cron config job for "bh-cron-command-1.test" with command containing "bh-cron-command-c1" should have "no-overlap" "true"
      And the cron config job for "bh-cron-command-1.test" with command containing "bh-cron-command-c1" should target the php container of "bh-cron-command-1.test"
      And the docker container "services_global-cron_1" should be running

  Scenario Outline: Schedule helpers are stored verbatim
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo <marker>' --schedule='<schedule>'"
    Then the command should succeed
    When I store the id of the latest cron job for "bh-cron-command-1.test" with command containing "<marker>" as "ID"
    Then the cron job "{ID}" should have schedule "<schedule>"
      And the cron config job for "bh-cron-command-1.test" with command containing "<marker>" should have schedule "<schedule>"

    Examples:
      | schedule        | marker                   |
      | @hourly         | bh-cron-command-hourly   |
      | @daily          | bh-cron-command-daily    |
      | @midnight       | bh-cron-command-midnight |
      | @weekly         | bh-cron-command-weekly   |
      | @monthly        | bh-cron-command-monthly  |
      | @yearly         | bh-cron-command-yearly   |
      | @annually       | bh-cron-command-annually |
      | @every 10m      | bh-cron-command-every10m |
      | @every 1h10m2s  | bh-cron-command-every3u  |

  Scenario: Extra whitespace between schedule fields is tolerated
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-spaces' --schedule='  0  3 * * 1 '"
    Then the command should succeed
    When I store the id of the latest cron job for "bh-cron-command-1.test" with command containing "bh-cron-command-spaces" as "ID"
    Then the cron job "{ID}" should have schedule "0 0  3 * * 1"

  Scenario: A custom user is stored and written to the config
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-root' --schedule='@hourly' --user=root"
    Then the command should succeed
    When I store the id of the latest cron job for "bh-cron-command-1.test" with command containing "bh-cron-command-root" as "ID"
    Then the cron job "{ID}" should have user "root"
      And the cron config job for "bh-cron-command-1.test" with command containing "bh-cron-command-root" should have user "root"

  Scenario: A command that already uses sh -c is not wrapped again
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command="sh -c 'echo bh-cron-command-prewrapped'" --schedule='@hourly'"
    Then the command should succeed
    When I store the id of the latest cron job for "bh-cron-command-1.test" with command containing "bh-cron-command-prewrapped" as "ID"
    Then the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-prewrapped'"

  Scenario: Pipes, redirects and && are accepted
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-chain && date | tee /tmp/x > /dev/null' --schedule='@hourly'"
    Then the command should succeed
    When I store the id of the latest cron job for "bh-cron-command-1.test" with command containing "bh-cron-command-chain" as "ID"
    Then the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-chain && date | tee /tmp/x > /dev/null'"

  Scenario: A trailing slash on the site name is ignored
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test/ --command='echo bh-cron-command-slash' --schedule='@hourly'"
    Then the command should succeed
      And there should be 1 cron job for "bh-cron-command-1.test" with command containing "bh-cron-command-slash"
      And there should be 0 cron jobs for "bh-cron-command-1.test/"

  Scenario: The site is detected from the current directory
    Given a php site "bh-cron-command-1.test" exists
    When I run "cd {EE_ROOT_DIR}/sites/bh-cron-command-1.test && {EE_BIN} cron create --command='echo bh-cron-command-cwd' --schedule='@hourly'"
    Then the command should succeed
      And there should be 1 cron job for "bh-cron-command-1.test" with command containing "bh-cron-command-cwd"

  Scenario: A wrong site name inside a site directory suggests the detected site
    Given a php site "bh-cron-command-1.test" exists
    When I run "cd {EE_ROOT_DIR}/sites/bh-cron-command-1.test && {EE_BIN} cron create bh-cron-command-nope.test --command='echo bh-cron-command-nope' --schedule='@hourly'"
    Then the command should fail
      And STDERR should contain "bh-cron-command-nope.test is not a valid site-name. Did you mean `ee cron create bh-cron-command-1.test`?"
      And there should be 0 cron jobs for "bh-cron-command-1.test" with command containing "bh-cron-command-nope"

  Scenario: Creating the same job twice stores two independent jobs
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-twice' --schedule='@hourly'"
      And I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-twice' --schedule='@hourly'"
    Then the command should succeed
      And there should be 2 cron jobs for "bh-cron-command-1.test" with command containing "bh-cron-command-twice"

  Scenario: A host job is a job-local without container or user
    When I run "bin/ee cron create host --command='echo bh-cron-command-host-create' --schedule='@daily'"
    Then the command should succeed
      And STDOUT should contain "Success: Cron created successfully"
    When I store the id of the latest cron job for "host" with command containing "bh-cron-command-host-create" as "ID"
    Then the cron job "{ID}" should have site_url "host"
      And the cron job "{ID}" should have command "sh -c 'echo bh-cron-command-host-create'"
      And the cron job "{ID}" should have schedule "@daily"
      And the cron job "{ID}" should have an empty user
      And the cron config should have a "job-local" job for "host" with command containing "bh-cron-command-host-create"
      And the cron config job for "host" with command containing "bh-cron-command-host-create" should not have container
      And the cron config job for "host" with command containing "bh-cron-command-host-create" should not have user

  Scenario: Without a site name outside a site directory the command fails
    When I run "bin/ee cron create --command='echo bh-cron-command-nosite' --schedule='@hourly'"
    Then the command should fail
      And STDERR should contain "Could not find the site you wish to run cron create command on."

  Scenario: An unknown site is rejected
    When I run "bin/ee cron create bh-cron-command-missing.test --command='echo bh-cron-command-missing' --schedule='@hourly'"
    Then the command should fail
      And STDERR should contain "Could not find the site you wish to run cron create command on."
      And there should be 0 cron jobs for "bh-cron-command-missing.test"

  Scenario: A site without a php container is rejected
    Given a html site "bh-cron-command-2.test" exists
    When I run "bin/ee cron create bh-cron-command-2.test --command='echo bh-cron-command-html' --schedule='@hourly'"
    Then the command should fail
      And STDERR should contain "bh-cron-command-2.test does not have PHP container."
      And there should be 0 cron jobs for "bh-cron-command-2.test"

  Scenario: A disabled site is rejected
    Given a php site "bh-cron-command-3.test" exists
      And the site "bh-cron-command-3.test" is disabled
    When I run "bin/ee cron create bh-cron-command-3.test --command='echo bh-cron-command-disabled' --schedule='@hourly'"
    Then the command should fail
      And STDERR should contain "Site bh-cron-command-3.test is not enabled. Use `ee site enable bh-cron-command-3.test` to enable it."
      And there should be 0 cron jobs for "bh-cron-command-3.test"

  Scenario Outline: Required flags are enforced
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test <flags>"
    Then the command should fail
      And STDERR should contain "missing --<missing> parameter"
      And there should be 0 cron jobs for "bh-cron-command-1.test" with command containing "bh-cron-command-required"

    Examples:
      | flags                                          | missing  |
      | --schedule='@hourly'                           | command  |
      | --command='echo bh-cron-command-required'      | schedule |

  Scenario: Too many positional arguments are rejected
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test extra --command='echo bh-cron-command-extra' --schedule='@hourly'"
    Then the command should fail
      And STDERR should contain "Too many positional arguments: extra"

  Scenario: An unknown flag is rejected
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-flag' --schedule='@hourly' --users=root"
    Then the command should fail
      And STDERR should contain "unknown --users parameter"
      And there should be 0 cron jobs for "bh-cron-command-1.test" with command containing "bh-cron-command-flag"

  Scenario Outline: Schedules without exactly five fields are rejected
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-badfields' --schedule='<schedule>'"
    Then the command should fail
      And STDERR should contain "Schedule format should be same as Linux cron or schedule helper syntax(Check help for this)"
      And there should be 0 cron jobs for "bh-cron-command-1.test" with command containing "bh-cron-command-badfields"

    Examples:
      | schedule        |
      | * * * *         |
      | 0 * * * * *     |
      | 1 2 3 4 5 6 7   |
      | hourly          |

  Scenario Outline: Commands that break the INI config are rejected
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='<command>' --schedule='@hourly'"
    Then the command should fail
      And STDERR should contain "<error>"
      And there should be 0 cron jobs for "bh-cron-command-1.test" with command containing "bh-cron-command-ini"

    Examples:
      | command                                  | error                                                   |
      | echo bh-cron-command-ini; date           | Command chaining using `;` - semi-colon is not supported |
      | echo bh-cron-command-ini # comment       | EasyEngine does not support commands with #             |

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): schedules are only checked for a leading @ or five fields; ofelia then silently drops the job ("Failed to register job").
  Scenario Outline: Invalid schedules are rejected at creation
    Given a php site "bh-cron-command-1.test" exists
    # A valid job keeps ofelia from exiting when every other job fails to register.
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-anchor' --schedule='@yearly'"
      And I run "bin/ee cron create bh-cron-command-1.test --command='echo <marker>' --schedule='<schedule>'"
    Then the command should fail
      And there should be 0 cron jobs for "bh-cron-command-1.test" with command containing "<marker>"

    Examples:
      | schedule      | marker                   |
      | @bogus        | bh-cron-command-bogus    |
      | @every        | bh-cron-command-everyraw |
      | 99 * * * *    | bh-cron-command-range    |
      | a b c d e     | bh-cron-command-letters  |

  # An empty value fails the five-field check.
  Scenario: An empty schedule is rejected
    Given a php site "bh-cron-command-1.test" exists
    When I run "bin/ee cron create bh-cron-command-1.test --command='echo bh-cron-command-empty' --schedule=''"
    Then the command should fail
      And there should be 0 cron jobs for "bh-cron-command-1.test" with command containing "bh-cron-command-empty"
