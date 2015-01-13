<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timelineController
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module controller class.
 */

class timelineController extends timeline
{
	function init()
	{
	}

	function insertTimelineInfo($args)
	{
		if (!(is_object($args) && $args->module_srl))
		{
			return new Object(-1, 'msg_timeline_no_module_srl');
		}

		$oDB = DB::getInstance();
		$oDB->begin();
		$output = $this->deleteTimelineInfo($args->module_srl);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$output = executeQuery('timeline.insertTimelineInfo', $args);
		if ($output->toBool())
		{
			$oDB->commit();
			unset($GLOBALS['__timeline__']['timeline_list']);
			unset($GLOBALS['__timeline__']['timeline_info']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('timeline');
			}
		}
		else
		{
			$oDB->rollback();
		}

		return $output;
	}

	function insertAttachInfo($module_srl, $target_srls = array())
	{
		if (!($module_srl && is_numeric($module_srl)))
		{
			return new Object(-1, 'msg_timeline_no_module_srl');
		}
		if (!is_array($target_srls))
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$oDB = DB::getInstance();
		$oDB->begin();
		$output = $this->deleteAttachInfo($module_srl);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$args = new stdClass();
		$args->module_srl = $module_srl;
		foreach ($target_srls as $target_srl)
		{
			$args->target_srl = $target_srl;
			$output = executeQuery('timeline.insertAttachInfo', $args);
			if (!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}

		$oDB->commit();
		return new Object();
	}

	function deleteTimelineInfo($module_srl)
	{
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('timeline.deleteTimelineInfo', $args);
		if ($output->toBool())
		{
			unset($GLOBALS['__timeline__']['timeline_list']);
			unset($GLOBALS['__timeline__']['timeline_info']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('timeline');
			}
		}

		return $output;
	}

	function deleteAttachInfo($module_srl)
	{
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('timeline.deleteAttachInfo', $args);
		if ($output->toBool())
		{
			unset($GLOBALS['__timeline__']['timeline_info']);
			unset($GLOBALS['__timeline__']['attach_info']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('timeline');
			}
		}

		return $output;
	}

	function _setTimelineInfo(&$oModule)
	{
		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($oModule->module_srl);
		$attach_info = $timeline_info->attach_info;
		if (!$timeline_info)
		{
			return new Object();
		}

		$attach_info[] = $oModule->module_srl;
		$oModuleModel = getModel('module');
		$modules_info = array();
		foreach ($attach_info as $item)
		{
			if (is_null($modules_info[$item]))
			{
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($item);
				if ($module_info)
				{
					$modules_info[$item] = $module_info;
				}
			}
		}

		Context::set('timeline_info', $timeline_info);
		Context::set('modules_info', $modules_info);

		return new Object();
	}

	function _replaceMid(&$oModule)
	{
		$mid = Context::get('mid');
		$document_srl = Context::get('document_srl');
		if (!$mid || !$document_srl || isCrawler())
		{
			return new Object();
		}

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid($mid);
		$curr_module_srl = $module_info->module_srl;
		if (!$curr_module_srl)
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($curr_module_srl);
		$attach_info = $timeline_info->attach_info;
		/* 타임라인에 등록되어 있지 않은 모듈 */
		if (!$timeline_info)
		{
			return new Object();
		}

		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		$origin_module_srl = $oDocument->get('module_srl');
		/* module id replace를 회피할 필요 없거나 타임라인에 등록되어 있지 않은 모듈인 경우 */
		if ($curr_module_srl == $origin_module_srl || !in_array($origin_module_srl, $attach_info))
		{
			return new Object();
		}

		/* 타임라인에 등록되어 있는 게시판이지만 게시글 필터링을 통과하지 못한 경우 */
		$tl_filter = array('readed_count', 'voted_count', 'blamed_count', 'comment_count');
		foreach ($tl_filter as $filter)
		{
			$key = $timeline_info->{'cond_' . $filter};
			$val = $timeline_info->{$filter};
			$if_val = $oDocument->get($filter);
			if ($filter == 'blamed_count')
			{
				$if_val *= -1;
			}
			if ($val && (($key == 'excess' && $val >= $if_val) || ($key == 'below' && $val <= $if_val) || ($key == 'more' && $val > $if_val) || ($key == 'less' && $val < $if_val)))
			{
				return new Object();
			}
		}

		/* module id replace를 회피하기 위해 게시글 정보 동기화 */
		$origin_module_info = $oModuleModel->getModuleInfoByModuleSrl($origin_module_srl);
		$this->curr_module_info = $module_info;
		$this->origin_module_info = $origin_module_info;
		Context::set('mid', $oModule->mid = $origin_module_info->mid);
		$GLOBALS['XE_DOCUMENT_LIST'][$document_srl]->add('module_srl', $curr_module_srl);

		return new Object();
	}

	function _replaceModuleInfo(&$module_info)
	{
		/* 게시글 정보 동기화 롤백 */
		if ($this->curr_module_info)
		{
			$module_info = $this->curr_module_info;
			Context::set('mid', $module_info->mid);
		}

		return new Object();
	}

	function _replaceModuleSrlForWrite(&$oModule)
	{
		if ($oModule->act != 'procBoardInsertDocument')
		{
			return new Object();
		}

		$module_srl = $oModule->module_srl;
		$target_module_srl = Context::get('module_srl');
		$document_srl = Context::get('document_srl');
		if ($document_srl)
		{
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if ($oDocument->isExists())
			{
				if ($this->origin_module_info)
				{
					$oModule->module_srl = $this->origin_module_info->module_srl;
				}

				return new Object();
			}
		}
		if ($target_module_srl)
		{
			$oTimelineModel = getModel('timeline');
			$timeline_info = $oTimelineModel->getTimelineInfo($module_srl);
			$attach_info = $timeline_info->attach_info;
			if ($timeline_info && in_array($target_module_srl, $attach_info))
			{
				$oModule->module_srl = $target_module_srl;
			}
		}

		return new Object();
	}

	function _replaceModuleSrlForCategory(&$oModule)
	{
		if ($oModule->act != 'procBoardInsertDocument')
		{
			return new Object();
		}

		$module_srl = $oModule->module_srl;
		$category_srl = Context::get('category_srl');
		$document_srl = Context::get('document_srl');
		if (!$category_srl || $document_srl)
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($module_srl);
		$attach_info = $timeline_info->attach_info;
		if (!$timeline_info)
		{
			return new Object();
		}

		$oDocumentModel = getModel('document');
		$category_list = $oDocumentModel->getCategoryList($module_srl);
		foreach ($attach_info as $item)
		{
			$category_list += $oDocumentModel->getCategoryList($item);
		}
		if (!$category_list[$category_srl] || !$category_list[$category_srl]->grant)
		{
			return new Object(-1, 'msg_not_permitted');
		}

		$target_module_srl = $category_list[$category_srl]->module_srl;
		$oModule->module_srl = $target_module_srl;

		return new Object();
	}

	function _replaceDocumentList(&$oModule)
	{
		$document_list = Context::get('document_list');
		if (is_null($document_list) || !$oModule->grant->list)
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($oModule->module_srl);
		if (!$timeline_info)
		{
			return new Object();
		}

		$attach_info = $timeline_info->attach_info;
		$attach_info[] = $oModule->module_srl;

		$args = new stdClass();
		$args->module_srl = $attach_info;
		$args->tl_title = $timeline_info->title;
		$args->tl_content = $timeline_info->content;
		$args->tl_tags = $timeline_info->tags;
		$args->page = Context::get('page');
		$args->list_count = $oModule->list_count;
		$args->page_count = $oModule->page_count;
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');

		$module_srl = intval(Context::get('module_srl'));
		if (in_array($module_srl, $attach_info))
		{
			$args->module_srl = $module_srl;
		}

		$tl_filter = array('readed_count', 'voted_count', 'blamed_count', 'comment_count');
		$tl_operation = array('excess', 'below', 'more', 'less');
		foreach ($tl_filter as $filter)
		{
			$key = $timeline_info->{'cond_' . $filter};
			if ($key && in_array($key, $tl_operation))
			{
				$key = 'tl_' . $key . '_' . $filter;
				$args->{$key} = $timeline_info->{$filter};
			}
		}

		$search_option = Context::get('search_option');
		if ($search_option == FALSE)
		{
			$search_option = $oModule->search_option;
		}
		if (is_null($search_option[$args->search_target]))
		{
			$args->search_target = '';
		}
		if ($oModule->module_info->use_category == 'Y')
		{
			$args->category_srl = Context::get('category');
		}

		$args->sort_index = Context::get('sort_index');
		$args->order_type = Context::get('order_type');
		if (!in_array($args->sort_index, $oModule->order_target))
		{
			$args->sort_index = $oModule->module_info->order_target;
			if (!$args->sort_index)
			{
				$args->sort_index = 'list_order';
			}
		}
		if (!in_array($args->order_type, array('asc', 'desc')))
		{
			$args->order_type = $oModule->module_info->order_type;
			if (!$args->order_type)
			{
				$args->order_type = 'asc';
			}
		}

		$document_srl = Context::get('document_srl');
		if (!$args->page && $document_srl)
		{
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if ($oDocument->isExists() && !$oDocument->isNotice())
			{
				$page = $oTimelineModel->getDocumentPage($oDocument, $args);
				Context::set('page', $args->page = $page);
			}
		}
		if ($args->category_srl || $args->search_keyword)
		{
			$args->list_count = $oModule->search_list_count;
		}
		if ($oModule->consultation)
		{
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
		}

		$output = $oTimelineModel->getDocumentList($args, $oModule->except_notice, TRUE, $oModule->columnList);
		Context::set('document_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		return new Object();
	}

	function _replaceDocumentObject(&$oModule)
	{
		$module_info = $this->origin_module_info;
		$oDocument = Context::get('oDocument');
		if ($module_info && $oDocument)
		{
			$oDocument->add('module_srl', $module_info->module_srl);
			Context::set('oDocument', $oDocument);
		}

		return new Object();
	}

	function _replaceCategoryList(&$oModule)
	{
		$category_list = Context::get('category_list');
		if (is_null($category_list) || $oModule->module_info->use_category != 'Y')
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($oModule->module_srl);
		$attach_info = $timeline_info->attach_info;
		if (!$timeline_info)
		{
			return new Object();
		}

		$oDocumentModel = getModel('document');
		foreach ($attach_info as $item)
		{
			$category_list += $oDocumentModel->getCategoryList($item);
		}

		Context::set('category_list', $category_list);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('category_list.', 'category_list.childs.');

		return new Object();
	}
}

/* End of file timeline.controller.php */
/* Location: ./modules/timeline/timeline.controller.php */
