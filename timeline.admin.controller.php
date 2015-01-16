<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timelineAdminController
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module admin controller class.
 */

class timelineAdminController extends timeline
{
	function init()
	{
	}

	function procTimelineAdminInsert()
	{
		$args = Context::getRequestVars();
		$keys = array('standard_date', 'limit_date');
		foreach ($keys as $key)
		{
			list($year, $month, $day, $hour, $minute, $second) = $args->{$key};
			$args->{$key} = sprintf('%04d%02d%02d%02d%02d%02d', $year, $month, $day, $hour, $minute, $second);
			if (!(intval($args->{$key}) && $year < 10000 && $month < 100 && $day < 100 && $hour < 100 && $minute < 100 && $second < 100))
			{
				unset($args->{$key});
			}
		}
		if ($args->standard_date && !strtotime($args->standard_date))
		{
			return new Object(-1, 'msg_timeline_invalid_date');
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($args->module_srl);
		if ($timeline_info)
		{
			$lang_code = 'success_updated';
		}
		else
		{
			$lang_code = 'success_registed';
		}

		$oTimelineController = getController('timeline');
		$output = $oTimelineController->insertTimelineInfo($args);
		if (!$output->toBool())
		{
			return $output;
		}

		$attach_info = explode(',', Context::get('target_module_srl'));
		$output = $oTimelineController->insertAttachInfo($args->module_srl, $attach_info);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage($lang_code);
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminInfo', 'module_srl', $args->module_srl, 'page', Context::get('page')));
	}

	function procTimelineAdminDelete()
	{
		$module_srls = Context::get('module_srl');
		if (!($module_srls && is_array($module_srls)))
		{
			$module_srls = array();
		}

		$oDB = DB::getInstance();
		$oDB->begin();
		$oTimelineController = getController('timeline');
		foreach ($module_srls as $module_srl)
		{
			$output = $oTimelineController->deleteTimelineInfo($module_srl);
			if (!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}

		$oDB->commit();
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminList', 'page', Context::get('page')));
	}
}

/* End of file timeline.admin.controller.php */
/* Location: ./modules/timeline/timeline.admin.controller.php */
