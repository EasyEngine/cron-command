<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;


function unexpectedOutput(string $command, string $output, int $return_status): string
{
	return "Command did not exit as expected. Ran `$command` and got a return status code of `$return_status` with the following output:\n\n" . $output;
}



/**
 * Defines behat context for subcommands of ``ee cron``.
 */
class SharedContext implements Context
{

	public string $command;
	public string $output;
	public int $return_status;

	public array $sites_created = [];
	public int $cron_created = -1;

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
	 * @Given EE is present
	 * @throws Exception: If EE is not present
	 */
	public function ee_is_present()
	{
		exec("command -v ee", $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception("EE is not present. Can not continue.");
		}
	}


	/**
	 * @Given No site has been created
	 * @throws Exception: If a site is present
	 */
	function no_site_created()
	{
		exec("ee site list --format=text", $output, $return_status);
		if ($return_status === 0) {
			throw new Exception(
				"The following sites are present on the ee installation:\n" .
					implode( $output ) .
					"\nNot continuing the test to not disrupt the system state. To run the test, please execute it on a fresh installation of Easy Engine."
			);
		}
	}


	/**
	 * @Given I created site `:site_name` with type `:site_type`
	 * @throws Exception: If the site creation fails
	 */
	function create_site(string $site_name, string $site_type) {
		$this->sites_created[] = $site_name;
		exec("ee site create $site_name --type=$site_type", $output, $return_status);
		if ($return_status !== 0) {
			throw new Exception("Could not create site $site_name with type $site_type. Output: " . implode($output));
		}
	}

	/**
	 * @Then exit code must be 0
	 * @throws Exception: If the return status is not 0
	 */
	function zero_status() {
		if ($this->return_status !== 0) {
			throw new Exception(unexpectedOutput($this->command, $this->output, $this->return_status));
		}
	}

		/**
	 * @Then Exit Code must not be 0
	 * @throws Exception: If the return status is 0
	 */
	function non_zero_status()
	{
		if ($this->return_status === 0) {
			throw new Exception(unexpectedOutput($this->command, $this->output, $this->return_status));
		}
	}

	/**
	 * @Then I should see `:message`
	 * @throws Exception: If the output does not contain the success message
	 */
	function see_message(string $message)
	{
		if (strpos(trim($this->output), $message) === false) {
			throw new Exception("Could not find the message '$message' in the output of the command: " . $this->output);
		}
	}

	/**
	 * @Given I created cron for site `:site_name` with schedule `:schedule` and command `:command`
	 */
	function create_cron(string $site_name, string $schedule, string $command) {
		$to_exec = "ee cron create $site_name --schedule=\"$schedule\" --command=\"$command\"";
		exec($to_exec, $_, $return_status);
		if ($return_status !== 0) {
			throw new Exception("Could not create cron job for site $site_name with schedule $schedule and command $command. Output:\n" . $output);
		}
		// Cron is created, now we need to find the cron id
		exec(
			"ee cron list $site_name | grep \"$site_name\" | grep \"$schedule\" | grep \"$command\" | awk '{print $1}'",
			$_output, $return_status
		);
		if ($return_status !== 0) {
			throw new Exception("Could not find the cron job in the list of crons. Output:\n" . $output);
		}
		$this->cron_created = (int) $_output[0];  // The cron id
	}


	/**
	 * After Scenario Cleanup Hook
	 *
	 * @AfterScenario
	 */
	function after_scenario_cleanup() {
		$this->command = "";
		$this->output = "";
		$this->return_status = 0;
		$this->cron_created = -1;

		foreach ($this->sites_created as $site) {
			exec("ee site delete $site --yes");
		}
		$this->sites_created = [];
	}

}
