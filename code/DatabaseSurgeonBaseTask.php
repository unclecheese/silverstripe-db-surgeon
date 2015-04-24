<?php

/**
 * An "abstract" task that provides utility to all of its descendants
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonBaseTask extends CliController {

	/**
	 * The name of the file that will be store in the assets/ directory
	 * containing the timstamp of the migration
	 *
	 * @var  string
	 */
	const MIGRATION_FILENAME = '.migration';

	/**
	 * The number of records that have been added
	 * @var integer
	 */
	public $added = 0;

	/**
	 * The number of records that have been updated.
	 * @var integer
	 */
	public $updated = 0;

	/**
	 * The number of records that have been deleted
	 * @var integer
	 */
	public $deleted = 0;

	/**
	 * The number of conflicts
	 * @var integer
	 */
	public $conflicts = 0;

	/**
	 * The store of all records that have been written to the target database.
	 * The source of truth for all ID questions.
	 * 
	 * @var array
	 */
	protected $_store = array ();

	/**
	 * Stores a record in the store. Indexed by a sku.
	 * @param  DataObject $record 	 
	 */
	public function store(DataObject $record) {
		$sku = $this->createSku($record);
		$this->_store[$this->createSku($record)] = $record;
	}

	/**
	 * Gets a record from the store
	 * @param  string $class The name of the class to get
	 * @param  int $id    The ID of the record
	 * @return DataObject
	 */
	public function retrieve($class, $id = null) {
		$sku = $this->createSku($class, $id); 

		if(isset($this->_store[$sku])) {
			return $this->_store[$sku];		
		}

		return false;
	}

	/**
	 * Creates a sku for a given record, using its ClassName and ID
	 * @param  string $class 
	 * @param  int $id    
	 * @return string
	 */
	protected function createSku($class, $id = null) {
		return ($class instanceof DataObject) ? 
				ClassInfo::baseDataClass($class->ClassName)."__{$class->ID}" : 
				ClassInfo::baseDataClass($class)."__{$id}";		
	}

	/**
	 * Sanity check for a good configuration
	 * 	
	 * @return boolean
	 */
	protected function checkConfiguration() {
		global $sourceDatabaseConfig;

		$good = true;
		foreach($sourceDatabaseConfig as $k => $v) {
			if(!$v) {
				$this->error("Target database variable $k is not defined.");				
				$good = false;
			}
		}

		return $good;
	}

	/**
	 * Gets a path to where the bookmark file will be written.
	 * @return string
	 */
	protected function getBookmarkPath() {
		return Controller::join_links(BASE_PATH, ASSETS_DIR, self::MIGRATION_FILENAME);
	}

	/**
	 * Gets the contents of the bookmark file
	 * 
	 * @return string
	 */
	protected function getBookmark() {
		return @file_get_contents($this->getBookmarkPath());
	}

	/**
	 * Creates a bookmark in the filesystem
	 * @param  string $date The date of the bookmark
	 * @return string
	 */
	protected function createBookmark($date = null) {
		
		if(!$date) {
			$date = SS_Datetime::now()->Rfc2822();
		}

		@file_put_contents($this->getBookmarkPath(), $date);

		if(!file_exists($this->getBookmarkPath())) {
			$this->fail('Could not create bookmark at ' . $this->getBookmarkPath());
		}

		return $date;
	}

	/**
	 * Writes a line of text to the output stream
	 * @param  string $msg The message to write	 
	 */
	public function writeLn($msg = null) {
		if(Director::is_cli()) {
			fwrite(STDOUT, $msg.PHP_EOL);	
		}
		else {
			echo "$msg<br>";
		}
	}

	/**
	 * Writes text to the output stream
	 * @param  string $msg The message to write	 
	 */
	public function write($msg) {
		fwrite(STDOUT, $msg);
	}

	/**
	 * Writes an error to the output stream
	 * @param  string $msg The error message	 
	 */
	public function error($msg) {
		$this->writeLn("[ERROR] . $msg");
	}

	/**
	 * Writes an error and kills the task
	 * @param  string $msg The error message	 
	 */
	public function fail($msg) {
		$this->writeLn("[FAILED] $msg");
		
		die();
	}

	/**
	 * Prompts the user with a question. Acceptible values are y/n.
	 * @param  string $question 
	 * @return boolean
	 */
	public function ask($question) {
		$this->write($question . ' (y/n) ');
		$response = strtolower(trim(fgets(STDIN)));

		if(!in_array($response, array('y','n','yes','no'))) {
			$this->writeLn('Invalid response');
			return $this->ask($question);
		}

		return in_array($response, array('y','yes'));
	}

	/**
	 * Prompts the user for input
	 * @param  string $prompt The question
	 * @return string         The user's response
	 */
	public function prompt($prompt) {
		$this->write($prompt . ': ');
		$response = fgets(STDIN);

		return $response;
	}

	/**
	 * Gets an argunment from the command line
	 * @param  string $arg The name of the arg to get
	 * @return string      The value of the arg
	 */
	protected function getArg($arg) {
		if(isset($_REQUEST['args']) && is_array($_REQUEST['args'])) {
			foreach($_REQUEST['args'] as $a) {
				if($a == $arg) {
					return true;
				}
			}
		}

		return false;
	}
}