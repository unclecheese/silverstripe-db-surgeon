<?php

/**
 * Enhances DataObject to be able to write or create on the source or target explicitly.
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonDataObject extends DataExtension {

	/**
	 * Writes the record to the target database
	 * @param  boolean $showDebug       
	 * @param  boolean $forceInsert     
	 * @param  boolean $forceWrite      
	 * @param  boolean $writeComponents 
	 * @return int The ID
	 */
	public function writeToTarget($showDebug = false, $forceInsert = false, $forceWrite = false, $writeComponents = false) {
		DatabaseSurgeonTask::connect_to_target();
		
		$originalID = $this->owner->ID;
		if($this->owner->__TargetID) {
			$this->owner->ID = $this->owner->__TargetID;
		}
		
		$originalParentID = $this->owner->ParentID;
		if($this->owner->__TargetParentID) {
			$this->owner->ParentID = $this->owner->__TargetParentID;
		}

		$ret = $this->owner->write($showDebug, $forceInsert, $forceWrite, $writeComponents);
		$this->owner->ID = $originalID;

		if($originalParentID) {
			$this->owner->ParentID = $originalParentID;
		}

		return $ret;
	}

	/**
	 * Creates the record on the target database
	 * 
	 * @return int The ID
	 */
	public function createOnTarget() {
		DatabaseSurgeonTask::connect_to_target();
		
		$originalID = $this->owner->ID;
		$this->owner->ID = 0;
		$ret = $this->owner->write(false, true);
		$this->owner->ID = $originalID;

		return $ret;
	}

	/**
	 * Writes the record to the source database
	 * 	
	 * @return int The ID
	 */
	public function writeToSource() {
		DatabaseSurgeonTask::connect_to_source();

		return $this->owner->write();
	}

	/**
	 * Explicitly convert this DataObject to its "target" counterpart by flipping the IDs
	 * @return DataObject
	 */
	public function useTarget() {
		if($this->owner->__TargetID) {
			$originalID = $this->owner->ID;
			$this->owner->ID = $this->owner->__TargetID;
			$this->__SourceID = $originalID;
		}

		return $this->owner;
	}

	/**
	 * Explicitly convert this DataObject to its "source" counterpart by flipping the IDs
	 * @return DataObject
	 */
	public function useSource() {
		if($this->owner->__SourceID) {			
			$this->owner->ID = $this->owner->__SourceID;
		}

		return $this->owner;		
	}
}