<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;


/**
 * Defines behat context for ``ee cron update``.
 */
class UpdateContext implements Context
{
	private string $updated_site_name;
	private string $updated_schedule;
	private string $updated_command;

	private SharedContext $shared_context;

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
	 * @When I update that cron to site `:site_name` with schedule `:schedule` and command `:command`
	 */
	function update_created_cron(string $site_name, string $schedule, string $command) {
		$this->updated_site_name = $site_name;
		$this->updated_schedule = $schedule;
		$this->updated_command = $command;
		$this->shared_context->command = "ee cron update {$this->shared_context->cron_created} --site=\"$site_name\" --schedule=\"$schedule\" --command=\"$command\"";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}

	/**
	 * @Then The cron job listing should reflect the changes
	 * @throws Exception when the changes are not reflected properly
	 */
	function check_updated_cron() {
		$cron_id = $this->shared_context->cron_created;
		// Filter out the output to only include our specific cron job
		exec("ee cron list $this->updated_site_name | grep '^$cron_id\s'", $output, $return_status);
		$output = implode($output);
		if (
			false === strpos($output, $this->shared_context->cron_created) ||
			false === strpos($output, $this->updated_site_name) ||
			false === strpos($output, $this->updated_schedule) ||
			false === strpos($output, $this->updated_command)
		) {
			throw new Exception("Expected output to contain the updated cron job details but got:\n\n" . $output);
		}
	}
}
