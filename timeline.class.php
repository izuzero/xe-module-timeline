<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timeline
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module high class.
 */

class timeline extends ModuleObject
{
	private $triggers = array(
		array( 'moduleObject.proc',  'timeline', 'controller', '_setTimelineInfo',          'after'  ),
		array( 'moduleHandler.init', 'timeline', 'controller', '_replaceMid',               'before' ),
		array( 'moduleHandler.init', 'timeline', 'controller', '_replaceModuleInfo',        'after'  ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_rollbackBeforeModuleInfo', 'before' ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_rollbackAfterModuleInfo',  'after'  ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_replaceDocumentList',      'after'  ),
		array( 'moduleObject.proc',  'timeline', 'controller', '_replaceCategoryList',      'after'  )
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
		$oModuleModel = getModel('module');
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return TRUE;
			}
		}

		return false;
	}

	function moduleUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
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
