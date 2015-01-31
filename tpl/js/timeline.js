/*! Copyright (C) 2015 Eunsoo Lee. All rights reserved. */

(function (global, $) {
	"use strict";
	var bind = function (target, selector) {
		if (target && selector) {
			$(selector).on("change", function (e) {
				var val = $(this).val();
				location.href = current_url.setQuery(target, val);
			});
		}
	};
	global.timelineSelectModuleSrl = function (selector) {
		bind("module_srl", selector);
	};
	global.timelineSelectCategorySrl = function (selector) {
		bind("category_srl", selector);
	};
})(this, jQuery);

/* End of file */
