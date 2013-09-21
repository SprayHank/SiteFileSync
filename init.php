<?php defined('SYNCSYSTEM') || die('No direct script access.');
//
//
//
//
//
//
define('LOCAL_DIR', "./");
//
include dirname(dirname(__FILE__)).'/SyncClass/init.php';
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$operation == '' && $do == '' && $submit == '' && exit(Page_Template::init_page());
if($do != '') {
	in_array($do, array('after MD5 Compare on local', 'push', 'after upload on local', 'after dnload on local')) || exit('Unkonwn operation');
	$includefiles = isset($_REQUEST['includefiles']) ? $_REQUEST['includefiles'] : array();
	$list         = isset($_REQUEST['list']) ? str_replace('"', '', str_replace(LOCAL_DIR, '', str_replace('\\', '/', $_REQUEST['list']))) : '';
	$listArray    = explode(' ', $list);
	$targetList   = array_merge($listArray, $includefiles);
	$func         = str_replace(' ', '_', $do);
	$hiddenform   = call_user_func_array(array('Sync', $func), array($targetList));
	exit;
	$includefilesHiddenform = '';
	while($includefile = $includefiles) {
		$includefilesHiddenform .= "<input type='hidden' name='includefiles[]' value='$includefile' />";
	}
	echo <<<FOM
		\n
<form action="http://$SessionSite/sync.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="do" value="continue $do on local" />
<input type="hidden" name="list" value="$list" />
$includefilesHiddenform
$hiddenform
</form>
<script type="text/javascript">document.getElementsByTagName('FORM')[0].submit();</script>
FOM;
	exit;
	if($operation == 'md5') {
		$ignorelist = file_get_contents('./sync/ignorelist.txt');
		$ignorelist = explode("\n", trim($ignorelist));

	} elseif($operation == 'md5checkedsync') {
		if(count(($_REQUEST['delete']))) {
			echo 'here';
			foreach(($_REQUEST['delete']) as $delete) {
				@unlink(u2g("$delete"));
				plusHTML('删除文件'.$delete.';<br />');
			}
		}
		if(count(($_REQUEST['dnload']))) {
			$files = array();
			foreach(($_REQUEST['dnload']) as $file) {
				$files[] = u2g($file);
			}
			$Zip = new PclZip('./package.zip');
			$Zip->create($files);
			$list = $Zip->listContent();
			if($list) {
				info($Zip);
				echo <<<FOM
<form id="pull" action="http://localhost/Sync/index.php?operation=pulltolocal" method="post" enctype="multipart/form-data">
<input type='hidden' name='talkingSite' value='tryanderror.cn' />
<br />
</form>
<script type="text/javascript">document.getElementById('pull').submit();</script>
FOM;

			} else {
				die("Error : ".$Zip->errorInfo(TRUE));
			}
		}
		exit;
		$localdir = './';
	} elseif($operation == 'push') {

	} elseif($operation == 'unzip') {
		echo $head;
		dounzip();
		echo $foot;
	} elseif($operation == 'aftermd5check') {
		$upload = $dnload = $delete = array();
		foreach($_POST['file'] as $file => $option) {
			switch($option) {
				case 'ignore':
					$ignorelist = file_get_contents('./sync/ignorelist.txt');
					if(!in_array($file, explode("\n", $ignorelist))) {
						$fp = fopen('./sync/ignorelist.txt', 'a');
						fwrite($fp, "\n$file");
						fclose($fp);
					}
					break;
				case 'upload':
					$upload[] = $file;
					break;
				case 'dnload':
					$dnload[] = $file;
					break;
				case 'delete':
					$delete[] = $file;
					break;
			}
		}
		echo '<br />upload:'.implode('<br />upload:', $upload);
		echo '<br />dnload:'.implode('<br />dnload:', $dnload);
		echo '<br />delete:'.implode('<br />delete:', $delete);
		ECHO <<<HTML
<form action="http://localhost/Sync/index.php?operation=md5checkedsync" method="post">
HTML;
		foreach($upload as $item) {
			echo "\n".'<input type="hidden" name="upload[]" value="'.$item.'" />';
		}
		foreach($delete as $item) {
			echo "\n".'<input type="hidden" name="delete[]" value="'.$item.'" />';
		}
		echo <<<HTML
<input type="submit" name="submit" value="submit" />
</form>
HTML;

	}
}

if(@!$_REQUEST['submit']) {
	if(@$_REQUEST['checkdir'] != '') {
		$localdir = $_REQUEST['currentdir'].($_REQUEST['checkdir']);
	}
	echo $head;
	echo <<<HTM
选择要压缩的文件或目录：<a href="http://localhost/Sync/index.php">浏览本地文件</a><br>
<form name="myform" method="post" action="$_SERVER[PHP_SELF]">
HTM;

	?>
<?php
} elseif($submit == 'md5') {

	//		$fp = fopen('./md5.xml', 'w');
	//		fwrite($fp, '');
	//		fclose($fp);
	//		$fp = fopen('./md5.xml', 'a');
	//$file = array();
	function listfiles($dir = ".") {
		global $sublevel, $localdir, $fp, $ignores, $hiddenform;
		$sub_file_num = 0;
		$dir          = preg_replace('/^\.\//i', '', $dir);
		$realdir      = $localdir.$dir;
		if(is_file("$realdir")) {
			//fwrite($fp, md5_file($realdir) . ' *' . $dir."\n");
			plusHTML('<input type="hidden" name="file['.g2u($dir).']" value="'.md5_file($realdir).'" />');

			return 1;
		}

		$handle = opendir("$realdir");
		$sublevel++;
		while($file = readdir($handle)) {
			if(preg_match($ignores, $file)) continue;
			$sub_file_num += listfiles("$dir/$file");
		}
		closedir($handle);
		$sublevel--;

		return $sub_file_num;
	}


	echo $javascriptHTML;
	plusHTML("正在校验文件...<br><br>");
	$filenum  = 0;
	$sublevel = 0;
	if(!@$_REQUEST['includefiles']) {
		$_REQUEST['includefiles'] = array();
	}
	$list = array_merge(explode(' ', @$_REQUEST['list']), $_REQUEST['includefiles']);


	foreach($list as $file) {
		$filenum += listfiles($file);
	}

	//$package->createfile();
	//fclose($fp);
	plusHTML("<br>校验完成,共添加 $filenum 个文件.<br>");
	echo <<<FOM
parent.document.getElementsByTagName('FORM')[0].action = "http://localhost/Sync/index.php?operation=md5ResultToLocal";


FOM;

	exit('parent.document.getElementById("displayRect").innerHTML = html;</script>');
} elseif($submit == 'zip') {
	if(!@$_REQUEST['includefiles']) {
		$_REQUEST['includefiles'] = array();
	}
	packfiles(array_merge(explode(' ', @$_REQUEST['list']), $_REQUEST['includefiles']));
	echo <<<FOM
<form action="http://localhost/Sync/index.php?operation=messagetopick" method="post" enctype="multipart/form-data">
<label for="file">Filename:</label>
<br />
<input type="submit" name="submit" value="Submit" />
</form>
FOM;
} elseif($submit == '显示远程文件') {
	$fdir = opendir($localdir);
	function checkfiletype($filename) {
		$ext = strrchr($filename, '.');
		$ext = substr($ext, 1);
		switch($ext) {
			case 'txt':
				$type = 'text';
				break;
			case 'htm':
				$type = 'html';
				break;
			default:
				$type = $ext;
		}

		return $type;
	}

	echo $javascriptHTML;
	$parentFolder = <<<HTML
<div class='exploreritem'>
<input name='includefiles[]' type='checkbox' value='' disabled /><br />
<input type='hidden' name='currentdir' value='$localdir' />
<input type='submit' name='submit' class='submit floder-parent' value='..' />
</div>
HTML;
	plusHTML($parentFolder);

	while($file = readdir($fdir)) {
		if(preg_match($ignores, $file)) continue;
		$html = <<<HTML
<div class="exploreritem">
<input name='includefiles[]' type='checkbox' value='$file' /><br />
HTML;
		plusHTML($html);
		if(is_dir($localdir.$file)) {
			plusHTML('<input type="submit" name="checkdir" class="submit floder-page" value="'.$file.'" />');
		} else {
			plusHTML('<input type="submit" name="submit" class="submit '.checkfiletype($file).'" value="'.$file.'" />');
		}
		echo 'html += "</div>";';
		echo "\n";
	}
	exit('parent.document.getElementById("displayRect").innerHTML = html;</script>');
} else {

}
