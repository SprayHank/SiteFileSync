<?php defined('SYNCSYSTEM') || die('No direct script access.');

Sync::$CONFIG['IGNORE_FILE_LIST'] = array(
	'.git*',
	'*.md',
	'*.markdown',
	'Thumbs.db',
	'*.patch',
);