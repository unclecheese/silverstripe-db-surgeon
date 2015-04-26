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
	 * If true, output for each record even if not changed.
	 */
	protected $verbose = false;

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
	 * @param  int $level The level of the hierarchy
	 */
	protected function traverse($id, $level = 0) {
		$baseClass = $this->baseClass;
		$children = $baseClass::get()->fromSource()->filter(array(
			'ParentID' => $id
		))->toArray();
		
		foreach($children as $sourceRecord) {
			$tab = str_pad("\t", $level);
			$targetRecord = DataList::create($sourceRecord->ClassName)
								->fromTarget()
								->byID($sourceRecord->ID);
		
			$msg = $this->processUpdate($sourceRecord, $targetRecord, $level);			
			if($msg) {
				$this->task->writeln($tab.$msg);
			}
			else if($this->verbose) {
				$this->task->writeln($tab.$sourceRecord->Title);
			}
			
			$children = $baseClass::get()->fromSource()->filter(array(
				'ParentID' => $sourceRecord->ID
			))->toArray();

			if(!empty($children)) {
				$level++;
				$this->traverse($sourceRecord->ID, $level);
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
				$this->task->writeln("{$targetRecord->ClassName} \"{$targetRecord->getTitle()}\" " . SS_Cli::text('[DELETED]','red',null, true));
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
		$baseClass = $this->baseClass;
		$sourceRecords = $baseClass::get()->fromSource()->toArray();		
		$targetRecords = $baseClass::get()->fromTarget()->toArray();
			
		foreach($sourceRecords as $sourceRecord) {
			$storedRecord = $this->task->retrieve($sourceRecord);
			if(!$storedRecord) {				
				continue;
			}

			$hasOneMsg = $this->relateHasOne($storedRecord);				
			$mmMsg = $this->relateManyMany($storedRecord);

			if($hasOneMsg) {
				$this->task->writeln("\t{$hasOneMsg}");
			}
			if($mmMsg) {
				$this->task->writeln("\t{$mmMsg}");
			}
		}					
	}

	/**
	 * Runs the "create" phase of the migration, in which records are created on the target
	 * @param  DataObject $record 	 
	 */
	public function handleCreate(DataObject $record) {
		Versioned::reading_stage('Stage');
		$this->task->log("Source {$record->ClassName} record {$record->getTitle()} does not exist on target. Creating.");

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
		$this->task->log("Source {$record->ClassName} \"{$record->getTitle()}\" stored on target as $newID with parent id {$record->__TargetParentID}");
		$this->task->added++;

		return $record->Title . " " . SS_Cli::text('[CREATED]','green', null, true);
	}	
}