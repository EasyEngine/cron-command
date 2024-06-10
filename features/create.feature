Feature: Cron-Command -> Create
  We have a command that we make use of in order to manage cron-jobs
  run by easyengine using ofelia cron manager. This is a subcommand
  used to create any cron jobs which are to be run on a particular
  site's containers.

  Scenario:
    I can use the create command to create a cron job for an existing site

    Given EE is present
    And I created site `test.site` with type `wp`
    When I create a cron for site `test.site` with schedule `"* 1 * * *"` and command `"echo Hello World"`
    Then I should see `"Success: Cron created successfully"`
    And I should see the relavant cron job in list of crons for site `test.site`
    And Exit Code must be 0

  Scenario:
    I can not create a cron job for a non-existing site, the command should error out

    Given EE is present
    # Notice that the site `test.site` has not been created
    When I create a cron for site `test.site` with schedule `"* 1 * * *"` and command `"echo Hello World"`
    Then Cron creation should show an error message
    And Exit Code must not be 0
