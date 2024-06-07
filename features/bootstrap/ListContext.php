<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;


function unexpectedOutput(string $command, string $output, int $return_status): string
{
	return "Command did not exit as expected. Ran `$command` and got a return status code of `$return_status` with the following output:\n\n" . $output;
}


/**
 * Defines behat context for ``ee cron list``.
 */
class ListContext implements Context
{

	private SharedContext $shared_context;

	/**
	 * Initializes context.
	 *
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 */
	public function __construct()
	{
	}

	/**
	 * @BeforeScenario
	 */
	public function gatherSharedContext(BeforeScenarioScope $scope)
	{
		$this->shared_context = $scope->getEnvironment()->getContext(SharedContext::class);
	}

	/**
	 * @When I list all of the cron entries
	 */
	function list_cron()
	{
		$this->shared_context->command = "ee cron list --all";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}


	/**
	 * @Then I get an error message for no cron jobs
	 * @throws Exception: If the output is not empty
	 */
	function no_cron_error()
	{
		if ("" !== trim($this->shared_context->output)) {
			throw new Exception(unexpectedOutput($this->shared_context->command, $this->shared_context->output, $this->shared_context->return_status));
		}
	}

	/**
	 * @Then I should see a list of cron jobs for those sites
	 * @throws Exception: If the site name is not found in the output
	 */
	function crons_for_all_sites() {
		$this->shared_context->command = "ee cron list --all";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;

		foreach ($this->shared_context->sites_created as $site) {
			if (strpos($this->shared_context->output, $site) === false) {
				throw new Exception("Could not find cron job for site $site in the output of the command: " . $this->shared_context->output);
			}
		}
	}


	/**
	 * @When I list cron jobs for the site `:site_name`
	 */
	function list_cron_for_site(string $site_name) {
		$this->shared_context->command = "ee cron list $site_name";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}

	/**
	 * @Then I should see a list of cron jobs for the site `:site_name`
	 * @throws Exception: If the site name is not found in the output
	 */
	function crons_for_site(string $site_name) {
		if (strpos($this->shared_context->output, $site_name) === false) {
			throw new Exception("Could not find cron job for site $site_name in the output of the command: " . $this->shared_context->output);
		}
	}

}
