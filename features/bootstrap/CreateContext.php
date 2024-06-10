<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;


/**
 * Defines behat context for ``ee cron create``.
 */
class CreateContext implements Context
{

	private SharedContext $shared_context;
	private string $cronCommand;
	private string $site;
	private string $schedule;

	/**
	 * Initializes context.
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
	 * @When I create a cron for site `:site` with schedule `:schedule` and command `:command`
	 */
	function create_cron(string $site, string $schedule, string $command)
	{
		$this->site = $site;
		$this->schedule = $schedule;
		$this->cronCommand = $command;
		$this->shared_context->command = "ee cron create $site --schedule=\"$schedule\" --command=\"$command\"";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}

	/**
	 * @Then I should see the relavant cron job in list of crons for site `:site`
	 * @throws Exception: If the cron job is not found
	 */
	function cron_job_exists(string $site)
	{
		exec("ee cron list $site", $output, $return_status);
		if ($return_status !== 0) {
			throw new Exception("Could not list crons for site $site. Got a return status code of $return_status with the following output:\n\n" . implode($output));
		}
		if (
			// All three of these should be present in the output, otherwise the cron job was not created properly
			strpos(implode($output), $this->cronCommand) === false ||
			strpos(implode($output), $this->schedule) === false ||
			strpos(implode($output), $this->site) === false
		) {
			throw new Exception("Could not find the cron job in the list of crons for site $site. The cron job was supposed to be:\n\n" . $this->cronCommand . "\n\nBut the list of crons for site $site was:\n\n" . implode($output));
		}
	}

	/**
	 * @Then Cron creation should show an error message
	 * @throws Exception: If the cron creation does not fail
	 */
	function cron_creation_errors()
	{
		if ( trim($this->shared_context->output) === "" ) {
			# Error message is thrown directly to stderr, so there shouldn't be any output
			return;
		}
		throw new Exception("Cron creation did not fail as expected. The output was:\n\n" . $this->shared_context->output);
	}
}
