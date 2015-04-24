<?php

/**
 * A heirarchical migration specifically for SiteTree
 * 
 * @package  silverstripe-db-surgeon
 * @author Uncle Cheese <unclecheese@leftandmain.com>
 */
class DatabaseSurgeonSiteTreeMigration extends DatabaseSurgeonHierarchicalMigration {

	/**
	 * The base class of the hierarchy
	 * @var string
	 */
	protected $baseClass = 'SiteTree';	

	/**
	 * Gets a list of has_one relations that should be ignored
	 *
	 * @return array
	 */
	protected function getHasOneExclusions() {
		return array (
			'Parent'
		);
	}

	/**
	 * A list of many_many relations that should be ignored
	 *
	 * @return array
	 */
	protected function getManyManyExclusions() {
		return array (
			'LinkTracking',
			'ImageTracking',
			'BackLinkTracking'
		);
	}

	/**
	 * Using a list of ids, delete from the target database
	 * 
	 * @param  array $targetIDs	 
	 */
	protected function handleDeletion($targetIDs) {
		if(empty($targetIDs)) return;
		
		$list = DataList::create($this->baseClass)
					->fromTarget()
					->byIDs($targetIDs);
		foreach($list as $node) {				
			$node->deleteFromStage('Live');
			$node->deleteFromStage('Stage');
			$node->delete();
		}
	}
}