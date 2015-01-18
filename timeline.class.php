<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timeline
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module high class.
 */

class timeline extends ModuleObject
{
	private $columns = array(
		array( 'timeline_registered_info', 'notice',        'char', 1,    'N',  TRUE  ),
		array( 'timeline_registered_info', 'replace',       'char', 1,    'N',  TRUE  ),
		array( 'timeline_registered_info', 'write',         'char', 1,    'N',  TRUE  ),
		array( 'timeline_registered_info', 'standard_date', 'date', NULL, NULL, FALSE ),
		array( 'timeline_registered_info', 'limit_date',    'date', NULL, NULL, FALSE ),
		array( 'timeline_registered_info', 'auto_renewal',  'char', 1,    'N',  TRUE  )
	);

	private $indexes = array(
		array( 'timeline_registered_info', 'idx_standard_date', array('standard_date'), FALSE ),
		array( 'timeline_registered_info', 'idx_limit_date',    array('limit_date'),    FALSE )
	);

	private $triggers = array(
		array( 'moduleHandler.init', 'timeline', 'controller', '_setTimelineInfo',          'after'  ),
		array( 'moduleHandler.init', 'timeline', 'controller', '_replaceMid',               'before' ),
		array( 'moduleHandler.init', 'timeline', 'controller', '_replaceModuleInfo',        'after'  ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_rollbackBeforeModuleInfo', 'before' ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_rollbackAfterModuleInfo',  'after'  ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_replaceDocumentList',      'after'  ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_replaceCategoryList',      'after'  ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_replaceNoticeList',        'after'  )
	);

	function moduleInstall()
	{
		$oModuleController = getController('module');
		foreach ($this->triggers as $trigger)
		{
			$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new Object();
	}

	function moduleUninstall()
	{
		$oModuleController = getController('module');
		foreach ($this->triggers as $trigger)
		{
			$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new Object();
	}

	function checkUpdate()
	{
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');
		foreach ($this->columns as $column)
		{
			if (!$oDB->isColumnExists($column[0], $column[1]))
			{
				return TRUE;
			}
		}
		foreach ($this->indexes as $index)
		{
			if (!$oDB->isIndexExists($index[0], $index[1]))
			{
				return TRUE;
			}
		}
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	function moduleUpdate()
	{
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach ($this->columns as $column)
		{
			if (!$oDB->isColumnExists($column[0], $column[1]))
			{
				$oDB->addColumn($column[0], $column[1], $column[2], $column[3], $column[4], $column[5]);
			}
		}
		foreach ($this->indexes as $index)
		{
			if (!$oDB->isIndexExists($index[0], $index[1]))
			{
				$oDB->addIndex($index[0], $index[1], $index[2], $index[3]);
			}
		}
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}

		return new Object();
	}
}

/* End of file timeline.class.php */
/* Location: ./modules/timeline/timeline.class.php */
