<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;


/**
 * Defines behat context for ``ee cron delete``.
 */
class DeleteContext implements Context
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
	 * @When I delete that cron job
	 */
	function delete_created_cron()
	{
		$this->shared_context->command = "ee cron delete {$this->shared_context->cron_created}";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}

	/**
	 * @Then I should see success message for deleting cron job
	 * @throws Exception: If the output is not as expected
	 */
	function success_message_for_deleting_cron()
	{
		$id_to_delete = $this->shared_context->cron_created;
		if (false === strpos($this->shared_context->output, "Success: Deleted cron with id $id_to_delete")) {
			throw new Exception("Expected output to contain `Deleted cron job` but got:\n\n" . $this->shared_context->output);
		}
	}

	/**
	 * @Then I should not see the cron job in the list of crons
	 * @throws Exception: If the output is not as expected
	 */
	function cron_deleted()
	{
		exec("ee cron list --all | awk '{print $1}'", $output, $return_status);
		$output = array_map(
			function ($line) {
				return trim($line);
			},
			$output
		);
		if (in_array((string) $this->shared_context->cron_created, $output)) {
			throw new Exception("Cron job was not deleted. Expected output to not contain `{$this->shared_context->sites_created[0]}` but got:\n\n" . $output);
		}
	}

	/**
	 * @When I delete a cron job that does not exist
	 * @throws Exception: If the randomness is not random?
	 */
	function delete_non_existent_cron() {
		$this->shared_context->cron_created = random_int(1, 65535);
		$this->shared_context->command = "ee cron delete {$this->shared_context->cron_created}";
		exec($this->shared_context->command, $output, $return_status);
		$this->shared_context->output = implode($output);
		$this->shared_context->return_status = $return_status;
	}

	/**
	 * @Then I should see an error message for deleting cron job
	 * @throws Exception: If the output is not empty
	 */
	function error_message_for_deleting_non_existent_cron() {
		if ("" === trim($this->shared_context->output)) {
			// Error is directly thrown to stderr
			return;
		}
		throw new Exception("Expected an error message for deleting a non-existent cron job but got an empty output.");
	}
}
