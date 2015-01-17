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
			unset($GLOBALS['__timeline__']['attach_info']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('timeline');
			}
		}

		return $output;
	}

	function renewalTimelineInfo(&$timeline_info)
	{
		$oTimelineModel = getModel('timeline');
		$standard_date = strtotime($timeline_info->standard_date);
		$limit_date = $oTimelineModel->getStrTime($timeline_info->limit_date);
		if ($standard_date === FALSE || is_null($limit_date) || $timeline_info->auto_renewal != 'Y')
		{
			return $timeline_info;
		}

		$now_date = time();
		$diff_date = strtotime($limit_date, $standard_date) - $standard_date;
		$repeat = floor(($now_date - $standard_date) / $diff_date);
		if (!$repeat)
		{
			return $timeline_info;
		}

		$last_date = $standard_date + ($diff_date * $repeat);
		$timeline_info->standard_date = date('YmdHis', $last_date);
		$this->insertTimelineInfo($timeline_info);

		return $timeline_info;
	}

	function _setTimelineInfo(&$module_info)
	{
		$curr_module_info = $this->curr_module_info;
		if (!$curr_module_info)
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($curr_module_info->module_srl);
		$attach_info = $timeline_info->attach_info;
		$attach_info[] = $timeline_info->module_srl;

		$oModuleModel = getModel('module');
		$modules_info = array();
		foreach ($attach_info as $item)
		{
			if (!isset($modules_info[$item]))
			{
				$target_module_info = $oModuleModel->getModuleInfoByModuleSrl($item);
				if ($target_module_info)
				{
					$modules_info[$item] = $target_module_info;
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
		$comment_srl = Context::get('comment_srl');
		$document_srl = Context::get('document_srl');
		if (!$mid)
		{
			return new Object();
		}

		$oModuleModel = getModel('module');
		$curr_module_info = $oModuleModel->getModuleInfoByMid($mid);
		if (!$curr_module_info->module_srl)
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($curr_module_info->module_srl);
		if (!$timeline_info)
		{
			return new Object();
		}

		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		$document_srl = $oDocument->get('document_srl');
		$module_srl = $oDocument->get('module_srl');
		if ($oDocument->isExists())
		{
			$attach_info = $timeline_info->attach_info;
			if (in_array($module_srl, $attach_info) && $oDocument->get('is_notice') == 'Y' && $timeline_info->notice != 'Y')
			{
				return new Object();
			}

			$attach_info[] = $timeline_info->module_srl;
			if (in_array($module_srl, $attach_info) && ($oDocument->get('is_notice') == 'Y' || $oTimelineModel->isFilterPassed($timeline_info->module_srl, $document_srl)))
			{
				$origin_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			}
		}

		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl);
		if ($oDocument->isExists() && $oComment->isExists() && $oComment->get('document_srl') != $document_srl)
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$this->curr_module_info = $curr_module_info;
		$this->origin_module_info = $origin_module_info;
		if ($origin_module_info && $timeline_info->replace != 'Y' && !isCrawler())
		{
			Context::set('mid', $oModule->mid = $origin_module_info->mid);
		}

		return new Object();
	}

	function _replaceModuleInfo(&$module_info)
	{
		$curr_module_info = $this->curr_module_info;
		if (!$curr_module_info)
		{
			return new Object();
		}

		$module_info = clone($curr_module_info);
		$origin_module_info = clone($module_info);
		if ($origin_module_info)
		{
			$module_info->mid = $origin_module_info->mid;
			$module_info->module_srl = $origin_module_info->module_srl;
		}

		$act = Context::get('act');
		$exception = array('dispBoardWrite', 'procBoardInsertDocument', 'procBoardInsertComment');
		if (in_array($act, $exception))
		{
			$this->is_replaceable = FALSE;

			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument(Context::get('document_srl'));
			if (!$oDocument->isExists())
			{
				$oModuleModel = getModel('module');
				$oTimelineModel = getModel('timeline');
				$timeline_info = $oTimelineModel->getTimelineInfo($curr_module_info->module_srl);
				$module_srl = Context::get('module_srl');
				$category_srl = Context::get('category_srl');
				if ($category_srl)
				{
					$attach_info = $timeline_info->attach_info;
					$attach_info[] = $timeline_info->module_srl;
					foreach ($attach_info as $item)
					{
						$category_list = $oDocumentModel->getCategoryList($item);
						$category = $category_list[$category_srl];
						if ($category && $category->grant)
						{
							break;
						}
						unset($category);
					}
					if (!$category)
					{
						return new Object(-1, 'msg_not_permitted');
					}
					$target_module_info = $oModuleModel->getModuleInfoByModuleSrl($category->module_srl);
				}
				else if ($module_srl)
				{
					$attach_info = $timeline_info->attach_info;
					$attach_info[] = $timeline_info->module_srl;
					if (in_array($module_srl, $attach_info))
					{
						$target_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
					}
					else
					{
						return new Object(-1, 'msg_not_permitted');
					}
				}
				if ($target_module_info)
				{
					$module_info->mid = $target_module_info->mid;
					$module_info->module_srl = $target_module_info->module_srl;
				}
			}
		}
		else
		{
			$this->is_replaceable = TRUE;
		}

		Context::set('mid', $curr_module_info->mid);

		return new Object();
	}

	function _rollbackBeforeModuleInfo(&$oModule)
	{
		$module_info = $this->curr_module_info;
		if (!$module_info)
		{
			return new Object();
		}

		$oModuleModel = getModel('module');
		$oModuleModel->syncSkinInfoToModuleInfo($module_info);
		if (!$this->is_replaceable)
		{
			$module_info->mid = $oModule->mid;
			$module_info->module_srl = $oModule->module_srl;
		}

		$oDocumentModel = getModel('document');
		$oDocument = &$oDocumentModel->getDocument(Context::get('document_srl'));
		if ($oDocument->isExists())
		{
			$oDocument->add('module_srl', $module_info->module_srl);
		}

		$oModule->mid = $module_info->mid;
		$oModule->module_srl = $module_info->module_srl;
		$oModule->module_info = $oModule->origin_module_info = $module_info;
		$oModule->list_count = $module_info->list_count;
		$oModule->search_list_count = $module_info->search_list_count;
		$oModule->page_count = $module_info->page_count;
		$oModule->except_notice = $module_info->except_notice == 'N' ? FALSE : TRUE;

		$status_list = array();
		if (!empty($module_info->use_status))
		{
			$status_name_list = $oDocumentModel->getStatusNameList();
			$use_status = explode('|@|', $module_info->use_status);
			if (is_array($use_status))
			{
				foreach ($use_status as $key => $value)
				{
					$status_list[$value] = $status_name_list[$value];
				}
			}
		}
		if (isset($status_list['SECRET']))
		{
			$oModule->module_info->secret = 'Y';
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($module_info->module_srl);
		$category_list = $oDocumentModel->getCategoryList($module_info->module_srl);
		if ($timeline_info)
		{
			foreach ($timeline_info->attach_info as $item)
			{
				$category_list += $oDocumentModel->getCategoryList($item);
			}
		}
		if (count($category_list))
		{
			if ($module_info->hide_category)
			{
				$oModule->module_info->use_category = ($module_info->hide_category == 'Y') ? 'N' : 'Y';
			}
			else if ($module_info->use_category)
			{
				$oModule->module_info->hide_category = ($module_info->use_category == 'Y') ? 'N' : 'Y';
			}
			else
			{
				$oModule->module_info->hide_category = 'N';
				$oModule->module_info->use_category = 'Y';
			}
		}
		else
		{
			$oModule->module_info->hide_category = 'Y';
			$oModule->module_info->use_category = 'N';
		}

		if ($module_info->consultation == 'Y' && !$oModule->grant->manager)
		{
			$oModule->consultation = TRUE;
			$is_logged = Context::get('is_logged');
			if (!$is_logged)
			{
				$oModule->grant->list = FALSE;
				$oModule->grant->write_document = FALSE;
				$oModule->grant->write_comment = FALSE;
				$oModule->grant->view = FALSE;
			}
		}
		else
		{
			$oModule->consultation = FALSE;
		}

		return new Object();
	}

	function _rollbackAfterModuleInfo(&$oModule)
	{
		$origin_module_info = $this->origin_module_info;
		$oDocument = Context::get('oDocument');
		if ($origin_module_info && $oDocument && $oDocument->isExists())
		{
			$oDocument->add('module_srl', $origin_module_info->module_srl);
			Context::set('oDocument', $oDocument);
		}

		return new Object();
	}

	function _replaceDocumentList(&$oModule)
	{
		$notice_list = Context::get('notice_list');
		$document_list = Context::get('document_list');
		if (!$this->curr_module_info || !$oModule->grant->list || is_null($notice_list) && is_null($document_list))
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($this->curr_module_info->module_srl);
		$attach_info = $timeline_info->attach_info;
		$attach_info[] = $timeline_info->module_srl;

		$args = new stdClass();
		$args->module_srl = $attach_info;
		$args->tl_title = $timeline_info->title;
		$args->tl_content = $timeline_info->content;
		$args->tl_tags = $timeline_info->tags;
		$args->tl_least_date = $oTimelineModel->getLeastDate($timeline_info->module_srl);
		$args->tl_last_date = $oTimelineModel->getLastDate($timeline_info->module_srl);
		$args->page = Context::get('page');
		$args->list_count = $oModule->list_count;
		$args->page_count = $oModule->page_count;
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');

		$module_srl = Context::get('module_srl');
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

	function _replaceCategoryList(&$oModule)
	{
		$category_list = Context::get('category_list');
		if (is_null($category_list) || $oModule->module_info->use_category != 'Y')
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($oModule->module_srl);
		if (!$timeline_info)
		{
			return new Object();
		}

		$oDocumentModel = getModel('document');
		$attach_info = $timeline_info->attach_info;
		foreach ($attach_info as $item)
		{
			$category_list += $oDocumentModel->getCategoryList($item);
		}

		Context::set('category_list', $category_list);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('category_list.', 'category_list.childs.');

		return new Object();
	}

	function _replaceNoticeList(&$oModule)
	{
		$notice_list = Context::get('notice_list');
		$document_list = Context::get('document_list');
		if (!$this->curr_module_info || (is_null($notice_list) && is_null($document_list)))
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($this->curr_module_info->module_srl);
		if ($timeline_info->notice != 'Y')
		{
			return new Object();
		}

		$args = new stdClass();
		$args->module_srl = $timeline_info->attach_info;
		$args->module_srl[] = $timeline_info->module_srl;

		$oDocumentModel = getModel('document');
		$notice_list = $oDocumentModel->getNoticeList($args, $oModule->columnList);
		Context::set('notice_list', $notice_list->data);

		return new Object();
	}
}

/* End of file timeline.controller.php */
/* Location: ./modules/timeline/timeline.controller.php */
