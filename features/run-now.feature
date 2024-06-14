Feature: Cron-Command -> Run-Now
  We have a command that we make use of in order to manage cron-jobs
  run by easyengine using ofelia cron manager. This is a subcommand
  used to execute a given cron-job immediately based on its id.

  Scenario:
    If I have a cron-job that is scheduled to run at a later time
    and I want to run it immediately, I can make use of the
    `ee cron run-now` command to execute the cron-job immediately,
    and get its output in the console.

    Given EE is present
    And I created site `test.site` with type `wp`
    And I created cron for site `test.site` with schedule `"* * * * *"` and command `"echo Hello World"`
    When I run that cron job immediately
    Then I should see `"Hello World"`
    And Exit Code must be 0

Scenario:
  If I try to run a cron job that does not exist, I should see an error message

  Given EE is present
  When I run a cron job that does not exist
  Then I should see an error message for cron job not found
  And Exit Code must not be 0
