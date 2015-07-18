<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timeline
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module high class.
 */

class timeline extends ModuleObject
{
	// table, column, type, size, default, notnull
	protected static $columns = array(
		array( 'timeline_registered_info', 'notice',             'char',    1,    'N',   TRUE  ),
		array( 'timeline_registered_info', 'replace',            'char',    1,    'N',   TRUE  ),
		array( 'timeline_registered_info', 'write',              'char',    1,    'N',   TRUE  ),
		array( 'timeline_registered_info', 'popular_count',      'number',  11,   '0',   TRUE  ),
		array( 'timeline_registered_info', 'cond_popular_count', 'varchar', 6,   'more', TRUE  ),
		array( 'timeline_registered_info', 'standard_date',      'date',    NULL, NULL,  FALSE ),
		array( 'timeline_registered_info', 'limit_date',         'date',    NULL, NULL,  FALSE ),
		array( 'timeline_registered_info', 'auto_renewal',       'char',    1,    'N',   TRUE  ),
		array( 'timeline_attach_info',     'priority',           'number',  11,   '1',   FALSE )
	);

	// table, index, unique
	protected static $deletedIndexes = array(
		array( 'timeline_attach_info', 'idx_priority', TRUE )
	);

	// table, index, column, unique
	protected static $indexes = array(
		array( 'timeline_registered_info', 'idx_popular_count', array('popular_count'), FALSE ),
		array( 'timeline_registered_info', 'idx_standard_date', array('standard_date'), FALSE ),
		array( 'timeline_registered_info', 'idx_limit_date',    array('limit_date'),    FALSE )
	);

	protected static $triggers = array(
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

		foreach (self::$triggers as $trigger)
		{
			$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new Object();
	}

	function moduleUninstall()
	{
		$oModuleController = getController('module');

		foreach (self::$triggers as $trigger)
		{
			$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new Object();
	}

	function checkUpdate()
	{
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');

		foreach (self::$columns as $column)
		{
			if (!$oDB->isColumnExists($column[0], $column[1]))
			{
				return TRUE;
			}
		}
		foreach (self::$deletedIndexes as $index)
		{
			if ($oDB->isIndexExists($index[0], $index[1]))
			{
				return TRUE;
			}
		}
		foreach (self::$indexes as $index)
		{
			if (!$oDB->isIndexExists($index[0], $index[1]))
			{
				return TRUE;
			}
		}
		foreach (self::$triggers as $trigger)
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

		foreach (self::$columns as $column)
		{
			if (!$oDB->isColumnExists($column[0], $column[1]))
			{
				$oDB->addColumn($column[0], $column[1], $column[2], $column[3], $column[4], $column[5]);
			}
		}
		foreach (self::$deletedIndexes as $index)
		{
			if ($oDB->isIndexExists($index[0], $index[1]))
			{
				$oDB->dropIndex($index[0], $index[1], $index[2]);
			}
		}
		foreach (self::$indexes as $index)
		{
			if (!$oDB->isIndexExists($index[0], $index[1]))
			{
				$oDB->addIndex($index[0], $index[1], $index[2], $index[3]);
			}
		}
		foreach (self::$triggers as $trigger)
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
