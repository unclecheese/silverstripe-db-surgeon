<?php

/**
 * The task that handles the migration of DataObjects (i.e. not SiteTree, not File)
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonDataObjectMigration extends DatabaseSurgeonBaseMigration {

	/**
	 * Gets a list of classes that will be checked in this migration
	 * 		
	 * @return array
	 */
	protected function getQualifyingClasses() {
		$map = ArrayLib::valuekey(
			ClassInfo::subclassesFor('DataObject')
		);

		unset($map['DataObject']);

		foreach($map as $k => $class) {
			$sng = Injector::inst()->get($class);
			if($sng instanceof TestOnly || $sng instanceof SiteTree || $sng instanceof File) {
				unset($map[$class]);
			}
		}
		ksort($map);

		return array_values($map);
	}

	/**
	 * Handles the "update" phase of a DataObject migration in which
	 * records are either created or updated on the target
	 */
	public function runUpdatePhase() {
		$classes = $this->getQualifyingClasses();	
		$previousClass = null;		
		// add, update
		foreach($classes as $class) {
			$classLabel = str_pad("--- $class ---", 50, ' ', STR_PAD_RIGHT);
			$this->task->write($classLabel);
			$sourceRecords = $class::get()->fromSource()->toArray();
			$hasActivity = false;			
			foreach($sourceRecords as $sourceRecord) {			
				$targetRecord = $class::get()
									->fromTarget()
									->byID($sourceRecord->ID);

				$msg = $this->processUpdate($sourceRecord, $targetRecord);

				if($msg) {
					if(!$hasActivity) $this->task->writeln();
					$hasActivity = true;
					$this->task->write("\t".$msg);
				}
			}
			if($hasActivity) {
				$this->task->writeln();
				$this->task->write($classLabel);
			}
			$this->task->write("\033[50D");
			$previousClass = $class;
		}
	}

	/**
	 * Handles the "delete" phase of a DataObject migration in which
	 * records are deleted on the target due to their absence in the source	 
	 */
	public function runDeletePhase() {
		$classes = $this->getQualifyingClasses();			
		$previousClass = null;				
		foreach($classes as $class) {
			$targetIDs = array ();
			$classLabel = str_pad("--- $class ---", 50, ' ', STR_PAD_RIGHT);
			$this->task->write($classLabel);
			$targetRecords = $class::get()->fromTarget()->toArray();
			$hasActivity = false;			
			foreach($targetRecords as $targetRecord) {
				$sourceRecord = $class::get()->fromSource()->byID($targetRecord->ID);
				if(!$sourceRecord && !$this->isEditedAfterBookmark($targetRecord)) {					
					$this->task->log("Target {$targetRecord->ClassName} \"{$targetRecord->getTitle()}\" is not in the source database. Deleting.");
					$targetIDs[] = $targetRecord->ID;
					$this->task->deleted++;

					if(!$hasActivity) {
						$this->task->writeln();
						$hasActivity = true;
						$this->task->writeln($targetRecord->getTitle() . " " . SS_Cli::text('[DELETED]', 'red', null, true));
					}
				}
			}

			if(!empty($remoteIDs)) {				
				$class::get()->fromTarget()->byIDs($remoteIDs)->removeAll();				
			}

			if($hasActivity) {
				$this->task->write($classLabel);
			}

			$this->task->write("\033[50D");
			$previousClass = $class;
		}
	}

	/**
	 * Handles the "relation" phase of a DataObject migration in which
	 * records are assigned new foreign keys	 
	 */
	public function runRelatePhase() {
		$previousClass = null;
		$classes = $this->getQualifyingClasses();

		foreach($classes as $class) {
			$classLabel = str_pad("--- $class ---", 50, ' ', STR_PAD_RIGHT);
			$this->task->write($classLabel);
			$sourceRecords = $class::get()->fromSource()->toArray();			
			$hasActivity = false;
			foreach($sourceRecords as $sourceRecord) {
				$storedRecord = $this->task->retrieve($sourceRecord);
				if(!$storedRecord) {
					continue;
				}

				$hasOneMsg = $this->relateHasOne($storedRecord);
				$mmMsg = $this->relateManyMany($storedRecord);

				if($hasOneMsg || $mmMsg) {
					if(!$hasActivity) $this->task->writeln();
					$hasActivity = true;
					if($hasOneMsg) {
						$this->task->writeln("\t".$hasOneMsg);
					}
					if($mmMsg) {
						$this->task->writeln("\t".$mmMsg);
					}
				}

			}

			if($hasActivity) {
				$this->task->writeln();
				$this->task->write($classLabel);
			}
			$this->task->write("\033[50D");
			$previousClass = $class;
		}

	}
}