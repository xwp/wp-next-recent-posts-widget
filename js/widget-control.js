/* global wp, module */
/* eslint consistent-this: [ "error", "form" ] */
/* eslint no-magic-numbers: [ "error", {"ignore":[0,1]} ] */
/* eslint-disable strict */
/* eslint-disable complexity */

wp.widgets.formConstructor['next-recent-posts'] = (function() {
	'use strict';

	var NextRecentPostsForm;

	/**
	 * Next Recent Posts Widget Form.
	 *
	 * This is empty because it can re-use all of the base class's functionality.
	 *
	 * @constructor
	 */
	NextRecentPostsForm = wp.widgets.Form.extend( {
		id_base: 'next-recent-posts'
	} );

	if ( 'undefined' !== typeof module ) {
		module.exports = NextRecentPostsForm;
	}
	return NextRecentPostsForm;

})();
