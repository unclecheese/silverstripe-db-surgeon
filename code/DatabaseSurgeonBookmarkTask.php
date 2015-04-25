<?php

/**
 * A command-line task that creates a "bookmark" on the filesystem, storing the
 * date and time that the target database was last in sync with the source database.
 *
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonBookmarkTask extends DatabaseSurgeonBaseTask {

	/**
	 * Initialises the task. Sanity check	 
	 */
	public function init() {
		parent::init();
		if(!$this->checkConfiguration()) {
			$this->fail('Your configuration is not set up. Make sure to put all the constants in your _ss_environment.php file');
		}
	}

	/**
	 * Creates the bookmark. Overrides if requested.	 
	 */
	public function index() {
		global $databaseConfig;
		
		$this->writeLn("Creating migration bookmark for current date and time.");
		$existing = $this->getBookmark();

		if($existing) {
			$this->write('There is already a bookmark created for a migration from '. SS_SOURCE_DATABASE_NAME .'.');
			if(!$this->ask('Do you want to create one?')) {
				$this->fail('Bookmark was not created');
			}
		}

		$this->createBookmark();
		$this->writeLn('Bookmark created');
		
	}
}