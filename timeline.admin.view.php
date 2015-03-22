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
		// 타임라인 게시판 정보 유효성 검증
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

		// 모듈 분류 불러오기
		$oModuleModel = getModel('module');
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);

		$security = new Security();
		$security->encodeHTML('module_category..');

		// 모듈 설치 여부 불러오기
		$is_installed = !$this->checkUpdate();
		Context::set('is_installed', $is_installed);

		// 템플릿 경로 설정
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	/**
	 * @brief 타임라인 게시판 목록
	 * @return void
	 */
	function dispTimelineAdminList()
	{
		$oTimelineModel = getModel('timeline');
		$oTimelineController = getController('timeline');
		// 모든 타임라인 게시판 정보 불러오기
		$whole_timeline_info = $oTimelineModel->getWholeTimelineInfo();

		$oModuleModel = getModel('module');
		$modules_info = array();
		// 타임라인 게시판의 모듈 정보 불러오기
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

		// 데이터 페이징 처리
		$oTimelineAdminModel = getAdminModel('timeline');
		$output = $oTimelineAdminModel->getPageHandler($modules_info, Context::get('page'));
		Context::set('page', $output->page);
		Context::set('total_page', $output->total_page);
		Context::set('total_count', $output->total_count);
		Context::set('modules_info', $output->data);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('list');
	}

	/**
	 * @brief 타임라인 게시판 설정
	 * @return void
	 */
	function dispTimelineAdminInfo()
	{
		$timeline_info = Context::get('timeline_info');
		if ($timeline_info)
		{
			// 타임라인 게시판의 모듈 정보를 템플릿으로 넘김
			$oModuleModel = getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($timeline_info->module_srl);
			Context::set('module_info', $module_info);
		}
		else
		{
			// 타임라인 게시판 정보가 없을 경우
			return $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminInsert', 'page', Context::get('page')));
		}

		$this->setTemplateFile('insert');
	}

	/**
	 * @brief 새로운 타임라인 게시판 등록
	 * @return void
	 */
	function dispTimelineAdminInsert()
	{
		$timeline_info = Context::get('timeline_info');
		if ($timeline_info)
		{
			// 타임라인 게시판 정보가 이미 있는 경우
			return $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminInfo', 'module_srl', $timeline_info->module_srl, 'page', Context::get('page')));
		}

		// 이미 등록되어 있는 타임라인 게시판 목록 불러오기
		$oTimelineModel = getModel('timeline');
		$timeline_list = $oTimelineModel->getTimelineList();

		// 타임라인 게시판으로 등록 가능한 모듈 목록 불러오기
		$args = new stdClass();
		$args->module_srl = $timeline_list;
		$output = executeQueryArray('timeline.getUsableModuleList', $args);
		Context::set('module_list', $output->data);

		$this->setTemplateFile('insert');
	}
}

/* End of file timeline.admin.view.php */
/* Location: ./modules/timeline/timeline.admin.view.php */
