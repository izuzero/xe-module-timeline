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

	/**
	 * @brief 타임라인 게시판 등록
	 * @return void
	 */
	function procTimelineAdminInsert()
	{
		$args = Context::getRequestVars();
		// 입력 받은 기준 날짜와 시간 범위를 date string으로 변환
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
		// 입력 받은 기준 날짜가 잘못된 날짜인 경우
		if ($args->standard_date && !strtotime($args->standard_date))
		{
			return new Object(-1, 'msg_timeline_invalid_date');
		}

		// 이미 등록되어 있는 타임라인 게시판인지 확인
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

		// 타임라인 게시판 등록
		$oTimelineController = getController('timeline');
		$output = $oTimelineController->insertTimelineInfo($args);
		if (!$output->toBool())
		{
			return $output;
		}

		// 게시글을 모을 대상 등록
		$attach_info = explode(',', Context::get('target_module_srl'));
		$output = $oTimelineController->insertAttachInfo($args->module_srl, $attach_info);
		if (!$output->toBool())
		{
			return $output;
		}

		// 주소 리다이렉트
		$this->setMessage($lang_code);
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminInfo', 'module_srl', $args->module_srl, 'page', Context::get('page')));
	}

	/**
	 * @brief 타임라인 게시판 삭제
	 * @return void
	 */
	function procTimelineAdminDelete()
	{
		// 삭제할 타임라인 게시판
		$module_srls = Context::get('module_srl');
		if (!($module_srls && is_array($module_srls)))
		{
			$module_srls = array();
		}

		// 타임라인 게시판 삭제
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

		// DB에 반영
		$oDB->commit();

		// 주소 리다이렉트
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispTimelineAdminList', 'page', Context::get('page')));
	}
}

/* End of file timeline.admin.controller.php */
/* Location: ./modules/timeline/timeline.admin.controller.php */
