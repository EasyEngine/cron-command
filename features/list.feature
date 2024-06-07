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

  Scenario:
    If I list all of the cron entries and there are sites
    which require a cron service (type wp), then I should
    get a list of cron jobs for those sites

    Given EE is present
    And I created site `test.site` with type `wp`
    And I created site `test2.site` with type `wp`
    And I created site `test3.site` with type `wp`
    When I list all of the cron entries
    Then I should see a list of cron jobs for those sites
    And Exit Code must be 0

  Scenario:
    If I list all of the cron entries for a site which does not
    requires a cron service, then I should get an error message
    for no cron jobs

    Given EE is present
    And I created site `test.site` with type `html`
    When I list cron jobs for the site `test.site`
    Then I get an error message for no cron jobs
    And Exit Code must not be 0


  Scenario:
    If I list all of the cron entries for a site which requires
    a cron service, then I should get a list of cron jobs for
    that site

    Given EE is present
    And I created site `test.site` with type `wp`
    When I list cron jobs for the site `test.site`
    Then I should see a list of cron jobs for the site `test.site`
    And Exit Code must be 0
