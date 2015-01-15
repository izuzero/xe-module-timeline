<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timelineAdminView
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module admin view class.
 */

class timelineAdminView extends timeline
{
	function init()
	{
		$oTimelineModel = getModel('timeline');
		$module_srl = Context::get('module_srl');
		$timeline_info = $oTimelineModel->getTimelineInfo($module_srl);
		if ($timeline_info)
		{
			$standard_date = sscanf($timeline_info->standard_date, '%04d%02d%02d%02d%02d%02d');
			$limit_date = sscanf($timeline_info->limit_date, '%04d%02d%02d%02d%02d%02d');
			Context::set('timeline_info', $timeline_info);
			Context::set('attach_info', $timeline_info->attach_info);
			Context::set('standard_date', $standard_date);
			Context::set('limit_date', $limit_date);
		}
		else
		{
			Context::set('module_srl', '');
		}

		$oModuleModel = getModel('module');
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);

		$security = new Security();
		$security->encodeHTML('module_category..');

		$this->setTemplatePath($this->module_path . 'tpl');
	}

	function dispTimelineAdminList()
	{
		$oTimelineModel = getModel('timeline');
		$oTimelineController = getController('timeline');
		$whole_timeline_info = $oTimelineModel->getWholeTimelineInfo();

		$oModuleModel = getModel('module');
		$modules_info = array();
		foreach ($whole_timeline_info as $key => $val)
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($key);
			if ($module_info)
			{
				$modules_info[$key] = new stdClass();
				$modules_info[$key]->module_info = $module_info;
				$modules_info[$key]->timeline_info = $val;
			}
			else
			{
				$output = $oTimelineController->deleteTimelineInfo($key);
				if (!$output->toBool())
				{
					return $output;
				}
			}
		}

		$oTimelineAdminModel = getAdminModel('timeline');
		$output = $oTimelineAdminModel->getPageHandler($modules_info, Context::get('page'));
		Context::set('page', $output->page);
		Context::set('total_page', $output->total_page);
		Context::set('total_count', $output->total_count);
		Context::set('modules_info', $output->data);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('list');
	}

	function dispTimelineAdminInfo()
	{
		$timeline_info = Context::get('timeline_info');
		if ($timeline_info)
		{
			$oModuleModel = getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($timeline_info->module_srl);
			Context::set('module_info', $module_info);
		}
		else
		{
			return $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminInsert', 'page', Context::get('page')));
		}

		$this->setTemplateFile('insert');
	}

	function dispTimelineAdminInsert()
	{
		$timeline_info = Context::get('timeline_info');
		if ($timeline_info)
		{
			return $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminInfo', 'module_srl', $timeline_info->module_srl, 'page', Context::get('page')));
		}

		$oTimelineModel = getModel('timeline');
		$timeline_list = $oTimelineModel->getTimelineList();

		$args = new stdClass();
		$args->module_srl = $timeline_list;
		$output = executeQueryArray('timeline.getUsableModuleList', $args);
		Context::set('module_list', $output->data);

		$this->setTemplateFile('insert');
	}
}

/* End of file timeline.admin.view.php */
/* Location: ./modules/timeline/timeline.admin.view.php */
