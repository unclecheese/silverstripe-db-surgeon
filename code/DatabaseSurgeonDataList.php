<?php

/**
 * Enhances DataList to be able to query against the source database
 * or the target database
 *
 * NB: Due to lazy loading, the query may not be run until after you have
 * reconnected to a different database. Be sure to run ->toArray()
 * against the list to ensure the query is executed immediately.
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonDataList extends DataExtension {

	/**
	 * Connect to the target
	 * 
	 * @return DataList
	 */
	public function fromTarget() {
		DatabaseSurgeonTask::connect_to_target();

		return $this->owner;
	}

	/**
	 * Connect to the source
	 * 
	 * @return DataList
	 */
	public function fromSource() {
		DatabaseSurgeonTask::connect_to_source();

		return $this->owner;
	}
}