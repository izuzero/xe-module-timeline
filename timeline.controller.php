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

	/**
	 * @brief 타임라인 게시판 등록
	 * @param object $args ($args->module_srl 값은 필수)
	 * @return object
	 */
	function insertTimelineInfo($args)
	{
		// 인자 유효성 검증
		if (!(is_object($args) && $args->module_srl))
		{
			return new Object(-1, 'msg_timeline_no_module_srl');
		}

		$oDB = DB::getInstance();
		$oDB->begin();
		// DB에 남아 있는 타임라인 게시판 정보 삭제
		$output = $this->deleteTimelineInfo($args->module_srl);
		if (!$output->toBool())
		{
			// DB 접근에 문제가 생겼을 경우 롤백
			$oDB->rollback();
			return $output;
		}

		// 타임라인 게시판 등록
		$output = executeQuery('timeline.insertTimelineInfo', $args);
		if ($output->toBool())
		{
			$oDB->commit();
			// 메모리에서 타임라인 게시판 정보 삭제
			unset($GLOBALS['__timeline__']['timeline_list']);
			unset($GLOBALS['__timeline__']['timeline_info']);
			// 타임라인 모듈 캐시 삭제
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('timeline');
			}
		}
		else
		{
			// DB 접근에 문제가 생겼을 경우 롤백
			$oDB->rollback();
		}

		return $output;
	}

	/**
	 * @brief 타임라인 게시판의 자식 게시판 등록
	 * @param int $module_srl
	 * @param array $target_srls
	 * @return object
	 */
	function insertAttachInfo($module_srl, $target_srls = array())
	{
		// 인자 유효성 검증
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
		// DB에 남아 있는 자식 게시판 정보 삭제
		$output = $this->deleteAttachInfo($module_srl);
		if (!$output->toBool())
		{
			// DB 접근에 문제가 생겼을 경우 롤백
			$oDB->rollback();
			return $output;
		}

		$args = new stdClass();
		$args->module_srl = $module_srl;
		$args->priority = 0;
		// 배열로 입력 받은 target_srl 값을 하나씩 등록
		foreach ($target_srls as $target_srl)
		{
			$args->target_srl = $target_srl;
			$args->priority++;
			$output = executeQuery('timeline.insertAttachInfo', $args);
			if (!$output->toBool())
			{
				// DB 접근에 문제가 생겼을 경우 롤백
				$oDB->rollback();
				return $output;
			}
		}

		$oDB->commit();
		return new Object();
	}

	/**
	 * @brief 타임라인 게시판 정보 삭제
	 * @param int $module_srl
	 * @return object
	 */
	function deleteTimelineInfo($module_srl)
	{
		$args = new stdClass();
		$args->module_srl = $module_srl;
		// DB에서 타임라인 게시판 정보 삭제
		$output = executeQuery('timeline.deleteTimelineInfo', $args);
		if ($output->toBool())
		{
			// 메모리에서 타임라인 게시판 정보 삭제
			unset($GLOBALS['__timeline__']['timeline_list']);
			unset($GLOBALS['__timeline__']['timeline_info']);
			// 타임라인 모듈 캐시 삭제
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('timeline');
			}
		}

		return $output;
	}

	/**
	 * @brief 타임라인 게시판의 자식 게시판 삭제
	 * @param int $module_srl
	 * @return object
	 */
	function deleteAttachInfo($module_srl)
	{
		$args = new stdClass();
		$args->module_srl = $module_srl;
		// DB에서 자식 게시판 정보 삭제
		$output = executeQuery('timeline.deleteAttachInfo', $args);
		if ($output->toBool())
		{
			// 메모리에서 자식 게시판 정보 삭제
			unset($GLOBALS['__timeline__']['attach_info']);
			// 타임라인 모듈 캐시 삭제
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('timeline');
			}
		}

		return $output;
	}

	/**
	 * @brief 타임라인 게시판 정보에서 기준 날짜 자동 갱신
	 * @param object $timeline_info
	 * @return object
	 */
	function renewalTimelineInfo(&$timeline_info)
	{
		$oTimelineModel = getModel('timeline');
		$standard_date = strtotime($timeline_info->standard_date);
		$limit_date = $oTimelineModel->getStrTime($timeline_info->limit_date);
		// 기준 날짜가 잘못된 날짜 형식이거나 시간 범위가 없거나 자동 갱신을 사용하지 않을 경우
		if ($standard_date === FALSE || is_null($limit_date) || $timeline_info->auto_renewal != 'Y')
		{
			return $timeline_info;
		}

		// 현재 시간
		$now_date = time();
		// 기준 날짜와 시간 범위간의 차이
		$diff_date = strtotime($limit_date, $standard_date) - $standard_date;
		// 갱신해야 할 횟수 계산
		$repeat = floor(($now_date - $standard_date) / $diff_date);
		if (!$repeat)
		{
			return $timeline_info;
		}

		// 기준 날짜에 차이값을 갱신해야 할 횟수만큼 곱하고 더해서 새로운 기준 날짜 계산
		$last_date = $standard_date + ($diff_date * $repeat);
		$timeline_info->standard_date = date('YmdHis', $last_date);
		$this->insertTimelineInfo($timeline_info);

		return $timeline_info;
	}

	/**
	 * @brief 타임라인 게시판 정보를 템플릿으로 넘겨주는 트리거
	 * @param object $module_info
	 * @return object
	 */
	function _setTimelineInfo(&$module_info)
	{
		// 타임라인 게시판 정보
		$curr_module_info = $this->curr_module_info;
		// 타임라인 게시판 정보가 없을 경우
		if (!$curr_module_info)
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($curr_module_info->module_srl);
		// 자식 게시판 정보
		$attach_info = $timeline_info->attach_info;
		$attach_info[] = $timeline_info->module_srl;

		$oModuleModel = getModel('module');
		// 자식 게시판들의 모듈 정보 구하기
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

		// 템플릿으로 값 넘겨주기
		Context::set('timeline_info', $timeline_info);
		Context::set('modules_info', $modules_info);

		return new Object();
	}

	/**
	 * @brief module id replace 회피를 위한 트리거
	 * @description document_srl 상의 mid와 주소 상의 mid가 다를 경우 발생하는 문제 해결
	 * @param object $oModule
	 * @return object
	 */
	function _replaceMid(&$oModule)
	{
		$mid = $oModule->mid;
		$module = $oModule->module;
		$document_srl = $oModule->document_srl;
		$site_module_info = Context::get('site_module_info');
		$oModuleModel = getModel('module');
		if ($mid)
		{
			$curr_module_info = $oModuleModel->getModuleInfoByMid($mid, $site_module_info->site_srl);
		}
		else if (!$module && !$document_srl)
		{
			$curr_module_info = $site_module_info;
		}
		if (!$curr_module_info)
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($curr_module_info->module_srl);
		// 타임라인 게시판이 아닌 경우
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
			// 자식 게시판에 등록되어 있는 게시판의 공지글이지만 공지 게시글 통합 기능을 사용하지 않는 경우
			$attach_info = $timeline_info->attach_info;
			if (in_array($module_srl, $attach_info) && $oDocument->get('is_notice') == 'Y' && $timeline_info->notice != 'Y')
			{
				return new Object();
			}
			// 타임라인 게시판에 표시될 수 있는 게시글이면서 공지글이거나 게시글 필터링을 통과했을 경우
			$attach_info[] = $timeline_info->module_srl;
			if (in_array($module_srl, $attach_info) && ($oDocument->get('is_notice') == 'Y' || $oTimelineModel->isFilterPassed($timeline_info->module_srl, $document_srl)))
			{
				$origin_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			}
		}

		// 현재 모듈 정보와 게시글의 모듈 정보를 저장
		$this->curr_module_info = $curr_module_info;
		$this->origin_module_info = $origin_module_info;

		// 타임라인 모듈이 동작하는 경우
		if ($origin_module_info && !isCrawler())
		{
			// 원래 게시판으로 이동 기능을 사용할 경우
			if ($timeline_info->replace == 'Y')
			{
				// 페이지 값 초기화
				Context::set('page', NULL);
			}
			else
			{
				// module id replace 회피
				Context::set('mid', $oModule->mid = $origin_module_info->mid);
			}
		}

		return new Object();
	}

	/**
	 * @brief 모듈 정보를 후킹하는 트리거
	 * @param object $module_info
	 * @return object
	 */
	function _replaceModuleInfo(&$module_info)
	{
		// 타임라인 게시판 정보가 없을 경우
		$curr_module_info = $this->curr_module_info;
		if (!$curr_module_info)
		{
			return new Object();
		}

		// 현재 모듈 정보를 다른 변수에 저장
		$origin_module_info = clone($module_info);
		// 현재 모듈 정보를 타임라인 게시판 정보로 교체
		$module_info = clone($curr_module_info);
		// 게시글 모듈 정보가 있을 경우
		if ($origin_module_info)
		{
			// 현재 모듈 정보를 게시글 모듈 정보와 동기화
			$module_info->mid = $origin_module_info->mid;
			$module_info->module_srl = $origin_module_info->module_srl;
			$module_info->use_status = $origin_module_info->use_status;
			$module_info->use_anonymous = $origin_module_info->use_anonymous;
			$module_info->protect_content = $origin_module_info->protect_content;
		}

		$act = Context::get('act');
		$exception = array('dispBoardWrite', 'procBoardInsertDocument', 'procBoardInsertComment');
		// 게시글 작성 화면, 게시글 등록, 댓글 등록에 대한 예외 처리
		if (in_array($act, $exception))
		{
			// 모듈 정보를 교체하지 않음
			$this->is_replaceable = FALSE;

			$oModuleModel = getModel('module');
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument(Context::get('document_srl'));
			// 게시글이 있는 경우 (게시글 수정, 댓글 등록, 댓글 수정)
			if ($oDocument->isExists())
			{
				$oCommentModel = getModel('comment');
				$oComment = $oCommentModel->getComment(Context::get('comment_srl'));
				// 댓글이 있는 경우 (댓글 수정)
				if ($oComment->isExists())
				{
					// 댓글 정보 유효성 검사
					$document_srl = $oDocument->get('document_srl');
					$target_document_srl = $oComment->get('document_srl');
					if ($document_srl == $target_document_srl)
					{
						$module_srl = $oComment->get('module_srl');
						$target_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
					}
					else
					{
						return new Object(-1, 'msg_invalid_request');
					}
				}
				// 댓글이 없는 경우 (게시글 수정, 댓글 등록)
				else
				{
					$module_srl = $oDocument->get('module_srl');
					$target_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
				}
			}
			// 게시글이 없는 경우 (게시글 등록)
			else
			{
				$oTimelineModel = getModel('timeline');
				$timeline_info = $oTimelineModel->getTimelineInfo($curr_module_info->module_srl);
				$module_srl = Context::get('module_srl');
				$category_srl = Context::get('category_srl');
				// 카테고리 번호를 입력 받았을 경우
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
					// 현재 타임라인 게시판에서 사용할 수 없는 카테고리거나 없는 카테고리인 경우
					if (!$category)
					{
						return new Object(-1, 'msg_not_permitted');
					}
					// 카테고리 번호에 맞는 모듈 정보 불러오기
					$target_module_info = $oModuleModel->getModuleInfoByModuleSrl($category->module_srl);
				}
				// 모듈 번호를 입력 받았을 경우
				else if ($module_srl)
				{
					$attach_info = $timeline_info->attach_info;
					$attach_info[] = $timeline_info->module_srl;
					// 모듈 번호가 자식 게시판으로 등록되어 있는 경우
					if (in_array($module_srl, $attach_info))
					{
						// 모듈 번호에 맞는 모듈 정보 불러오기
						$target_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
					}
					else
					{
						return new Object(-1, 'msg_not_permitted');
					}
				}
			}
			// 모듈 정보를 교체해야 하는 경우
			if ($target_module_info)
			{
				// 모듈 정보 교체
				$module_info->mid = $target_module_info->mid;
				$module_info->module_srl = $target_module_info->module_srl;
				$module_info->use_status = $target_module_info->use_status;
				$module_info->use_anonymous = $target_module_info->use_anonymous;
				$module_info->protect_content = $target_module_info->protect_content;
			}
		}
		else
		{
			// 모듈 정보를 교체함
			$this->is_replaceable = TRUE;
		}

		// module id replace를 위해 바꿔 놓았던 값을 돌려놓기
		Context::set('mid', $curr_module_info->mid);

		return new Object();
	}

	/**
	 * @brief 후킹한 모듈 정보를 되돌리는 트리거
	 * @param object $oModule
	 * @return object
	 */
	function _rollbackBeforeModuleInfo(&$oModule)
	{
		// 타임라인 게시판일 경우
		if ($this->curr_module_info)
		{
			// 모듈 정보를 타임라인 게시판 정보로 동기화
			$module_info = clone($this->curr_module_info);
			$module_info->use_status = $oModule->module_info->use_status;
			$module_info->use_anonymous = $oModule->module_info->use_anonymous;
			$module_info->protect_content = $oModule->module_info->protect_content;
			$module_info->secret = $oModule->module_info->secret;
		}
		else
		{
			return new Object();
		}

		$oModuleModel = getModel('module');
		// 모듈 정보에 스킨 정보 동기화
		$oModuleModel->syncSkinInfoToModuleInfo($module_info);
		// 모듈 정보를 교체하지 말아야 할 경우
		if (!$this->is_replaceable)
		{
			// 모듈 정보 롤백
			$module_info->mid = $oModule->mid;
			$module_info->module_srl = $oModule->module_srl;
		}

		$oDocumentModel = getModel('document');
		$oDocument = &$oDocumentModel->getDocument(Context::get('document_srl'));
		// 게시글 정보가 있는 경우
		if ($oDocument->isExists())
		{
			/**
			 * module_srl 값을 현재 모듈 정보로 동기화
			 * 동기화하지 않을 경우 게시판 모듈에서 오류가 발생함
			 */
			$oDocument->add('module_srl', $module_info->module_srl);
		}

		// 바뀐 모듈 정보로 게시판 설정 동기화
		$oModule->mid = $module_info->mid;
		$oModule->module_srl = $module_info->module_srl;
		$oModule->module_info = $oModule->origin_module_info = $module_info;
		$oModule->list_count = $module_info->list_count;
		$oModule->search_list_count = $module_info->search_list_count;
		$oModule->page_count = $module_info->page_count;
		$oModule->except_notice = $module_info->except_notice == 'N' ? FALSE : TRUE;

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

		$logged_info = Context::get('logged_info');
		$grant = $oModuleModel->getGrant($module_info, $logged_info);
		if ($timeline_info && $timeline_info->write == 'Y' && $oModule->act == 'dispBoardWrite')
		{
			$grant->write_document = TRUE;
		}
		if ($module_info->consultation == 'Y' && !$grant->manager)
		{
			$oModule->consultation = TRUE;
			if (!Context::get('is_logged'))
			{
				$grant->list = FALSE;
				$grant->write_document = FALSE;
				$grant->write_comment = FALSE;
				$grant->view = FALSE;
			}
		}
		else
		{
			$oModule->consultation = FALSE;
		}

		$oModule->grant = $grant;

		return new Object();
	}

	/**
	 * @brief 모듈 정보를 원래대로 돌려놓는 트리거
	 * @param object $oModule
	 * @return object
	 */
	function _rollbackAfterModuleInfo(&$oModule)
	{
		$origin_module_info = $this->origin_module_info;
		$oDocument = Context::get('oDocument');
		// 게시글 모듈 정보가 있고 템플릿으로 넘겨준 게시글 정보가 있는 경우
		if ($origin_module_info && $oDocument && $oDocument->isExists())
		{
			$oDocument->add('module_srl', $origin_module_info->module_srl);
			Context::set('oDocument', $oDocument);
		}

		return new Object();
	}

	/**
	 * @brief 타임라인 게시판 게시글 교체
	 * @param object $oModule
	 * @return object
	 */
	function _replaceDocumentList(&$oModule)
	{
		$notice_list = Context::get('notice_list');
		$document_list = Context::get('document_list');
		// 타임라인 게시판 정보가 없거나, 게시글을 바꿔치기할 act가 아니거나, 게시글 보기 권한이 없거나, 공지 목록이 없고 게시글 목록이 없는 경우
		if (!$this->curr_module_info || $oModule->act != 'dispBoardContent' || !$oModule->grant->list || is_null($notice_list) && is_null($document_list))
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($this->curr_module_info->module_srl);
		$attach_info = $timeline_info->attach_info;
		$attach_info[] = $timeline_info->module_srl;

		// 게시글 목록 불러오기
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

		$tl_filter = array('readed_count', 'voted_count', 'blamed_count', 'comment_count', 'popular_count');
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

	/**
	 * @brief 타임라인 게시판 카테고리 목록 교체
	 * @param object $oModule
	 * @return object
	 */
	function _replaceCategoryList(&$oModule)
	{
		// 타임라인 게시판 정보가 없거나 카테고리를 사용하지 않는 게시판일 경우
		if (!$this->curr_module_info || $oModule->module_info->use_category != 'Y')
		{
			return new Object();
		}

		$oDocumentModel = getModel('document');
		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($this->curr_module_info->module_srl);
		$attach_info = $timeline_info->attach_info;
		$attach_info[] = $timeline_info->module_srl;
		$category_list = array();
		// 카테고리 목록 불러오기
		foreach ($attach_info as $item)
		{
			$category_list += $oDocumentModel->getCategoryList($item);
		}

		// 카테고리 목록을 템플릿으로 넘겨주기
		Context::set('category_list', $category_list);
		$oSecurity = new Security();
		$oSecurity->encodeHTML('category_list.', 'category_list.childs.');

		return new Object();
	}

	/**
	 * @brief 타임라인 게시판 공지 목록 교체
	 * @param object $oModule
	 * @return object
	 */
	function _replaceNoticeList(&$oModule)
	{
		$notice_list = Context::get('notice_list');
		$document_list = Context::get('document_list');
		// 타임라인 게시판 정보가 없거나 공지 목록이 없고 게시글 목록이 없는 경우
		if (!$this->curr_module_info || (is_null($notice_list) && is_null($document_list)))
		{
			return new Object();
		}

		$oTimelineModel = getModel('timeline');
		$timeline_info = $oTimelineModel->getTimelineInfo($this->curr_module_info->module_srl);
		// 공지 게시글 통합 기능을 사용하지 않는 경우
		if ($timeline_info->notice != 'Y')
		{
			return new Object();
		}

		$args = new stdClass();
		$args->module_srl = $timeline_info->attach_info;
		$args->module_srl[] = $timeline_info->module_srl;

		$oDocumentModel = getModel('document');
		// 공지 게시글 불러오기
		$notice_list = $oDocumentModel->getNoticeList($args, $oModule->columnList);
		Context::set('notice_list', $notice_list->data);

		return new Object();
	}
}

/* End of file timeline.controller.php */
/* Location: ./modules/timeline/timeline.controller.php */
