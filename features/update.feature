Feature: Cron-Command -> Update
  We have a command that we make use of in order to manage cron-jobs
  run by easyengine using ofelia cron manager. This is a subcommand
  used to update any cron jobs which are to be run by this manager.

  Scenario:
    I can use the update command to update various aspects of a cron
    job that is to be run by ofelia cron manager.

    Given EE is present
    And I created site `test.site` with type `wp`
    And I created site `test2.site` with type `wp`
    And I created cron for site `test.site` with schedule `"* * * 1 *"` and command `"echo 'Hello World'"`
    When I update that cron to site `test2.site` with schedule `"* 1 * * *"` and command `"echo 'It Works!'"`
    Then The cron job listing should reflect the changes
    And I should see `"Success: Cron update Successfully"`
    And Exit Code must be 0
