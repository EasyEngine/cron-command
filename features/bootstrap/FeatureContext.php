<?php
/**
 * Behat tests.
 *
 * @package ee-cli
 */

/* Start: Loading required files to enable EE::launch() in tests. */
define( 'EE_ROOT', __DIR__ . '/../..' );

include_once( EE_ROOT . '/php/class-ee.php' );
include_once( EE_ROOT . '/php/EE/Runner.php' );
include_once( EE_ROOT . '/php/utils.php' );

define( 'EE', true );
define( 'EE_VERSION', trim( file_get_contents( EE_ROOT . '/VERSION' ) ) );

$root_alias = '/opt';
if ( 'Darwin' === php_uname( 's' ) ) {
	$sys_name   = posix_getpwuid(posix_geteuid())['name'];
	if ( ! empty( $sys_name ) ) {
		$root_alias = '/Users/' . $sys_name;
	}
}
if ( ! defined( 'EE_ROOT_DIR' ) ) {
	define( 'EE_ROOT_DIR', $root_alias . '/easyengine' );
}

require_once EE_ROOT . '/php/bootstrap.php';

if ( ! class_exists( 'EE\Runner' ) ) {
	require_once EE_ROOT . '/php/EE/Runner.php';
}

if ( ! class_exists( 'EE\Configurator' ) ) {
	require_once EE_ROOT . '/php/EE/Configurator.php';
}

$logger_dir = EE_ROOT . '/php/EE/Loggers';
$iterator   = new \DirectoryIterator( $logger_dir );

// Make sure the base class is declared first.
include_once "$logger_dir/Base.php";

foreach ( $iterator as $filename ) {
	if ( '.php' !== pathinfo( $filename, PATHINFO_EXTENSION ) ) {
		continue;
	}

	include_once "$logger_dir/$filename";
}
$runner = \EE::get_runner();
$runner->init_logger();
$runner->init_config();
/* End. Loading required files to enable EE::launch() in tests. */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;

use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

define( 'EE_SITE_ROOT', EE_ROOT_DIR . '/sites' );

class FeatureContext implements Context
{
	public $command;
	public $commands = [];
	public $webroot_path;
	public $ee_path;

	/**
	 * Values stored by steps, substituted as {NAME} in commands and step arguments.
	 *
	 * @var array
	 */
	public $variables = [];

	/**
	 * Whether the global cron container existed when the feature started, so cleanup can restore it.
	 *
	 * @var bool
	 */
	public static $cron_container_existed = false;

	/**
	 * Set when a step removed the scheduler on purpose, so cleanup recreates it even if it was created during the feature.
	 *
	 * @var bool
	 */
	public static $cron_container_removed = false;

	/**
	 * Every site, and every host cron command, created by this suite starts with this prefix. Cleanup touches nothing else.
	 */
	const TEST_PREFIX = 'bh-cron-command-';

	/**
	 * Initializes context.
	 */
	public function __construct()
	{
		$this->commands = [];
		$this->ee_path = getcwd();
	}

	/**
	 * @Given ee phar is generated
	 */
	public function eePharIsPresent()
	{
		// Checks if phar already exists, replaces it
		if(file_exists('ee-old.phar')) {
			// Below exec call is required as currenly `ee cli update` is ran with root
			// which updates ee.phar with root privileges.
			exec("sudo rm ee.phar");
			copy('ee-old.phar','ee.phar');
			return 0;
		}
		exec("php -dphar.readonly=0 utils/make-phar.php ee.phar", $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception("Unable to generate phar" . $return_status);
		}

		// Cache generaed phar as it is expensive to generate one
		copy('ee.phar','ee-old.phar');
	}

	/**
	 * @Given :command is installed
	 */
	public function isInstalled($command)
	{
		exec("type " . $command, $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception($command . " is not installed! Exit code is:" . $return_status);
		}
	}

	/**
	 * @When /^I run (?|'(.*)'|"(.*)")$/
	 */
	public function iRun($command)
	{
		$this->commands[] = EE::launch($this->replaceVariables($command), false, true);
	}

	/**
	 * @Then return value should be 0
	 */
	public function returnValueShouldBe0()
	{
		if ( 0 !== $this->commands[0]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then return value of command :index should be 0
	 */
	public function returnValueOfCommandShouldBe0($index)
	{
		if ( 0 !== $this->commands[ $index - 1 ]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then After delay of :time seconds
	 */
	public function afterDelayOfSeconds( $time )
	{
		sleep( $time );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return exactly$/
	 */
	public function stdoutShouldReturnExactly($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return exactly$/
	 */
	public function stdoutOfCommandShouldReturnExactly($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return something like$/
	 */
	public function stdoutShouldReturnSomethingLike($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}


	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return something like$/
	 */
	public function stdoutOfCommandShouldReturnSomethingLike($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then The site :site should be :type multisite
	 */
	public function theSiteShouldBeMultisite( $site, $type )
	{
		$result = EE::launch( "cd " . EE_SITE_ROOT . "/$site && docker-compose exec --user='www-data' php sh -c 'wp config get SUBDOMAIN_INSTALL'", false, true );

		if( $result->stderr ) {
			throw new Exception("Found error while executing command: $result->stderr");
		}

		if($type === 'subdomain' && trim($result->stdout) !== '1') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be 1. Got: $result->stdout");
		}
		else if($type === 'subdir' && trim($result->stdout) !== '') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be empty. Got: $result->stdout");
		}
		else if($type !== 'subdomain' && $type !== 'subdir') {
			throw new Exception("Found unknown site type: $type");
		}
	}

	/**
	 * @Then The :site db entry should be removed
	 */
	public function theDbEntryShouldBeRemoved($site)
	{
		$out = shell_exec("sudo bin/ee site list");
		if (strpos($out, $site) !== false) {
			throw new Exception("$site db entry not been removed!");
		}

	}

	/**
	 * @Then The :site webroot should be removed
	 */
	public function theWebrootShouldBeRemoved($site)
	{
		if ( file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been removed!");
		}
	}

	/**
	 * @Then Following containers of site :site should be removed:
	 */
	public function followingContainersOfSiteShouldBeRemoved($site, TableNode $table)
	{
		$containers = $table->getHash();
		$site_name = implode(explode('.', $site));

		foreach ($containers as $container) {

			$sevice = $container['container'];
			$container_name = $site_name . '_' . $sevice . '_1';

			exec("docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return);
			if (!$return) {
				throw new Exception("$container_name has not been removed!");
			}
		}
	}

	/**
	 * @Then The site :site should have webroot
	 */
	public function theSiteShouldHaveWebroot($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been created!");
		}
	}

	/**
	 * @Then The site :site should have WordPress
	 */
	public function theSiteShouldHaveWordpress($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site . '/app/wp-config.php' ) ) {
			throw new Exception("WordPress data not found!");
		}
	}

	/**
	 * @Then Request on :site should contain following headers:
	 */
	public function requestOnShouldContainFollowingHeaders($site, TableNode $table)
	{
		$url = 'http://' . $site;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with header :header should contain following headers:
	 */
	public function requestOnWithHeaderShouldContainFollowingHeaders($host, $header, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [ $header ]);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with resolve option :resolve should contain following headers:
	 */
	public function requestOnWithResolveOptionShouldContainFollowingHeaders($host, $resolve, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RESOLVE, [ $resolve ]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);
		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupScenario(AfterScenarioScope $scope)
	{
		$this->commands  = [];
		$this->variables = [];
		chdir($this->ee_path);
	}

	/**
	 * @Then There should be :expected_running_containers containers with labels
	 */
	public function thereShouldBeContainersWithLabel($expected_running_containers, PyStringNode $pyStringNode)
	{
		$labels = $pyStringNode->getStrings();
		$label_string = implode(' -f label=',$labels);

		$result = EE::launch( "docker ps -qf label=$label_string | wc -l", false, true );
		$running_containers = (int) trim($result->stdout);

		if ( (int) $expected_running_containers !== $running_containers ) {
			throw new Exception("Expected $expected_running_containers running containers. Found: $running_containers");
		}
	}

	/*
	 * =====================================================================
	 * Shared generic steps (cron-command suite).
	 * Named generically so they can be moved into a common context later.
	 * All string arguments support {NAME} substitution of stored variables.
	 * =====================================================================
	 */

	const CRON_CONTAINER = 'services_global-cron_1';

	/**
	 * Replaces {NAME} placeholders with stored variables and a few built-ins.
	 */
	public function replaceVariables( $text )
	{
		$vars = array_merge(
			[
				'EE_ROOT_DIR' => EE_ROOT_DIR,
				'EE_BIN'      => realpath( EE_ROOT . '/bin/ee' ),
				'CRON_CONFIG' => self::cronConfigPath(),
			],
			$this->variables
		);
		foreach ( $vars as $name => $value ) {
			$text = str_replace( '{' . $name . '}', $value, $text );
		}

		return $text;
	}

	private static function cronConfigPath()
	{
		return EE_ROOT_DIR . '/services/cron/conf/config.ini';
	}

	private static function db()
	{
		$pdo = new PDO( 'sqlite:' . EE_ROOT_DIR . '/db/ee.sqlite' );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		return $pdo;
	}

	private static function query( $sql, array $params = [] )
	{
		$stmt = self::db()->prepare( $sql );
		$stmt->execute( $params );

		return $stmt->fetchAll( PDO::FETCH_ASSOC );
	}

	private function lastCommand()
	{
		if ( empty( $this->commands ) ) {
			throw new Exception( 'No command has been run in this scenario.' );
		}

		return end( $this->commands );
	}

	private static function describeResult( $result )
	{
		return sprintf(
			"Command: %s\nExit code: %d\nSTDOUT:\n%s\nSTDERR:\n%s",
			$result->command,
			$result->return_code,
			$result->stdout,
			$result->stderr
		);
	}

	private static function stripColors( $text )
	{
		return preg_replace( '/\e\[[0-9;]*m/', '', $text );
	}

	private function outputOf( $stream )
	{
		$result = $this->lastCommand();

		return self::stripColors( 'STDOUT' === $stream ? $result->stdout : $result->stderr );
	}

	private static function shell( $command )
	{
		return EE::launch( $command, false, true );
	}

	/* ---------- Command results ---------- */

	/**
	 * @Then the command should succeed
	 */
	public function theCommandShouldSucceed()
	{
		$result = $this->lastCommand();
		if ( 0 !== $result->return_code ) {
			throw new Exception( "Expected exit code 0.\n" . self::describeResult( $result ) );
		}
	}

	/**
	 * @Then the command should fail
	 */
	public function theCommandShouldFail()
	{
		$result = $this->lastCommand();
		if ( 0 === $result->return_code ) {
			throw new Exception( "Expected a non-zero exit code.\n" . self::describeResult( $result ) );
		}
	}

	/**
	 * @Then the command should exit with code :code
	 */
	public function theCommandShouldExitWithCode( $code )
	{
		$result = $this->lastCommand();
		if ( (int) $code !== $result->return_code ) {
			throw new Exception( "Expected exit code $code.\n" . self::describeResult( $result ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should contain "([^"]*)"$/
	 */
	public function outputShouldContain( $stream, $text )
	{
		$text = $this->replaceVariables( $text );
		if ( false === strpos( $this->outputOf( $stream ), $text ) ) {
			throw new Exception( "$stream does not contain '$text'.\n" . self::describeResult( $this->lastCommand() ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should not contain "([^"]*)"$/
	 */
	public function outputShouldNotContain( $stream, $text )
	{
		$text = $this->replaceVariables( $text );
		if ( false !== strpos( $this->outputOf( $stream ), $text ) ) {
			throw new Exception( "$stream unexpectedly contains '$text'.\n" . self::describeResult( $this->lastCommand() ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should contain:$/
	 */
	public function outputShouldContainBlock( $stream, PyStringNode $expected )
	{
		$text = $this->replaceVariables( $expected->getRaw() );
		if ( false === strpos( $this->outputOf( $stream ), $text ) ) {
			throw new Exception( "$stream does not contain:\n$text\n" . self::describeResult( $this->lastCommand() ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should be empty$/
	 */
	public function outputShouldBeEmpty( $stream )
	{
		if ( '' !== trim( $this->outputOf( $stream ) ) ) {
			throw new Exception( "$stream is not empty.\n" . self::describeResult( $this->lastCommand() ) );
		}
	}

	/**
	 * Parses `format_items( 'table' )` output: tab-separated when piped, ASCII art on a TTY.
	 */
	private static function parseTable( $output )
	{
		$rows    = [];
		$headers = null;
		foreach ( explode( "\n", self::stripColors( $output ) ) as $line ) {
			if ( '' === trim( $line ) || preg_match( '/^\+[-+]+\+$/', trim( $line ) ) ) {
				continue;
			}
			if ( false !== strpos( $line, "\t" ) ) {
				$cells = explode( "\t", $line );
			} elseif ( '|' === substr( trim( $line ), 0, 1 ) ) {
				$cells = explode( '|', trim( trim( $line ), '|' ) );
			} else {
				$cells = [ $line ];
			}
			$cells = array_map( 'trim', $cells );
			if ( null === $headers ) {
				$headers = $cells;
				continue;
			}
			if ( count( $cells ) !== count( $headers ) ) {
				throw new Exception( "Table row does not match header.\nHeader: " . implode( ' | ', $headers ) . "\nRow: $line" );
			}
			$rows[] = array_combine( $headers, $cells );
		}

		return $rows;
	}

	private function tableRowMatches( array $actual, array $expected )
	{
		foreach ( $expected as $column => $value ) {
			if ( ! array_key_exists( $column, $actual ) ) {
				throw new Exception( "Column '$column' not found in table. Columns: " . implode( ', ', array_keys( $actual ) ) );
			}
			if ( $this->replaceVariables( $value ) !== $actual[ $column ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @Then STDOUT should be a table containing rows:
	 */
	public function stdoutShouldBeATableContainingRows( TableNode $table )
	{
		$rows = self::parseTable( $this->lastCommand()->stdout );
		foreach ( $table->getHash() as $expected ) {
			$found = false;
			foreach ( $rows as $row ) {
				if ( $this->tableRowMatches( $row, $expected ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				throw new Exception( 'No table row matches ' . json_encode( $expected ) . "\n" . self::describeResult( $this->lastCommand() ) );
			}
		}
	}

	/**
	 * @Then STDOUT should be a table not containing rows:
	 */
	public function stdoutShouldBeATableNotContainingRows( TableNode $table )
	{
		$rows = self::parseTable( $this->lastCommand()->stdout );
		foreach ( $table->getHash() as $expected ) {
			foreach ( $rows as $row ) {
				if ( $this->tableRowMatches( $row, $expected ) ) {
					throw new Exception( 'Unexpected table row matches ' . json_encode( $expected ) . "\n" . self::describeResult( $this->lastCommand() ) );
				}
			}
		}
	}

	/**
	 * @Then STDOUT should be a table with :count row(s)
	 */
	public function stdoutShouldBeATableWithRows( $count )
	{
		$rows = self::parseTable( $this->lastCommand()->stdout );
		if ( (int) $count !== count( $rows ) ) {
			throw new Exception( "Expected $count table rows, found " . count( $rows ) . ".\n" . self::describeResult( $this->lastCommand() ) );
		}
	}

	/* ---------- Polling ---------- */

	/**
	 * @When I wait up to :seconds seconds until the command :command succeeds
	 */
	public function iWaitUntilTheCommandSucceeds( $seconds, $command )
	{
		$command  = $this->replaceVariables( $command );
		$deadline = time() + (int) $seconds;
		do {
			$result = self::shell( $command );
			if ( 0 === $result->return_code ) {
				return;
			}
			sleep( 2 );
		} while ( time() < $deadline );

		throw new Exception( "Command did not succeed within $seconds seconds.\n" . self::describeResult( $result ) );
	}

	/* ---------- Files ---------- */

	/**
	 * @Then the file :path should exist
	 */
	public function theFileShouldExist( $path )
	{
		$path = $this->replaceVariables( $path );
		if ( ! file_exists( $path ) ) {
			throw new Exception( "File $path does not exist." );
		}
	}

	/**
	 * @Then the file :path should not exist
	 */
	public function theFileShouldNotExist( $path )
	{
		$path = $this->replaceVariables( $path );
		if ( file_exists( $path ) ) {
			throw new Exception( "File $path exists." );
		}
	}

	/**
	 * @Then the file :path should contain :text
	 */
	public function theFileShouldContain( $path, $text )
	{
		$path = $this->replaceVariables( $path );
		$text = $this->replaceVariables( $text );
		$this->theFileShouldExist( $path );
		$content = file_get_contents( $path );
		if ( false === strpos( $content, $text ) ) {
			throw new Exception( "File $path does not contain '$text'. Content:\n$content" );
		}
	}

	/**
	 * @Then the file :path should not contain :text
	 */
	public function theFileShouldNotContain( $path, $text )
	{
		$path    = $this->replaceVariables( $path );
		$text    = $this->replaceVariables( $text );
		$content = file_exists( $path ) ? file_get_contents( $path ) : '';
		if ( false !== strpos( $content, $text ) ) {
			throw new Exception( "File $path unexpectedly contains '$text'. Content:\n$content" );
		}
	}

	/**
	 * @Given the file :path does not exist
	 */
	public function theFileDoesNotExist( $path )
	{
		$path = $this->replaceVariables( $path );
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
	}

	/* ---------- Docker ---------- */

	private static function inspect( $container, $format )
	{
		$result = self::shell( 'docker inspect -f ' . escapeshellarg( $format ) . ' ' . escapeshellarg( $container ) );

		return 0 === $result->return_code ? trim( $result->stdout ) : null;
	}

	private static function containerLogs( $container, $lines = 30 )
	{
		return self::shell( "docker logs --tail $lines " . escapeshellarg( $container ) . ' 2>&1' )->stdout;
	}

	/**
	 * @Then the docker container :name should be running
	 */
	public function theDockerContainerShouldBeRunning( $name )
	{
		$name  = $this->replaceVariables( $name );
		$state = self::inspect( $name, '{{.State.Status}} restarting={{.State.Restarting}}' );
		if ( null === $state ) {
			throw new Exception( "Container $name does not exist." );
		}
		if ( 'running restarting=false' !== $state ) {
			throw new Exception( "Container $name is not running (state: $state). Recent logs:\n" . self::containerLogs( $name ) );
		}
	}

	/**
	 * @Then the docker container :name should not exist
	 */
	public function theDockerContainerShouldNotExist( $name )
	{
		$name = $this->replaceVariables( $name );
		if ( null !== self::inspect( $name, '{{.Id}}' ) ) {
			throw new Exception( "Container $name exists." );
		}
	}

	/**
	 * Fails if the container stops, restarts or its restart count grows within the window (catches restart loops).
	 *
	 * @Then the docker container :name should stay up for :seconds seconds
	 */
	public function theDockerContainerShouldStayUp( $name, $seconds )
	{
		$name = $this->replaceVariables( $name );
		$this->theDockerContainerShouldBeRunning( $name );
		$initial  = self::inspect( $name, '{{.RestartCount}} {{.State.StartedAt}}' );
		$deadline = time() + (int) $seconds;
		while ( time() < $deadline ) {
			sleep( 2 );
			$state = self::inspect( $name, '{{.State.Status}} restarting={{.State.Restarting}}' );
			$now   = self::inspect( $name, '{{.RestartCount}} {{.State.StartedAt}}' );
			if ( 'running restarting=false' !== $state || $now !== $initial ) {
				throw new Exception( "Container $name did not stay up (state: $state, restarts/started before: $initial, now: $now). Recent logs:\n" . self::containerLogs( $name ) );
			}
		}
	}

	/**
	 * @When I note the start time of the docker container :name as :var
	 */
	public function iNoteTheStartTimeOfTheDockerContainer( $name, $var )
	{
		$name  = $this->replaceVariables( $name );
		$start = self::inspect( $name, '{{.State.StartedAt}}' );
		if ( null === $start ) {
			throw new Exception( "Container $name does not exist." );
		}
		$this->variables[ $var ] = $start;
	}

	/**
	 * @Then the docker container :name should have been restarted since :start
	 */
	public function theDockerContainerShouldHaveBeenRestartedSince( $name, $start )
	{
		$name  = $this->replaceVariables( $name );
		$start = $this->replaceVariables( $start );
		$now   = self::inspect( $name, '{{.State.StartedAt}}' );
		if ( null === $now ) {
			throw new Exception( "Container $name does not exist." );
		}
		if ( $now === $start ) {
			throw new Exception( "Container $name was not restarted (still started at $start)." );
		}
	}

	private static function logsSinceStart( $name )
	{
		$since = self::inspect( $name, '{{.State.StartedAt}}' );

		return null === $since ? '' : self::shell( 'docker logs --since ' . escapeshellarg( $since ) . ' ' . escapeshellarg( $name ) . ' 2>&1' )->stdout;
	}

	private function waitForLogs( $name, $text, $seconds )
	{
		$name     = $this->replaceVariables( $name );
		$text     = $this->replaceVariables( $text );
		$deadline = time() + (int) $seconds;
		do {
			$logs = self::logsSinceStart( $name );
			if ( false !== strpos( $logs, $text ) ) {
				return;
			}
			sleep( 2 );
		} while ( time() < $deadline );

		throw new Exception( "Logs of $name do not contain '$text' within $seconds seconds. Logs since start:\n$logs" );
	}

	/**
	 * Only looks at log lines written since the container's current start.
	 *
	 * @Then the logs of the docker container :name should contain :text within :seconds seconds
	 */
	public function theLogsOfTheDockerContainerShouldContain( $name, $text, $seconds )
	{
		$this->waitForLogs( $name, $text, $seconds );
	}

	/**
	 * @Then the logs of the docker container :name should contain within :seconds seconds:
	 */
	public function theLogsOfTheDockerContainerShouldContainBlock( $name, $seconds, PyStringNode $text )
	{
		$this->waitForLogs( $name, $text->getRaw(), $seconds );
	}

	/**
	 * @Then the logs of the docker container :name should not contain:
	 */
	public function theLogsOfTheDockerContainerShouldNotContainBlock( $name, PyStringNode $text )
	{
		$name = $this->replaceVariables( $name );
		$text = $this->replaceVariables( $text->getRaw() );
		$logs = self::logsSinceStart( $name );
		if ( false !== strpos( $logs, $text ) ) {
			throw new Exception( "Logs of $name unexpectedly contain '$text'. Logs since start:\n$logs" );
		}
	}

	/**
	 * @When I stop the docker container :name
	 */
	public function iStopTheDockerContainer( $name )
	{
		$name   = $this->replaceVariables( $name );
		$result = self::shell( 'docker stop ' . escapeshellarg( $name ) );
		if ( 0 !== $result->return_code ) {
			throw new Exception( "Unable to stop $name.\n" . self::describeResult( $result ) );
		}
	}

	/**
	 * @When I remove the docker container :name
	 */
	public function iRemoveTheDockerContainer( $name )
	{
		$name   = $this->replaceVariables( $name );
		if ( self::CRON_CONTAINER === $name ) {
			self::$cron_container_removed = true;
		}
		$result = self::shell( 'docker rm -f ' . escapeshellarg( $name ) );
		if ( 0 !== $result->return_code ) {
			throw new Exception( "Unable to remove $name.\n" . self::describeResult( $result ) );
		}
	}

	/* ---------- Sites ---------- */

	private static function siteRow( $site )
	{
		$rows = self::query( 'SELECT site_url, site_type, site_enabled, site_fs_path FROM sites WHERE site_url = ?', [ $site ] );

		return $rows ? $rows[0] : null;
	}

	/**
	 * Creates the site only when missing, so features can share it across scenarios.
	 *
	 * @Given a :type site :site exists
	 */
	public function aSiteExists( $type, $site )
	{
		$site = $this->replaceVariables( $site );
		$row  = self::siteRow( $site );
		if ( $row ) {
			if ( $type !== $row['site_type'] ) {
				throw new Exception( "Site $site exists with type {$row['site_type']}, expected $type." );
			}

			return;
		}
		$result = self::shell( 'bin/ee site create ' . escapeshellarg( $site ) . ' --type=' . escapeshellarg( $type ) );
		if ( 0 !== $result->return_code || ! self::siteRow( $site ) ) {
			throw new Exception( "Unable to create site $site.\n" . self::describeResult( $result ) );
		}
	}

	/**
	 * @Given the site :site is disabled
	 */
	public function theSiteIsDisabled( $site )
	{
		$site = $this->replaceVariables( $site );
		$row  = self::siteRow( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}
		if ( $row['site_enabled'] ) {
			$result = self::shell( 'bin/ee site disable ' . escapeshellarg( $site ) );
			if ( 0 !== $result->return_code ) {
				throw new Exception( "Unable to disable $site.\n" . self::describeResult( $result ) );
			}
		}
	}

	/**
	 * @Given the site :site is enabled
	 */
	public function theSiteIsEnabled( $site )
	{
		$site = $this->replaceVariables( $site );
		$row  = self::siteRow( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}
		if ( ! $row['site_enabled'] ) {
			$result = self::shell( 'bin/ee site enable ' . escapeshellarg( $site ) );
			if ( 0 !== $result->return_code ) {
				throw new Exception( "Unable to enable $site.\n" . self::describeResult( $result ) );
			}
		}
	}

	/**
	 * @Then the site :site should exist
	 */
	public function theSiteShouldExist( $site )
	{
		$site = $this->replaceVariables( $site );
		if ( ! self::siteRow( $site ) ) {
			throw new Exception( "Site $site does not exist in the sites table." );
		}
	}

	/**
	 * @Then the site :site should not exist
	 */
	public function theSiteShouldNotExist( $site )
	{
		$site = $this->replaceVariables( $site );
		if ( self::siteRow( $site ) ) {
			throw new Exception( "Site $site still exists in the sites table." );
		}
	}

	/**
	 * Resolves the php container name the same way cron-command does (compose v1 and v2 name differently).
	 */
	private static function sitePhpContainer( $site )
	{
		$row = self::siteRow( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}
		$result = self::shell( 'cd ' . escapeshellarg( $row['site_fs_path'] ) . " && docker-compose ps --format '{{.Name}}' php" );

		return trim( $result->stdout );
	}

	/* ---------- Cron jobs (sqlite `cron` table) ---------- */

	private static function cronRow( $id )
	{
		$rows = self::query( 'SELECT * FROM cron WHERE id = ?', [ $id ] );

		return $rows ? $rows[0] : null;
	}

	/**
	 * @When I store the id of the latest cron job for :site as :var
	 */
	public function iStoreTheIdOfTheLatestCronJobFor( $site, $var )
	{
		$this->iStoreTheIdOfTheLatestCronJobWithCommandContaining( $site, '', $var );
	}

	/**
	 * @When I store the id of the latest cron job for :site with command containing :text as :var
	 */
	public function iStoreTheIdOfTheLatestCronJobWithCommandContaining( $site, $text, $var )
	{
		$site = $this->replaceVariables( $site );
		$text = $this->replaceVariables( $text );
		$rows = self::query( "SELECT id FROM cron WHERE site_url = ? AND instr(command, ?) > 0 ORDER BY id DESC LIMIT 1", [ $site, $text ] );
		if ( ! $rows ) {
			throw new Exception( "No cron job for $site with command containing '$text'. Cron table:\n" . json_encode( self::query( 'SELECT * FROM cron' ), JSON_PRETTY_PRINT ) );
		}
		$this->variables[ $var ] = $rows[0]['id'];
	}

	/**
	 * @Then the cron job :id should exist
	 */
	public function theCronJobShouldExist( $id )
	{
		$id = $this->replaceVariables( $id );
		if ( ! self::cronRow( $id ) ) {
			throw new Exception( "Cron job $id does not exist." );
		}
	}

	/**
	 * @Then the cron job :id should not exist
	 */
	public function theCronJobShouldNotExist( $id )
	{
		$id  = $this->replaceVariables( $id );
		$row = self::cronRow( $id );
		if ( $row ) {
			throw new Exception( "Cron job $id still exists: " . json_encode( $row ) );
		}
	}

	/**
	 * @Then the cron job :id should have :field :value
	 */
	public function theCronJobShouldHave( $id, $field, $value )
	{
		$id    = $this->replaceVariables( $id );
		$value = $this->replaceVariables( $value );
		$row   = self::cronRow( $id );
		if ( ! $row ) {
			throw new Exception( "Cron job $id does not exist." );
		}
		if ( ! array_key_exists( $field, $row ) ) {
			throw new Exception( "Unknown cron column '$field'." );
		}
		if ( (string) $row[ $field ] !== $value ) {
			throw new Exception( "Cron job $id has $field '" . $row[ $field ] . "', expected '$value'. Row: " . json_encode( $row ) );
		}
	}

	/**
	 * @Then the cron job :id should have :field matching :regex
	 */
	public function theCronJobShouldHaveMatching( $id, $field, $regex )
	{
		$id  = $this->replaceVariables( $id );
		$row = self::cronRow( $id );
		if ( ! $row ) {
			throw new Exception( "Cron job $id does not exist." );
		}
		if ( ! array_key_exists( $field, $row ) ) {
			throw new Exception( "Unknown cron column '$field'." );
		}
		if ( ! preg_match( $regex, (string) $row[ $field ] ) ) {
			throw new Exception( "Cron job $id has $field '" . $row[ $field ] . "', which does not match $regex." );
		}
	}

	/**
	 * @Then the cron job :id should have an empty :field
	 */
	public function theCronJobShouldHaveAnEmpty( $id, $field )
	{
		$id  = $this->replaceVariables( $id );
		$row = self::cronRow( $id );
		if ( ! $row ) {
			throw new Exception( "Cron job $id does not exist." );
		}
		if ( ! array_key_exists( $field, $row ) ) {
			throw new Exception( "Unknown cron column '$field'." );
		}
		if ( null !== $row[ $field ] && '' !== $row[ $field ] ) {
			throw new Exception( "Cron job $id has $field '" . $row[ $field ] . "', expected none." );
		}
	}

	/**
	 * @Then there should be :count cron job(s) for :site
	 */
	public function thereShouldBeCronJobsFor( $count, $site )
	{
		$site = $this->replaceVariables( $site );
		$rows = self::query( 'SELECT * FROM cron WHERE site_url = ?', [ $site ] );
		if ( (int) $count !== count( $rows ) ) {
			throw new Exception( "Expected $count cron jobs for $site, found " . count( $rows ) . ': ' . json_encode( $rows, JSON_PRETTY_PRINT ) );
		}
	}

	/**
	 * @Then there should be :count cron job(s) for :site with command containing :text
	 */
	public function thereShouldBeCronJobsForWithCommandContaining( $count, $site, $text )
	{
		$site = $this->replaceVariables( $site );
		$text = $this->replaceVariables( $text );
		$rows = self::query( 'SELECT * FROM cron WHERE site_url = ? AND instr(command, ?) > 0', [ $site, $text ] );
		if ( (int) $count !== count( $rows ) ) {
			throw new Exception( "Expected $count cron jobs for $site with command containing '$text', found " . count( $rows ) . ': ' . json_encode( $rows, JSON_PRETTY_PRINT ) );
		}
	}

	/**
	 * Rows this suite owns: its sites' jobs (even for sites already deleted) and host jobs carrying the prefix.
	 */
	private static function ownedCronRows()
	{
		return self::query(
			"SELECT * FROM cron WHERE substr(site_url, 1, ?) = ? OR (site_url = 'host' AND instr(command, ?) > 0)",
			[ strlen( self::TEST_PREFIX ), self::TEST_PREFIX, self::TEST_PREFIX ]
		);
	}

	/**
	 * Guard for scenarios that need an empty cron table; they never touch jobs they don't own.
	 *
	 * @Given there are no cron jobs other than the ones owned by this suite
	 */
	public function thereAreNoForeignCronJobs()
	{
		$owned   = array_column( self::ownedCronRows(), 'id' );
		$foreign = array_filter(
			self::query( 'SELECT * FROM cron' ),
			function ( $row ) use ( $owned ) {
				return ! in_array( $row['id'], $owned );
			}
		);
		if ( $foreign ) {
			throw new Exception( "Refusing to run: cron jobs not owned by this suite exist:\n" . json_encode( array_values( $foreign ), JSON_PRETTY_PRINT ) );
		}
	}

	/**
	 * @When I delete every cron job owned by this suite
	 */
	public function iDeleteEveryCronJobOwnedByThisSuite()
	{
		self::deleteOwnedCronJobs();
		$left = self::ownedCronRows();
		if ( $left ) {
			throw new Exception( "Owned cron jobs remain after deletion:\n" . json_encode( $left, JSON_PRETTY_PRINT ) );
		}
	}

	/**
	 * Drops all but one owned row in SQL, then deletes the last through `ee cron delete`, so the config is regenerated and the shared scheduler restarted once instead of once per job.
	 */
	private static function deleteOwnedCronJobs()
	{
		$ids = array_column( self::ownedCronRows(), 'id' );
		if ( ! $ids ) {
			return;
		}
		$last = array_pop( $ids );
		foreach ( $ids as $id ) {
			self::query( 'DELETE FROM cron WHERE id = ?', [ $id ] );
		}
		self::shell( 'bin/ee cron delete ' . escapeshellarg( $last ) );
	}

	/* ---------- Cron config (ofelia config.ini) ---------- */

	/**
	 * @return array List of [ 'type' => job-exec|job-local, 'name' => ..., 'keys' => [ key => value ] ].
	 */
	private static function cronConfigJobs()
	{
		$path = self::cronConfigPath();
		if ( ! file_exists( $path ) ) {
			throw new Exception( "Cron config $path does not exist." );
		}
		$jobs    = [];
		$current = null;
		foreach ( file( $path, FILE_IGNORE_NEW_LINES ) as $line ) {
			if ( preg_match( '/^\[(job-[a-z-]+) "(.*)"\]\s*$/', $line, $m ) ) {
				$jobs[]  = [ 'type' => $m[1], 'name' => $m[2], 'keys' => [] ];
				$current = count( $jobs ) - 1;
			} elseif ( null !== $current && preg_match( '/^([a-z-]+)\s*=\s*(.*)$/', $line, $m ) ) {
				$jobs[ $current ]['keys'][ $m[1] ] = $m[2];
			}
		}

		return $jobs;
	}

	/**
	 * Job section names are "<site_url>-<sanitised command>-<random>", so the site prefix identifies the owner.
	 */
	private function findCronConfigJobs( $site, $text )
	{
		$site = $this->replaceVariables( $site );
		$text = $this->replaceVariables( $text );

		return array_values(
			array_filter(
				self::cronConfigJobs(),
				function ( $job ) use ( $site, $text ) {
					$command = isset( $job['keys']['command'] ) ? $job['keys']['command'] : '';

					return 0 === strpos( $job['name'], $site . '-' ) && ( '' === $text || false !== strpos( $command, $text ) );
				}
			)
		);
	}

	private function oneCronConfigJob( $site, $text )
	{
		$jobs = $this->findCronConfigJobs( $site, $text );
		if ( 1 !== count( $jobs ) ) {
			throw new Exception( 'Expected exactly one cron config job for ' . $this->replaceVariables( $site ) . " with command containing '" . $this->replaceVariables( $text ) . "', found " . count( $jobs ) . ". Config:\n" . file_get_contents( self::cronConfigPath() ) );
		}

		return $jobs[0];
	}

	/**
	 * @Then the cron config should have a :type job for :site with command containing :text
	 */
	public function theCronConfigShouldHaveAJob( $type, $site, $text )
	{
		$job = $this->oneCronConfigJob( $site, $text );
		if ( $type !== $job['type'] ) {
			throw new Exception( "Cron config job {$job['name']} is {$job['type']}, expected $type." );
		}
	}

	/**
	 * @Then the cron config should not have a job for :site with command containing :text
	 */
	public function theCronConfigShouldNotHaveAJob( $site, $text )
	{
		if ( $this->findCronConfigJobs( $site, $text ) ) {
			throw new Exception( "Cron config still has a job for $site with command containing '$text'. Config:\n" . file_get_contents( self::cronConfigPath() ) );
		}
	}

	/**
	 * @Then the cron config should not have any job for :site
	 */
	public function theCronConfigShouldNotHaveAnyJobFor( $site )
	{
		$this->theCronConfigShouldNotHaveAJob( $site, '' );
	}

	/**
	 * @Then the cron config job for :site with command containing :text should have :key :value
	 */
	public function theCronConfigJobShouldHave( $site, $text, $key, $value )
	{
		$job   = $this->oneCronConfigJob( $site, $text );
		$value = $this->replaceVariables( $value );
		if ( ! isset( $job['keys'][ $key ] ) || $value !== $job['keys'][ $key ] ) {
			throw new Exception( "Cron config job {$job['name']} has $key '" . ( isset( $job['keys'][ $key ] ) ? $job['keys'][ $key ] : '(unset)' ) . "', expected '$value'." );
		}
	}

	/**
	 * @Then the cron config job for :site with command containing :text should not have :key
	 */
	public function theCronConfigJobShouldNotHave( $site, $text, $key )
	{
		$job = $this->oneCronConfigJob( $site, $text );
		if ( isset( $job['keys'][ $key ] ) ) {
			throw new Exception( "Cron config job {$job['name']} unexpectedly has $key = {$job['keys'][ $key ]}." );
		}
	}

	/**
	 * @Then no :type job in the cron config should have :key
	 */
	public function noJobInTheCronConfigShouldHave( $type, $key )
	{
		foreach ( self::cronConfigJobs() as $job ) {
			if ( $type === $job['type'] && isset( $job['keys'][ $key ] ) ) {
				throw new Exception( "Cron config $type job {$job['name']} has $key = {$job['keys'][ $key ]}. Config:\n" . file_get_contents( self::cronConfigPath() ) );
			}
		}
	}

	/**
	 * @Then the cron config job for :site with command containing :text should target the php container of :target
	 */
	public function theCronConfigJobShouldTargetThePhpContainerOf( $site, $text, $target )
	{
		$container = self::sitePhpContainer( $this->replaceVariables( $target ) );
		if ( '' === $container ) {
			throw new Exception( "Site $target has no running php container." );
		}
		$this->theCronConfigJobShouldHave( $site, $text, 'container', $container );
	}

	/* ---------- Hooks ---------- */

	/**
	 * @BeforeFeature
	 */
	public static function rememberGlobalState( BeforeFeatureScope $scope )
	{
		self::$cron_container_existed = null !== self::inspect( self::CRON_CONTAINER, '{{.Id}}' );
		self::$cron_container_removed = false;
	}

	/**
	 * Brings back a scheduler that a scenario stopped or removed (e.g. with `docker rm -f`), if it existed before the feature or a step removed it.
	 */
	private static function restoreScheduler()
	{
		if ( ! self::$cron_container_existed && ! self::$cron_container_removed ) {
			return;
		}
		$running = self::inspect( self::CRON_CONTAINER, '{{.State.Running}}' );
		if ( null === $running ) {
			self::shell( 'cd ' . escapeshellarg( EE_ROOT_DIR . '/services' ) . ' && docker-compose -f docker-compose.yml up -d global-cron' );
		} elseif ( 'true' !== $running ) {
			self::shell( 'docker start ' . escapeshellarg( self::CRON_CONTAINER ) );
		}
	}

	/**
	 * Jobs firing every few seconds (or every second, if update drops the seconds field) must not outlive a scenario that failed before its own delete step.
	 *
	 * @AfterScenario
	 */
	public function deleteFastOwnedJobs( AfterScenarioScope $scope )
	{
		$fast = array_filter(
			self::ownedCronRows(),
			function ( $row ) {
				return preg_match( '/^@every\s+\d+s$/', $row['schedule'] ) || '* * * * *' === trim( $row['schedule'] );
			}
		);
		foreach ( array_column( $fast, 'id' ) as $id ) {
			self::shell( 'bin/ee cron delete ' . escapeshellarg( $id ) );
		}
	}

	/**
	 * A failed @destructive scenario skips its own restore steps, so the scheduler could stay stopped, or crash-loop on an owned host job with a user (ofelia rejects `user` in a job-local) and stop every site's cron.
	 *
	 * @AfterScenario @destructive
	 */
	public function restoreSchedulerAfterDestructiveScenario( AfterScenarioScope $scope )
	{
		$breaking = self::query(
			"SELECT id FROM cron WHERE site_url = 'host' AND instr(command, ?) > 0 AND user IS NOT NULL AND user != ''",
			[ self::TEST_PREFIX ]
		);
		foreach ( array_column( $breaking, 'id' ) as $id ) {
			self::shell( 'bin/ee cron delete ' . escapeshellarg( $id ) );
		}
		self::restoreScheduler();
		// A stopped scheduler is always this scenario's doing, even when it was created during the feature.
		if ( 'false' === self::inspect( self::CRON_CONTAINER, '{{.State.Running}}' ) ) {
			self::shell( 'docker start ' . escapeshellarg( self::CRON_CONTAINER ) );
		}
	}

	/**
	 * Deletes only what this suite owns (TEST_PREFIX), never every site.
	 *
	 * @AfterFeature
	 */
	public static function cleanup( AfterFeatureScope $scope )
	{
		// Jobs first (host and orphaned ones would otherwise survive site deletion).
		self::deleteOwnedCronJobs();

		$sites = self::query( 'SELECT site_url FROM sites WHERE substr(site_url, 1, ?) = ?', [ strlen( self::TEST_PREFIX ), self::TEST_PREFIX ] );
		foreach ( array_column( $sites, 'site_url' ) as $site ) {
			self::shell( 'bin/ee site delete ' . escapeshellarg( $site ) . ' --yes' );
		}

		foreach ( glob( '/tmp/' . self::TEST_PREFIX . '*' ) ?: [] as $marker ) {
			@unlink( $marker );
		}

		// `ee cron delete` removed the scheduler when the table emptied, before v4.13.1.
		self::restoreScheduler();
	}
}
