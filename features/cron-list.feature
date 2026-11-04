Feature: ee cron list
  `ee cron list` has no --format flag; the table is tab-separated when piped, which the table steps parse.

  Scenario: Listing a site shows its jobs with every column
    Given a php site "bh-cron-command-4.test" exists
    When I run "bin/ee cron create bh-cron-command-4.test --command='echo bh-cron-command-list-a' --schedule='0 * * * *'"
      And I run "bin/ee cron create bh-cron-command-4.test --command='echo bh-cron-command-list-b' --schedule='@daily' --user=root"
      And I store the id of the latest cron job for "bh-cron-command-4.test" with command containing "bh-cron-command-list-a" as "A"
      And I store the id of the latest cron job for "bh-cron-command-4.test" with command containing "bh-cron-command-list-b" as "B"
      And I run "bin/ee cron list bh-cron-command-4.test"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | id  | site_url               | user     | command                         | schedule    |
        | {A} | bh-cron-command-4.test | www-data | sh -c 'echo bh-cron-command-list-a' | 0 0 * * * * |
        | {B} | bh-cron-command-4.test | root     | sh -c 'echo bh-cron-command-list-b' | @daily      |

  Scenario: Listing a site shows only that site's jobs
    Given a php site "bh-cron-command-4.test" exists
    When I run "bin/ee cron create bh-cron-command-4.test --command='echo bh-cron-command-list-only' --schedule='@hourly'"
      And I run "bin/ee cron create host --command='echo bh-cron-command-list-host-a' --schedule='@hourly'"
      And I run "bin/ee cron list bh-cron-command-4.test"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | site_url               | command                                 |
        | bh-cron-command-4.test | sh -c 'echo bh-cron-command-list-only'  |
      And STDOUT should be a table not containing rows:
        | site_url |
        | host     |

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): format_items() looks up every column on every row, and Base::__isset() is false for the NULL user of a host job, so the listing fails with "Invalid field: user.".
  Scenario: Listing host shows only host jobs
    Given a php site "bh-cron-command-4.test" exists
    When I run "bin/ee cron create host --command='echo bh-cron-command-list-host-b' --schedule='@weekly'"
      And I run "bin/ee cron create bh-cron-command-4.test --command='echo bh-cron-command-list-nothost' --schedule='@hourly'"
      And I store the id of the latest cron job for "host" with command containing "bh-cron-command-list-host-b" as "H"
      And I run "bin/ee cron list host"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | id  | site_url | user | command                                | schedule |
        | {H} | host     |      | sh -c 'echo bh-cron-command-list-host-b' | @weekly  |
      And STDOUT should be a table not containing rows:
        | site_url               |
        | bh-cron-command-4.test |

  # Fixed in v4.13.1 (EasyEngine/cron-command#60): any listed host job without a user fails the listing, whatever its position.
  Scenario: --all lists site and host jobs together
    Given a php site "bh-cron-command-4.test" exists
    When I run "bin/ee cron create bh-cron-command-4.test --command='echo bh-cron-command-list-all-site' --schedule='@monthly'"
      And I run "bin/ee cron create host --command='echo bh-cron-command-list-all-host' --schedule='@monthly'"
      And I run "bin/ee cron list --all"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | site_url               | command                                   |
        | host                   | sh -c 'echo bh-cron-command-list-all-host' |
        | bh-cron-command-4.test | sh -c 'echo bh-cron-command-list-all-site' |

  Scenario: A site argument wins over --all
    Given a php site "bh-cron-command-4.test" exists
    When I run "bin/ee cron create bh-cron-command-4.test --command='echo bh-cron-command-list-all-site2' --schedule='@monthly'"
      And I run "bin/ee cron create host --command='echo bh-cron-command-list-all-host2' --schedule='@monthly'"
      And I run "bin/ee cron list bh-cron-command-4.test --all"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | site_url               | command                                    |
        | bh-cron-command-4.test | sh -c 'echo bh-cron-command-list-all-site2' |
      And STDOUT should be a table not containing rows:
        | site_url |
        | host     |

  Scenario: The site is detected from the current directory
    Given a php site "bh-cron-command-4.test" exists
    When I run "bin/ee cron create bh-cron-command-4.test --command='echo bh-cron-command-list-cwd' --schedule='@hourly'"
      And I run "cd {EE_ROOT_DIR}/sites/bh-cron-command-4.test && {EE_BIN} cron list"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | site_url               | command                                |
        | bh-cron-command-4.test | sh -c 'echo bh-cron-command-list-cwd'  |

  Scenario: Without a site name outside a site directory the command fails
    When I run "bin/ee cron list"
    Then the command should fail
      And STDERR should contain "Could not find the site you wish to run cron list command on."

  Scenario: An unknown site is rejected
    When I run "bin/ee cron list bh-cron-command-missing.test"
    Then the command should fail
      And STDERR should contain "Could not find the site you wish to run cron list command on."

  Scenario: A site without jobs reports that nothing was found
    Given a php site "bh-cron-command-5.test" exists
    When I run "bin/ee cron list bh-cron-command-5.test"
    Then the command should fail
      And STDERR should contain "Error: No cron jobs found."

  Scenario: Listing a disabled site still works
    Given a php site "bh-cron-command-5.test" exists
      And the site "bh-cron-command-5.test" is enabled
    When I run "bin/ee cron create bh-cron-command-5.test --command='echo bh-cron-command-list-disabled' --schedule='@hourly'"
      And the site "bh-cron-command-5.test" is disabled
      And I run "bin/ee cron list bh-cron-command-5.test"
    Then the command should succeed
      And STDOUT should be a table containing rows:
        | site_url               | command                                      |
        | bh-cron-command-5.test | sh -c 'echo bh-cron-command-list-disabled'   |
      And STDOUT should be a table with 1 row

  # Empties the cron table, so it needs a machine where every job belongs to this suite.
  @destructive
  Scenario: --all with an empty cron table reports that nothing was found
    Given there are no cron jobs other than the ones owned by this suite
    When I delete every cron job owned by this suite
      And I run "bin/ee cron list --all"
    Then the command should fail
      And STDERR should contain "Error: No cron jobs found."
