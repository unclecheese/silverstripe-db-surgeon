<?php
/**
 * A hierarchical migration task for assets (uploaded files)
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonAssetsMigration extends DatabaseSurgeonHierarchicalMigration {

	/**
	 * The base class of hierarchy to traverse
	 * @var string
	 */
	protected $baseClass = 'File';	

	/**
	 * Constrcutor. Check if we have a valid URL for downloading files (HTTP)
	 * 
	 * @param DatabaseSurgeonTask     $task     
	 * @param DatabaseSurgeonBookmark $bookmark 
	 */
	public function __construct(DatabaseSurgeonTask $task, DatabaseSurgeonBookmark $bookmark) {
		parent::__construct($task, $bookmark);

		if(!defined('SS_SOURCE_URL')) {
			$good = false;
			$this->task->fail('Source URL is not provided. This is required for downloading assets to the target site');
		}
	}

	/**
	 * Get all the has_one relations that should be ignored
	 * @return array
	 */
	protected function getHasOneExclusions() {
		return array (
			'Parent'
		);
	}

	/**
	 * Called when the migration should be deleting records
	 * 
	 * @param  array $remoteIDs  A list of IDs on the target DB that should be deleted	 
	 */
	protected function handleDeletion($remoteIDs) {
		if(!empty($remoteIDs)) {			
			DataList::create($this->baseClass)->fromTarget()->byIDs($remoteIDs)->removeAll();
		}						
	}

	/**
	 * Called when the migration should be creating a record on the target DB
	 * 
	 * @param  DataObject $record The record to be created	 
	 */
	public function handleCreate(DataObject $record) {		
		if($record instanceof Folder) {
			$folderName = preg_replace('/^'.ASSETS_DIR.'\//', '', $record->Filename);
			$new = Folder::find_or_make($folderName);
			$record->__TargetID = $new->ID;
			return parent::handleCreate($record);
		}
				
		$url = Controller::join_links(SS_SOURCE_URL, $record->Filename);
		$this->task->log("Downloading " . $record->__TargetParentID  . $url);
		$contents = @file_get_contents($url);
		if(!$contents) {
			return $this->task->error('Error downloading file ' . $url);
		}
		$this->task->log("Writing to " . $record->getFullPath());
		@file_put_contents($record->getFullPath(), $contents);
		
		if(!file_exists($record->getFullPath())) {
			$this->task->error("\tFailed to write file " . $record->getFullPath());
		}
	
		return parent::handleCreate($record);
	}		
}