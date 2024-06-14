<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;


/**
 * Defines behat context for ``ee cron run-now``.
 */
class RunNowContext implements Context
{

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
	 * @When I run that cron job immediately
	 */
	function run_cron_immediately()
	{
		$this->shared_context->command = "ee cron run-now {$this->shared_context->cron_created}";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}

	/**

	 * @When I run a cron job that does not exist
	 * @throws Exception: If the randomness is not random?
	 */
	function delete_non_existent_cron() {
		$this->shared_context->cron_created = random_int(1, 65535);
		$this->shared_context->command = "ee cron run-now {$this->shared_context->cron_created}";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}

	/**
	 * @Then I should see an error message for cron job not found
	 * @throws Exception: If the output is not empty
	 */
	function error_message_for_deleting_non_existent_cron() {
		if ("" === trim($this->shared_context->output)) {
			// Error is directly thrown to stderr
			return;
		}
		throw new Exception(unexpectedOutput($this->shared_context->command, $this->shared_context->output, $this->shared_context->return_status));
	}
}
