<?php

/**
 * An "abstract" class that handles migrations for DataObjects that use Hierarchy,
 * e.g. SiteTree, File
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonHierarchicalMigration extends DatabaseSurgeonBaseMigration {

	/**
	 * The base class that will be traversed in the hierarchy
	 * @var string
	 */
	protected $baseClass;

	/**
	 * Constructor. Assigns the task and bookmark,
	 * @param DatabaseSurgeonTask     $task     
	 * @param DatabaseSurgeonBookmark $bookmark 
	 */
	public function __construct(DatabaseSurgeonTask $task, DatabaseSurgeonBookmark $bookmark) {
		parent::__construct($task, $bookmark);

		if(!$this->baseClass || !class_exists($this->baseClass)) {
			$this->task->fail($this->class . ' does not have a valid $baseClass defined');
		}
	}

	/**
	 * Recursive function that traverses the tree and runs processUpdate()
	 * for each record
	 * @param  int $id The top level ID	 
	 */
	protected function traverse($id) {
		$baseClass = $this->baseClass;
		$children = $baseClass::get()->fromSource()->filter(array(
			'ParentID' => $id
		))->toArray();
		
		foreach($children as $sourceRecord) {			
			$targetRecord = DataList::create($sourceRecord->ClassName)
								->fromTarget()
								->byID($sourceRecord->ID);
			
			$this->processUpdate($sourceRecord, $targetRecord);			
			
			$children = $baseClass::get()->fromSource()->filter(array(
				'ParentID' => $sourceRecord->ID
			))->toArray();

			if(!empty($children)) {
				$this->traverse($sourceRecord->ID);
			}
		}
	}

	/**
	 * Runs the "update" phase of the migration, in which records are updated or created
	 * on the target	 
	 */
	public function runUpdatePhase() {
		Versioned::reading_stage('Stage');
		$this->traverse(0);
	}

	/**
	 * Runs the "delete" phase of the migration, in which target records are deleted
	 * per their absence from the source database	 
	 */
	public function runDeletePhase() {
		Versioned::reading_stage('Stage');
		$remoteIDs = array ();
		$targetRecords = DataList::create($this->baseClass)->fromTarget()->toArray();

		foreach($targetRecords as $targetRecord) {			
			$sourceRecord = DataList::create($this->baseClass)
								->fromSource()
								->byID($targetRecord->ID);
			if(!$sourceRecord && !$this->isEditedAfterBookmark($targetRecord)) {
				$this->task->writeLn();
				$this->task->writeln("\t\tTarget {$targetRecord->ClassName} \"{$targetRecord->getTitle()}\" is not in the source databse. Deleting.");
				$remoteIDs[] = $targetRecord->ID;
				$this->task->deleted++;
			}			
		}

		$this->handleDeletion($remoteIDs);
	}

	/**
	 * Runs the "relate" phase of the migration, in which recods are assigned
	 * new foreign keys
	 */
	public function runRelatePhase() {
		Versioned::reading_stage('Stage');
		$sourceRecords = SiteTree::get()->fromSource()->toArray();		
		$targetRecords = SiteTree::get()->fromTarget()->toArray();
			
		foreach($sourceRecords as $sourceRecord) {
			$storedRecord = $this->task->retrieve($sourceRecord);
			if(!$storedRecord) {
				continue;
			}

			$this->relateHasOne($storedRecord);			
			$this->relateManyMany($storedRecord);
		}					
	}

	/**
	 * Runs the "create" phase of the migration, in which records are created on the target
	 * @param  DataObject $record 	 
	 */
	public function handleCreate(DataObject $record) {
		Versioned::reading_stage('Stage');
		$this->task->writeLn();
		$this->task->writeLn("\tSource {$record->ClassName} record {$record->getTitle()} does not exist on target. Creating.");

		if($storedParent = $this->task->retrieve($this->baseClass, $record->ParentID)) {
			$record->__TargetParentID = $storedParent->__TargetID;
		}
		
		$record->forceChange();
		if($record->__TargetParentID) {
			$originalParent = $record->ParentID;
			$record->ParentID = $record->__TargetParentID;
			$newID = $record->createOnTarget();
			$record->ParentID = $originalParent;
		}
		else {
			$newID = $record->createOnTarget();
		}
		
		$record->__TargetID = $newID;

		$this->task->store($record);
		$this->task->writeLn("\tSource {$record->ClassName} \"{$record->getTitle()}\" stored on target as $newID with parent id {$record->__TargetParentID}");
		$this->task->added++;
	}	
}