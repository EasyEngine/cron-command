Feature: Cron-Command -> Delete
  We have a command that we make use of in order to manage cron-jobs
  run by easyengine using ofelia cron manager. This is a subcommand
  used to remove a cron-job from the list of cron-jobs managed by
  ofelia, based on the said job's ID.

  Scenario:
    I can use the delete command to remove a cron-job from the list
    of cron-jobs managed by ofelia, based on the said job's ID.

    Given EE is present
    And I created site `test.site` with type `wp`
    And I created cron for site `test.site` with schedule `"* * * * *"` and command `"echo Hello World"`
    When I delete that cron job
    Then I should see success message for deleting cron job
    And I should not see the cron job in the list of crons
    And Exit Code must be 0

Scenario:
  If I try to delete a cron-job that does not exist, I should see an error message

  Given EE is present
  When I delete a cron job that does not exist
  Then I should see an error message for deleting cron job
  And Exit Code must not be 0
