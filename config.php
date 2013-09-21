<?php defined('SYNCSYSTEM') || die('No direct script access.');

Sync::$CONFIG['IGNORE_FILE_LIST'] = array(
	'.git*',
	'*.md',
	'*.markdown',
	'Thumbs.db',
	'*.patch',
);

Sync::$CONFIG['UPLOAD_LIMIT_SIZE'] = 2 * 1024 * 1024;