<?php defined('SYNCSYSTEM') || die('No direct script access.');

SYNC::$CONFIG['IGNORE_FILE_LIST'] = array(
	'.git*',
	'*.md',
	'*.markdown',
	'Thumbs.db',
	'*.patch',
);