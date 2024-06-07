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
	 * After Scenario Cleanup Hook
	 *
	 * @AfterScenario
	 */
	function after_scenario_cleanup() {
		$this->command = "";
		$this->output = "";
		$this->return_status = 0;

		foreach ($this->sites_created as $site) {
			exec("ee site delete $site --yes");
		}
		$this->sites_created = [];
	}

}
