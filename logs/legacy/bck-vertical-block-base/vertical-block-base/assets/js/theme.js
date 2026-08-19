(function () {
	'use strict';

	if (window.VerticalBlockBase && window.VerticalBlockBase.activeVertical) {
		document.documentElement.setAttribute('data-vbb-vertical', window.VerticalBlockBase.activeVertical);
	}
})();
