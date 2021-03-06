<?php
/**
 *
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonTask extends DatabaseSurgeonBaseTask {

	/**
	 * The current connection to the database
	 * @var SS_Database
	 */
	private static $currentConn;

	/**
	 * Connects to the target database	 
	 */
	public static function connect_to_target() {
		if(self::$currentConn === 'target') return;

		DB::setConn(DB::getConn('target'));
		self::$currentConn = 'target';
	}

	/**
	 * Connects to the source database	 
	 */
	public static function connect_to_source() {	
		if(self::$currentConn === 'source') return;

		DB::setConn(DB::getConn('source'));	
		self::$currentConn = 'source';
	}

	/**
	 * Initialises the task. Adds extensions, establishes database configs.	 
	 */
	public function init() {
		global $databaseConfig;
		global $sourceDatabaseConfig;
		
		DB::connect($sourceDatabaseConfig, 'source');
		DB::connect($databaseConfig, 'target');

		Config::inst()->update('DataList', 'extensions', array('DatabaseSurgeonDataList'));
		Config::inst()->update('DataObject', 'extensions', array('DatabaseSurgeonDataObject'));

		parent::init();
	}

	/**
	 * The main point of execution. Instantiates all the tasks and runs all their phases.	 
	 */
	public function index() {
		$this->writeLn('Beginning database merge');
		$bookmark = $this->getBookmark();
		if(!$bookmark) {
			$this->writeLn('There is currently no bookmark defined.');
			if($this->ask('Do you want to create one retroactively?')) {
				$date = $this->prompt('Around what date and time did you migrate the database and uploaded assets from this database to ' . SS_SOURCE_DATABASE_NAME . '? (YYYY-MM-DD HH:MM:SS)');
				$result = strtotime($date);
				if($result === -1) {
					$this->fail('Invalid date');
				}

				$this->createBookmark($date);
				$this->writeLn('Bookmark created for ' . $date);
			}
			else {
				$this->fail('Cannot migrate without a bookmark');
			}
		}

		$this->writeLn("\nMigrating data based on bookmark from $bookmark\n");		
		$this->writeHeader('Beginning DataObject migration');

		$dataObjectTask = DatabaseSurgeonDataObjectMigration::create($this, $bookmark);
		$dataObjectTask->runUpdatePhase();
		$dataObjectTask->runDeletePhase();
		$this->writeln();
		$this->writeFooter('DataObject migration complete');
		$this->writeln();


		$this->writeHeader('Beginning Assets migration');
		$assetsTask = DatabaseSurgeonAssetsMigration::create($this, $bookmark);
		$assetsTask->runUpdatePhase();
		$assetsTask->runDeletePhase();
		$this->writeln();
		$this->writeFooter("Assets migration complete");
		$this->writeln();

		$this->writeHeader('Beginning SiteTree migration');
		$siteTreeTask = DatabaseSurgeonSiteTreeMigration::create($this, $bookmark);
		$siteTreeTask->runUpdatePhase();
		$siteTreeTask->runDeletePhase();
		$this->writeln();
		$this->writeFooter("SiteTree migration complete");		
		$this->writeln();

		$this->writeLn("\n\nRelating DataObjects...\n");
		$dataObjectTask->runRelatePhase();
		$this->writeLn("\n\nRelating assets...\n");
		$assetsTask->runRelatePhase();
		$this->writeLn("\n\nRelating SiteTree...\n");
		$siteTreeTask->runRelatePhase();
		 			
	}

	protected function writeHeader($txt) {
		$this->writeln(SS_Cli::text(str_pad('', 50, ' ', STR_PAD_RIGHT), 'white','blue'));
		$this->writeLn(SS_Cli::text(str_pad(" $txt ", 50, ' ', STR_PAD_RIGHT),'white','blue'));
		$this->writeln(SS_Cli::text(str_pad('', 50, ' ', STR_PAD_RIGHT), 'white','blue'));		
	}


	protected function writeFooter($txt) {
		$this->writeln(
			SS_Cli::text(
				str_pad(
					"[[[ $txt ]]]",
					50,
					' ', 
					STR_PAD_RIGHT
				),
			'black','yellow')
		);		
	}
}