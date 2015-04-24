<?php
/**
 * An abstract class (in practice) that provides functionality common to all migrations.
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonBaseMigration extends Object {

	/**	 
	 * @var DatabaseSurgeonTask
	 */
	protected $task;

	/**
	 * The bookmark (timestamp reference) for this migration
	 * @var string
	 */
	protected $bookmark;

	/**
	 * Stores all the records that have been written to the target. Used for looking up
	 * their target IDs (and target ParentIDs)
	 * 
	 * @var array
	 */
	protected $store = array ();

	/**
	 * Constructor. Assigns the task and bookmark
	 * @param DatabaseSurgeonTask $task     
	 * @param string             $bookmark
	 */
	public function __construct(DatabaseSurgeonTask $task, $bookmark) {
		$this->task = $task;
		$this->bookmark = $bookmark;
	}

	/**
	 * Gets a list of all the DataObject classes that should be checked in this migration
	 * @return array
	 */
	protected function getQualifyingClasses() {
		return array ();
	}

	/**
	 * "Abstract" method to run the "update" phase of the migration.
	 * During update, objects are created or updated on the target database.
	 */
	public function runUpdatePhase() {	}

	/**
	 * "Abstract" method to run the "delete" phase of the migration.
	 * During delete, objects on the target are deleted, if so stated by the source database.
	 */
	public function runDeletePhase() {  }

	/**
	 * "Abstract" method to run the "relate" phase of the migration.
	 * During relate, objects are related to one another using their target IDs
	 */
	public function runRelatePhase() {  }

	/**
	 * "Abstract" method that andles a bulk deletion of records
	 * @param  array $remoteIDs
	 */
	protected function handleDeletion($remoteIDs) {  }

	/**
	 * Gets a list of has_one relations that should be ignored
	 * 	 
	 * @return  array
	 */
	protected function getHasOneExclusions() { 
		return array ();
	}

	/**
	 * Gets a list of has_many relations that should be ignored
	 * 
	 * @return  array
	 */
	protected function getHasManyExclusions() {
		return array ();
	}

	/**
	 * Gets a list of many_many relations that should be ignored
	 * 
	 * @return [type] [description]
	 */
	protected function getManyManyExclusions() {
		return array ();
	}

	/**
	 * Returns true if the given DataObject was edited after the bookmark for the
	 * migration. This can be checked against a record either on the target or source database.
	 * 
	 * @param  DataObject $record
	 * @return boolean
	 */
	public function isEditedAfterBookmark(DataObject $record) {						
		return strtotime($record->LastEdited) > strtotime($this->bookmark);
	}

	/**
	 * Returns true if the given DataObject was created after the bookmark for the
	 * migration. This can be checked against a record either on the target or source database.
	 *
	 * @param  DataObject $record [description]
	 * @return boolean            [description]
	 */
	public function isCreatedAfterBookmark(DataObject $record) {						
		return strtotime($record->Created) > strtotime($this->bookmark);
	}

	/**
	 * Prompts the user with a conflict, where he can choose how to proceed.
	 * Possible return values:
	 *  (t): keep target
	 *  (s): keep source
	 *  (b): keep both
	 *  
	 * @param  DataObject $sourceRecord 
	 * @param  DataObject $targetRecord 
	 * @return string
	 */
	public function throwConflict(DataObject $sourceRecord, DataObject $targetRecord) {
		$this->task->writeLn();
		$response = trim(strtolower($this->task->prompt("
	[CONFLICT] Target {$sourceRecord->ClassName} record {$sourceRecord->getTitle()} has been updated on the target database since the bookmark.\n
	[Keep (t)arget, Keep (s)ource, Keep (b)oth]
		")));

		if(!in_array($response, array('t','s','b'))) {
			$this->task->writeLn('Invalid response');
			$this->throwConflict($sourceRecord, $targetRecord);
		}

		return $response;
	}

	/**
	 * Handles the update of a record on the target database
	 * 
	 * @param  DataObject $record	 
	 */
	public function handleUpdate(DataObject $record) {
		$this->task->writeLn();
		$this->task->writeLn("\tSource {$record->ClassName} record {$record->getTitle()} is out of date with its target counterpart. Updating.");

		$record->forceChange();
		$record->writeToTarget();
		$this->task->store($record);
		$this->task->updated++;
	}

	/**
	 * Handles the creation of a record on the target database
	 * 
	 * @param  DataObject $record	 
	 */
	public function handleCreate(DataObject $record) {
		$this->task->writeLn();
		$this->task->writeLn("\tSource {$record->ClassName} record {$record->getTitle()} does not exist on target. Creating.");
		
		$record->forceChange();
		$newID = $record->createOnTarget();					
		$record->__TargetID = $newID;		
		$this->task->store($record);
		$this->task->writeLn("\tSource {$record->ClassName} \"{$record->getTitle()}\" stored on target as $newID");
		$this->task->added++;

	}

	/**
	 * Compares a relation in the source and on the target to check for differences.
	 * This is a tri-state response:
	 *  - (-1) Conflict. Target has been modified.
	 *  - (1) Source has been modified. Migrate.
	 *  - (0) No changes.
	 *  
	 * @param  string     $relation 
	 * @param  DataObject $record
	 * @return int
	 */
	protected function relationListCheck($relation, DataObject $record) {
		$sourceIDs = $record->$relation()->fromSource()->column('ID');		
		$targetIDs = $record->$relation()->fromTarget()->column('ID');

		$targetDiff = array_diff($targetIDs, $sourceIDs);
		$sourceDiff = array_diff($sourceIDs, $targetIDs);

		if(!empty($targetDiff)) {
			return -1;
		}
		else if(!empty($sourceDiff)) {
			return 1;
		}					

		return 0;
	}

	/**
	 * Checks a many_many relation. This many not be needed anymore, since there 
	 * is no hasManyCheck(). This method could essentially be merged with
	 * relationListCheck().
	 *
	 * @todo  Merge with relationListCheck().
	 * @param  DataObject $sourceRecord
	 */
	public function manyManyCheck(DataObject $sourceRecord) {
		foreach((array) $sourceRecord->config()->many_many as $relation => $relationClass) {
			$result = $this->relationListCheck($relation, $sourceRecord);
			
			switch($result) {
				case -1:
					$this->task->error("\n\t[CONFLICT] Target {$sourceRecord->ClassName} record {$sourceRecord->getTitle()} has modified its many_many relation $relation since the bookmark. Skipping.");
					$this->task->conflicts++;

				break;

				case 1:					
					$this->task->writeLn("\n\tSource {$sourceRecord->ClassName} record {$sourceRecord->getTitle()} has an updated many_many relation $relation.");
					$this->task->store($sourceRecord);
					$this->task->updated++;							
				break;

			}			
		}
	}

	/**
	 * Creates a has_one relation to a record in the target database using the new
	 * foreign key.
	 * 
	 * @param  DataObject $storedRecord
	 */
	protected function relateHasOne(DataObject $storedRecord) {
		$exclusions = $this->getHasOneExclusions();

		foreach((array) $storedRecord->config()->has_one as $relation => $relationClass) {		
			if(in_array($relation, $exclusions)) continue;
			
			$idField = "{$relation}ID";
			$idVal = $storedRecord->$idField;
			if($updated = $this->task->retrieve($relationClass, $idVal)) {
				$storedRecord->$idField = $updated->__TargetID;
				$storedRecord->forceChange();
				$storedRecord->writeToTarget();
				$this->task->writeLn();
				$this->task->writeLn("\tSource {$storedRecord->ClassName} object has_one relation $relation updated on target");
			}
		}
	}

	/**
	 * Creates a many_many relation on the target database using the new set of foreign keys.
	 * @param  DataObject $storedRecord	 
	 */
	protected function relateManyMany(DataObject $storedRecord) {
		$exclusions = $this->getManyManyExclusions();		

		foreach((array) $storedRecord->config()->many_many as $relation => $relationClass) {
			if(in_array($relation, $exclusions)) continue;

			$result = $this->relationListCheck($relation, $storedRecord);
			
			if($result === 0) continue;

			if($result === -1) {
				$this->task->error("\n\t[CONFLICT] Target {$storedRecord->ClassName} record {$storedRecord->getTitle()} has modified its many_many relation $relation since the bookmark. Skipping.");
				continue;
			}

			$relatedRecords = $storedRecord->$relation()->fromSource()->toArray();			
			$newIDs = array ();
			foreach($relatedRecords as $related) {
				$stored = $this->task->retrieve($related);					
				$newIDs[] = $stored ? $stored->__TargetID : $related->ID;
			}
			$storedRecord->$relation()->fromTarget()->setByIDList($newIDs);

			$storedRecord->forceChange();
			$storedRecord->writeToTarget();
			$this->task->writeLn("\n\tUpdated many_many relation $relation for $storedRecord->Title");
		}
	}

	/**
	 * Given a source record and a target record, figure out if this is 
	 * a create, update, or conflict situation.
	 * 
	 * @param  DataObject $sourceRecord
	 * @param  DataObject $targetRecord	 
	 */
	protected function processUpdate(DataObject $sourceRecord, DataObject $targetRecord) {
		if(!$targetRecord && $this->isCreatedAfterBookmark($sourceRecord)) {
			$this->handleCreate($sourceRecord);
		}			
		else {
			$this->manyManyCheck($sourceRecord);

			$targetCreated = $this->isCreatedAfterBookmark($targetRecord);
			$targetEdited = $this->isEditedAfterBookmark($targetRecord);
			$sourceCreated = $this->isCreatedAfterBookmark($sourceRecord);
			$sourceEdited = $this->isEditedAfterBookmark($sourceRecord);

			// keep both
			if($targetCreated && $sourceCreated) {
				$this->handleCreate($sourceRecord);
			}

			// Throw a conflict
			else if($targetEdited && $sourceEdited) {
				$response = $this->throwConflict($sourceRecord, $targetRecord);
				switch($response) {
					// keep target
					case "t":
						break;

					// keep source
					case "s":
						$this->handleUpdate($sourceRecord);
						break;
					// keep both
					case "b":
						$sourceRecord->ID = 0;
						$this->handleCreate($sourceRecord);
						break;
				}
			}


			else if($targetEdited && !$sourceEdited) {
				$this->task->writeLn("Target record {$targetRecord->Title} was edited, but source record {$sourceRecord->Title} was not. Skipping");
				return;
			}

			else if(!$targetEdited && $sourceEdited) {
				$this->handleUpdate($sourceRecord);
			}
		}	
	}

}