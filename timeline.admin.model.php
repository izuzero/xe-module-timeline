<?php
/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */
/**
 * @class  timelineAdminModel
 * @author Eunsoo Lee (contact@isizu.co.kr)
 * @brief  Timeline module admin model class.
 */

class timelineAdminModel extends timeline
{
	function init()
	{
	}

	function getPageHandler($args = array(), $page = 1, $page_count = 10, $list_count = 20)
	{
		$page = (int)$page;
		$page_count = (int)$page_count;
		$list_count = (int)$list_count;
		if (!$page)
		{
			$page = 1;
		}
		if (!$page_count)
		{
			$page_count = 10;
		}
		if (!$list_count)
		{
			$list_count = 20;
		}

		$total_count = count($args);
		$total_page = $total_count ? (int)(($total_count - 1) / $list_count) + 1 : 1;

		$output = new Object();
		$output->total_count = $total_count;
		$output->total_page = $total_page;
		$output->page = $page;
		$output->page_navigation = new PageHandler($total_count, $total_page, $page, $page_count);
		$output->data = $page > $total_page ? array() : array_slice($args, ($page - 1) * $list_count, $list_count);

		return $output;
	}
}

/* End of file timeline.admin.model.php */
/* Location: ./modules/timeline/timeline.admin.model.php */
