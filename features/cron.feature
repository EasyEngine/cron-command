Feature: Cron-Command
  We have a command that we make use of in order to manage cron-jobs
  run by easyengine using ofelia cron manager.

  Scenario:
    If I list all of the cron entries and there are no sites
    present, then I should get an error message for no cron jobs

    Given EE is present
    And No site has been created
    When I list all of the cron entries
    Then I get an error message for no cron jobs
    And Exit Code must not be 0

