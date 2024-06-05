<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;


function unexpectedOutput(string $command, string $output, int $return_status): string
{
	return "Command did not exit as expected. Ran `$command` and got a return status code of `$return_status` with the following output:\n\n" . $output;
}


/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{

	public string $command;
	public string $output;
	public int $return_status;

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
	 */
	function no_site_created()
	{
		exec("ee site list --format=text", $output, $return_status);
		if ($return_status === 0) {
			throw new Exception(
				"The following sites are present on the ee installation:\n" .
					$output .
					"\nNot continuing the test to not disrupt the system state. To run the test, please execute it on a fresh installation of Easy Engine."
			);
		}
	}

	/**
	 * @When I list all of the cron entries
	 */
	function list_cron()
	{
		$this->command = "ee cron list --all";
		exec($this->command, $output, $return_status);
		$this->output = implode($output);
		$this->return_status = $return_status;
	}

	/**
	 * @Then Exit Code must not be 0
	 */
	function non_zero_status()
	{
		if ($this->return_status === 0) {
			throw new Exception(unexpectedOutput($this->command, $this->output, $this->return_status));
		}
	}


	/**
	 * @Then I get an error message for no cron jobs
	 */
	function no_cron_error()
	{
		if ("" !== trim($this->output)) {
			throw new Exception(unexpectedOutput($this->command, $this->output, $this->return_status));
		}
	}
}
