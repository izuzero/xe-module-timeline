<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timelineModel
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module model class.
 */

class timelineModel extends timeline
{
	function init()
	{
	}

	/**
	 * @brief 게시글 필터링 통과 여부
	 * @param int $module_srl
	 * @param int $document_srl
	 * @return boolean
	 */
	function isFilterPassed($module_srl, $document_srl)
	{
		$timeline_info = $this->getTimelineInfo($module_srl);
		// 타임라인 게시판이 아닌 경우
		if (!$timeline_info)
		{
			return FALSE;
		}

		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		// 게시글이 없는 경우
		if (!$oDocument->isExists())
		{
			return FALSE;
		}

		$tl_filter = array('readed_count', 'voted_count', 'blamed_count', 'comment_count', 'popular_count');
		foreach ($tl_filter as $filter)
		{
			$key = $timeline_info->{'cond_' . $filter};
			$val = $timeline_info->{$filter};
			if ($filter == 'popular_count')
			{
				$if_val = $oDocument->get('voted_count');
				$if_val += $oDocument->get('blamed_count');
			}
			else
			{
				$if_val = $oDocument->get($filter);
				if ($filter == 'blamed_count')
				{
					$if_val *= -1;
				}
			}
			if ($val && (($key == 'excess' && $val >= $if_val) || ($key == 'below' && $val <= $if_val) || ($key == 'more' && $val > $if_val) || ($key == 'less' && $val < $if_val)))
			{
				// 게시글 필터링을 통과하지 못한 경우
				return FALSE;
			}
		}

		// 기간 필터링을 통과하지 못한 경우
		$least_date = $this->getLeastDate($timeline_info->module_srl);
		$last_date = $this->getLastDate($timeline_info->module_srl);
		$regdate = $oDocument->get('regdate');
		if ($least_date && $regdate < $least_date)
		{
			return FALSE;
		}
		else if ($last_date && $regdate > $last_date)
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * @brief 타임라인 게시판 목록
	 * @description 배열에 들어간 값은 게시판의 module_srl 값임.
	 * @return array
	 */
	function getTimelineList()
	{
		// 메모리에 있는 타임라인 게시판 목록 불러오기
		$timeline_list = $GLOBALS['__timeline__']['timeline_list'];
		// 메모리에 값이 없는 경우
		if (is_null($timeline_list))
		{
			$timeline_list = FALSE;
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			// 캐시에서 타임라인 게시판 목록 불러오기
			if ($oCacheHandler->isSupport())
			{
				$cache_key = $oCacheHandler->getGroupKey('timeline', 'timeline_list');
				$timeline_list = $oCacheHandler->get($cache_key);
			}
			// 캐시가 저장되지 않았거나 캐시를 사용하지 못하는 경우
			if ($timeline_list === FALSE)
			{
				$timeline_list = array();
				$output = executeQueryArray('timeline.getTimelineList');
				foreach ($output->data as $item)
				{
					$timeline_list[] = $item->module_srl;
				}
				// 캐시 저장
				if ($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $timeline_list);
				}
			}
			// 메모리 저장
			$GLOBALS['__timeline__']['timeline_list'] = $timeline_list;
		}

		return $timeline_list;
	}

	/**
	 * @brief 모든 타임라인 게시판 정보 반환
	 * @param array $column_list
	 * @return array
	 */
	function getWholeTimelineInfo($column_list = array())
	{
		$whole_timeline_info = array();
		// 타임라인 게시판 목록을 불러오기
		$timeline_list = $this->getTimelineList();
		// 게시판 목록으로 게시판 정보 불러오기
		foreach ($timeline_list as $item)
		{
			$whole_timeline_info[$item] = $this->getTimelineInfo($item, $column_list);
		}

		return $whole_timeline_info;
	}

	/**
	 * @brief 타임라인 게시판 정보 반환
	 * @param int $module_srl
	 * @param array $column_list
	 * @return object
	 */
	function getTimelineInfo($module_srl, $column_list = array())
	{
		if (!$module_srl)
		{
			return NULL;
		}

		// 캐시 및 메모리 키값을 위한 해시 생성
		$hash_id = md5('module_srl:' . $module_srl);
		// 메모리에서 타임라인 게시판 정보 불러오기
		$timeline_info = $GLOBALS['__timeline__']['timeline_info'][$hash_id];
		// 메모리에 값이 없는 경우
		if (is_null($timeline_info))
		{
			$timeline_info = FALSE;
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			// 캐시에서 타임라인 게시판 정보 불러오기
			if ($oCacheHandler->isSupport())
			{
				$object_key = 'timeline_info:' . $hash_id;
				$cache_key = $oCacheHandler->getGroupKey('timeline', $object_key);
				$timeline_info = $oCacheHandler->get($cache_key);
			}
			// 저장된 캐시가 없거나 캐시를 사용할 수 없는 경우
			if ($timeline_info === FALSE)
			{
				$args = new stdClass();
				$args->module_srl = $module_srl;
				// DB에서 타임라인 게시판 정보 불러오기
				$output = executeQuery('timeline.getTimelineInfo', $args);
				$timeline_info = $output->data;
				// 캐시 저장
				if ($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $timeline_info);
				}
			}
			if ($timeline_info)
			{
				// 기준 날짜값 갱신
				$oTimelineController = getController('timeline');
				$oTimelineController->renewalTimelineInfo($timeline_info);
			}
			// 메모리 저장
			$GLOBALS['__timeline__']['timeline_info'][$hash_id] = $timeline_info;
		}
		if ($timeline_info)
		{
			// 자식 게시판 정보 불러오기
			$timeline_info->attach_info = $this->getAttachInfo($module_srl);
			// 지정한 컬럼만 반환하기 위한 처리
			if (count($column_list))
			{
				$temp = $timeline_info;
				$timeline_info = new stdClass();
				foreach ($temp as $key => $val)
				{
					if (in_array($key, $column_list))
					{
						$timeline_info->{$key} = $val;
					}
				}
			}
		}

		return $timeline_info;
	}

	/**
	 * @brief 자식 게시판 정보 반환
	 * @param int $module_srl
	 * @return array
	 */
	function getAttachInfo($module_srl)
	{
		if (!$module_srl)
		{
			return array();
		}

		// 캐시 및 메모리 키값을 위한 해시 생성
		$hash_id = md5('module_srl:' . $module_srl);
		// 메모리에서 자식 게시판 정보 불러오기
		$attach_info = $GLOBALS['__timeline__']['attach_info'][$hash_id];
		// 메모리에 값이 없는 경우
		if (is_null($attach_info))
		{
			$attach_info = FALSE;
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			// 캐시에서 자식 게시판 정보 불러오기
			if ($oCacheHandler->isSupport())
			{
				$object_key = 'attach_info:' . $hash_id;
				$cache_key = $oCacheHandler->getGroupKey('timeline', $object_key);
				$attach_info = $oCacheHandler->get($cache_key);
			}
			// 저장된 캐시가 없거나 캐시를 사용할 수 없는 경우
			if ($attach_info === FALSE)
			{
				$attach_info = array();
				$args = new stdClass();
				$args->module_srl = $module_srl;
				// DB에서 자식 게시판 정보 불러오기
				$output = executeQueryArray('timeline.getAttachInfo', $args);
				foreach ($output->data as $item)
				{
					$attach_info[] = $item->target_srl;
				}
				// 캐시 저장
				if ($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $attach_info);
				}
			}
			// 메모리 저장
			$GLOBALS['__timeline__']['attach_info'][$hash_id] = $attach_info;
		}

		return $attach_info;
	}

	/**
	 * @brief date string 값을 strtotime 함수에서 사용하기 위한 string 값으로 변환
	 * @param string $format
	 * @param string $sign
	 * @return string
	 */
	function getStrTime($format, $sign = '+')
	{
		$stack = sscanf($format, '%04d%02d%02d%02d%02d%02d');
		if (!$stack)
		{
			return NULL;
		}

		list($year, $month, $day, $hour, $minute, $second) = $stack;

		$str_time = sprintf(
			'%s%d years %s%d months %s%d days %s%d hours %s%d minutes %s%d seconds',
			$sign, $year, $sign, $month, $sign, $day, $sign, $hour, $sign, $minute, $sign, $second
		);

		return $str_time;
	}

	/**
	 * @brief 타임라인 게시판 정보에서 최소 등록일 반환
	 * @param int $module_srl
	 * @return mixed
	 */
	function getLeastDate($module_srl)
	{
		$timeline_info = $this->getTimelineInfo($module_srl);
		// 타임라인 게시판 정보가 없는 경우
		if (!$timeline_info)
		{
			return NULL;
		}

		$least_date = NULL;
		$standard_date = $timeline_info->standard_date;
		$limit_date = $this->getStrTime($timeline_info->limit_date, '-');
		// 기준 날짜가 있는 경우
		if ($standard_date)
		{
			// 최소 등록일을 기준 날짜로 결정
			$least_date = $standard_date;
		}
		// 기준 날짜는 없으나 시간 범위가 있는 경우
		else if (isset($limit_date))
		{
			// 최소 등록일을 현재 시간으로부터 시간 범위만큼의 이전 시간으로 결정
			$least_date = date('YmdHis', strtotime($limit_date));
		}

		return $least_date;
	}

	/**
	 * @brief 타임라인 게시판 정보에서 최대 등록일 반환
	 * @param $module_srl
	 * @return mixed
	 */
	function getLastDate($module_srl)
	{
		$timeline_info = $this->getTimelineInfo($module_srl);
		// 타임라인 게시판 정보가 없는 경우
		if (!$timeline_info)
		{
			return NULL;
		}

		$last_date = NULL;
		$standard_date = strtotime($timeline_info->standard_date);
		$limit_date = $this->getStrTime($timeline_info->limit_date);
		// 기준 날짜가 잘못된 날짜 형식이 아니고 시간 범위가 있는 경우
		if ($standard_date !== FALSE && isset($limit_date))
		{
			// 최대 등록일을 기준 날짜로부터 시간 범위만큼의 이후 시간으로 결정
			$last_date = date('YmdHis', strtotime($limit_date, $standard_date));
		}

		return $last_date;
	}

	/**
	 * @brief 게시글 목록 반환
	 * @description documentModel::getDocumentList 함수를 타임라인 모듈에 맞게 변환
	 * @param object $obj
	 * @param boolean $except_notice
	 * @param boolean $load_extra_vars
	 * @param array $column_list
	 * @return object
	 */
	function getDocumentList($obj, $except_notice = FALSE, $load_extra_vars = TRUE, $column_list = array())
	{
		$oDocumentModel = getModel('document');
		$sort_check = $oDocumentModel->_setSortIndex($obj, $load_extra_vars);
		$obj->sort_index = $sort_check->sort_index;
		$obj->isExtraVars = $sort_check->isExtraVars;
		$this->_setSearchOption($obj, $args, $query_id, $use_division);
		if ($sort_check->isExtraVars)
		{
			$output = executeQueryArray($query_id, $args);
		}
		else
		{
			$group_by_query = array('timeline.getDocumentListWithinComment', 'timeline.getDocumentListWithinTag', 'timeline.getDocumentListWithinExtraVars');
			if (in_array($query_id, $group_by_query))
			{
				$group_args = clone($args);
				$group_args->sort_index = 'documents.' . $args->sort_index;
				$output = executeQueryArray($query_id, $group_args);
				if (!($output->toBool() && count($output->data)))
				{
					return $output;
				}
				foreach ($output->data as $key => $val)
				{
					if ($val->document_srl)
					{
						$target_srls[] = $val->document_srl;
					}
				}

				$page_navigation = $output->page_navigation;
				$keys = array_keys($output->data);
				$virtual_number = $keys[0];

				$target_args = new stdClass();
				$target_args->document_srls = implode(',', $target_srls);
				$target_args->list_order = $args->sort_index;
				$target_args->order_type = $args->order_type;
				$target_args->list_count = $args->list_count;
				$target_args->page = 1;
				$output = executeQueryArray('document.getDocuments', $target_args);
				$output->page_navigation = $page_navigation;
				$output->total_count = $page_navigation->total_count;
				$output->total_page = $page_navigation->total_page;
				$output->page = $page_navigation->cur_page;
			}
			else
			{
				$output = executeQueryArray($query_id, $args, $column_list);
			}
		}
		if (!($output->toBool() && count($output->data)))
		{
			return $output;
		}

		$idx = 0;
		$data = $output->data;
		unset($output->data);
		if (is_null($virtual_number))
		{
			$keys = array_keys($data);
			$virtual_number = $keys[0];
		}
		if ($except_notice)
		{
			foreach ($data as $key => $attribute)
			{
				if ($attribute->is_notice == 'Y')
				{
					$virtual_number--;
				}
			}
		}
		foreach ($data as $key => $attribute)
		{
			if ($except_notice && $attribute->is_notice == 'Y')
			{
				continue;
			}

			$document_srl = $attribute->document_srl;
			if (!$GLOBALS['XE_DOCUMENT_LIST'][$document_srl])
			{
				$oDocument = new documentItem();
				$oDocument->setAttribute($attribute, FALSE);
				if ($is_admin)
				{
					$oDocument->setGrant();
				}

				$GLOBALS['XE_DOCUMENT_LIST'][$document_srl] = $oDocument;
			}

			$output->data[$virtual_number] = $GLOBALS['XE_DOCUMENT_LIST'][$document_srl];
			$virtual_number--;
		}
		if ($load_extra_vars)
		{
			$oDocumentModel->setToAllDocumentExtraVars();
		}
		if (is_array($output->data) && count($output->data))
		{
			foreach ($output->data as $number => $document)
			{
				$output->data[$number] = $GLOBALS['XE_DOCUMENT_LIST'][$document->document_srl];
			}
		}
		else
		{
			$output->data = array();
		}

		return $output;
	}

	/**
	 * @brief 게시글이 있는 페이지 번호 반환
	 * @description documentModel::getDocumentPage 함수를 타임라인 모듈에 맞게 변환
	 * @param object $oDocument
	 * @param object $opts
	 * @return int
	 */
	function getDocumentPage($oDocument, $opts)
	{
		$oDocumentModel = getModel('document');
		$sort_check = $oDocumentModel->_setSortIndex($opts, TRUE);
		$opts->sort_index = $sort_check->sort_index;
		$opts->isExtraVars = $sort_check->isExtraVars;
		$this->_setSearchOption($opts, $args, $query_id, $use_division);
		if ($sort_check->isExtraVars)
		{
			return 1;
		}
		else
		{
			if ($sort_check->sort_index === 'list_order' || $sort_check->sort_index === 'update_order')
			{
				if ($args->order_type === 'desc')
				{
					$args->{'rev_' . $sort_check->sort_index} = $oDocument->get($sort_check->sort_index);
				}
				else
				{
					$args->{$sort_check->sort_index} = $oDocument->get($sort_check->sort_index);
				}
			}
			else
			{
				return 1;
			}
		}

		$output = executeQuery($query_id . 'Page', $args);
		$count = $output->data->count;
		$page = intval(($count - 1) / $opts->list_count) + 1;
		return $page;
	}

	/**
	 * @brief 검색 옵션 설정
	 * @description documentModel::_setSearchOption 함수를 타임라인 모듈에 맞게 변환
	 * @param object $opts
	 * @param object $args
	 * @param string $query_id
	 * @param boolean $use_division
	 * @return void
	 */
	function _setSearchOption($opts, &$args, &$query_id, &$use_division)
	{
		$oDocumentModel = getModel('document');
		$args = new stdClass();
		$args->member_srl = $opts->member_srl;
		$args->category_srl = $opts->category_srl ? $opts->category_srl : NULL;
		$args->tl_title = $opts->tl_title ? $opts->tl_title : NULL;
		$args->tl_content = $opts->tl_content ? $opts->tl_content : NULL;
		$args->tl_tags = $opts->tl_tags ? $opts->tl_tags : NULL;
		$args->tl_least_date = $opts->tl_least_date ? $opts->tl_least_date : NULL;
		$args->tl_last_date = $opts->tl_last_date ? $opts->tl_last_date : NULL;
		$args->start_date = $opts->start_date ? $opts->start_date : NULL;
		$args->end_date = $opts->end_date ? $opts->end_date : NULL;
		$args->page = $opts->page ? $opts->page : 1;
		$args->list_count = $opts->list_count ? $opts->list_count : 20;
		$args->page_count = $opts->page_count ? $opts->page_count : 10;
		$args->order_type = $opts->order_type;
		$args->sort_index = $opts->sort_index;

		$tl_filter = array('readed_count', 'voted_count', 'blamed_count', 'comment_count', 'popular_count');
		$tl_operation = array('excess', 'below', 'more', 'less');
		foreach ($tl_filter as $filter)
		{
			foreach ($tl_operation as $operation)
			{
				$key = 'tl_' . $operation . '_' . $filter;
				if ($opts->{$key})
				{
					$args->{$key} = intval($opts->{$key});
					if ($filter == 'blamed_count')
					{
						$args->{$key} = $args->{$key} * -1;
					}
				}
			}
		}

		$order_type = array();
		if (!in_array($args->order_type, array('desc', 'asc')))
		{
			$args->order_type = 'asc';
		}
		if (is_array($opts->module_srl))
		{
			$module_srls = $opts->module_srl;
			$args->module_srl = implode(',', $opts->module_srl);
		}
		else
		{
			$module_srls = array(intval($opts->module_srl));
			$args->module_srl = $opts->module_srl;
		}
		if (is_array($opts->exclude_module_srl))
		{
			$args->exclude_module_srl = implode(',', $opts->exclude_module_srl);
		}
		else
		{
			$args->exclude_module_srl = $opts->exclude_module_srl;
		}
		if ($opts->statusList)
		{
			$args->status_list = $opts->statusList;
		}
		else
		{
			$logged_info = Context::get('logged_info');
			$status_list = array();
			$status_list[] = $oDocumentModel->getConfigStatus('secret');
			$status_list[] = $oDocumentModel->getConfigStatus('public');
			if ($logged_info->is_admin == 'Y' && !$opts->module_srl)
			{
				$status_list[] = $oDocumentModel->getConfigStatus('temp');
			}

			$args->status_list = $status_list;
		}
		if ($args->category_srl)
		{
			$category_list = array();
			foreach ($module_srls as $item)
			{
				$category_list += $oDocumentModel->getCategoryList($item);
			}

			$category_info = $category_list[$args->category_srl];
			$category_info->childs[] = $args->category_srl;
			$args->category_srl = implode(',', $category_info->childs);
		}

		$query_id = 'timeline.getDocumentList';
		$use_division = FALSE;
		$search_target = $opts->search_target;
		$search_keyword = $opts->search_keyword;
		if ($search_target && $search_keyword)
		{
			switch ($search_target)
			{
				case 'title':
				case 'content':
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->{'s_' . $search_target} = $search_keyword;
					$use_division = TRUE;
					break;

				case 'title_content':
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->s_title = $search_keyword;
					$args->s_content = $search_keyword;
					$use_division = TRUE;
					break;

				case 'user_id':
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->s_user_id = $search_keyword;
					$args->sort_index = 'documents.' . $args->sort_index;
					break;

				case 'user_name':
				case 'nick_name':
				case 'email_address':
				case 'homepage':
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->{'s_' . $search_target} = $search_keyword;
					break;

				case 'is_notice':
					if ($search_keyword == 'N')
					{
						$args->{'s_' . $search_target} = 'N';
					}
					else if ($search_keyword == 'Y')
					{
						$args->{'s_' . $search_target} = 'Y';
					}
					else
					{
						$args->{'s_' . $search_target} = '';
					}
					break;

				case 'is_secret':
					if ($search_keyword == 'N')
					{
						$args->status_list = array($oDocumentModel->getConfigStatus('public'));
					}
					else if ($search_keyword == 'Y')
					{
						$args->status_list = array($oDocumentModel->getConfigStatus('secret'));
					}
					else if ($search_keyword == 'temp')
					{
						$args->status_list = array($oDocumentModel->getConfigStatus('temp'));
					}
					break;

				case 'member_srl':
				case 'readed_count':
				case 'voted_count':
				case 'comment_count':
				case 'trackback_count':
				case 'uploaded_count':
					$args->{'s_' . $search_target} = intval($search_keyword);
					break;

				case 'blamed_count':
					$args->{'s_' . $search_target} = intval($search_keyword) * -1;
					break;

				case 'regdate':
				case 'last_update':
				case 'ipaddress':
					$args->{'s_' . $search_target} = $search_keyword;
					break;

				case 'comment':
					$args->s_comment = $search_keyword;
					$query_id = 'timeline.getDocumentListWithinComment';
					$use_division = TRUE;
					break;

				case 'tag':
					$args->s_tags = str_replace(' ', '%', $search_keyword);
					$query_id = 'timeline.getDocumentListWithinTag';
					break;

				case 'extra_vars':
					$args->var_value = str_replace(' ', '%', $search_keyword);
					$query_id = 'timeline.getDocumentListWithinExtraVars';
					break;

				default:
					if (strpos($search_target, 'extra_vars') !== FALSE)
					{
						$args->var_idx = substr($search_target, strlen('extra_vars'));
						$args->var_value = str_replace(' ', '%', $search_keyword);
						$args->sort_index = 'documents.' . $args->sort_index;
						$query_id = 'timeline.getDocumentListWithExtraVars';
					}
					break;
			}
		}
		if ($opts->isExtraVars)
		{
			$query_id = 'timeline.getDocumentListExtraSort';
		}
		else
		{
			if ($args->sort_index != 'list_order' || $args->order_type != 'asc')
			{
				$use_division = FALSE;
			}
			if ($use_division)
			{
				$division = intval(Context::get('division'));
				if ($args->sort_index == 'list_order' && ($args->exclude_module_srl === '0' || count(explode(',', $args->module_srl)) > 5))
				{
					$listSqlID = 'timeline.getDocumentListUseIndex';
					$divisionSqlID = 'timeline.getDocumentDivisionUseIndex';
				}
				else
				{
					$listSqlID = 'timeline.getDocumentList';
					$divisionSqlID = 'timeline.getDocumentDivision';
				}
				if (!$division)
				{
					$division_args = clone($args);
					$division_args->list_count = 1;
					$output = executeQuery($divisionSqlID, $division_args, array('list_order'));
					if ($output->data)
					{
						$item = array_pop($output->data);
						$division = $item->list_order;
					}

					$division_args = NULL;
				}

				$last_division = intval(Context::get('last_division'));
				if (!$last_division)
				{
					$last_division_args = clone($args);
					$last_division_args->list_count = 1;
					$last_division_args->page = 5001;
					$output = executeQuery($divisionSqlID, $last_division_args, array('list_order'));
					if($output->data)
					{
						$item = array_pop($output->data);
						$last_division = $item->list_order;
					}
				}
				if ($last_division)
				{
					$last_division_args = clone($args);
					$last_division_args->list_order = $last_division;
					$output = executeQuery('timeline.getDocumentDivisionCount', $last_division_args);
					if ($output->data->count < 1)
					{
						$last_division = NULL;
					}
				}

				$args->division = $division;
				$args->last_division = $last_division;
				Context::set('division', $division);
				Context::set('last_division', $last_division);
			}
		}
	}
}

/* End of file timeline.model.php */
/* Location: ./modules/timeline/timeline.model.php */
