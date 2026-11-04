Feature: Cron command help and usage

  Scenario: ee cron lists its subcommands
    When I run "bin/ee cron"
    Then STDOUT should contain "usage: ee cron create [<site-name>] --command=<command> --schedule=<schedule> [--user=<user>]"
      And STDOUT should contain "or: ee cron delete <cron-id>"
      And STDOUT should contain "or: ee cron list [<site-name>] [--all]"
      And STDOUT should contain "or: ee cron run-now <cron-id>"
      And STDOUT should contain "or: ee cron update <id> [--site=<site>] [--command=<command>] [--schedule=<schedule>] [--user=<user>]"

  Scenario Outline: ee help documents every cron subcommand
    When I run "bin/ee help cron <subcommand>"
    Then the command should succeed
      And STDOUT should contain "ee cron <subcommand>"
      And STDOUT should contain "<option>"

    Examples:
      | subcommand | option                |
      | create     | --schedule=<schedule> |
      | create     | @every <duration>     |
      | update     | [--site=<site>]       |
      | list       | [--all]               |
      | delete     | <cron-id>             |
      | run-now    | <cron-id>             |

  Scenario: An unknown cron subcommand is rejected
    When I run "bin/ee cron frobnicate"
    Then the command should fail
      And STDERR should contain "'frobnicate' is not a registered subcommand of 'cron'"

  Scenario Outline: Subcommands that need an id print their usage without one
    When I run "bin/ee cron <subcommand>"
    Then the command should fail
      And STDOUT should contain "usage: ee cron <subcommand>"

    Examples:
      | subcommand |
      | update     |
      | delete     |
      | run-now    |
