<?php
require_once 'sync/functions.php';
require_once 'sync/pclzip.class.php';
$localdir = './';
require_once 'sync/config.php';

$submit = '';
if(@$_REQUEST['submit']) {
	$submit = $_REQUEST['submit'];
}
$operation = '';
if(@$_REQUEST['operation']) {
	$operation = $_REQUEST['operation'];
}

if($operation == '' && $submit == '') {
	exit($HTMLTemplate);
}

if($operation != '') {
	if($operation == 'md5') {
		$ignorelist = file_get_contents('./sync/ignorelist.txt');
		$ignorelist = explode("\n", trim($ignorelist));

		function listfiles($dir = ".") {
			global $sublevel, $localdir, $fp, $ignores, $hiddenform;
			$sub_file_num = 0;
			$dir          = preg_replace('/^\.\//i', '', $dir);
			$realdir      = $localdir.$dir;
			if(is_file("$realdir")) {
				//fwrite($fp, md5_file($realdir) . ' *' . $dir."\n");
				if($_POST['file'][g2u($dir)] != '') {
					if(md5_file($realdir) != $_POST['file'][g2u($dir)]) {
						$file = g2u($dir);
						echo("parent.addUnmatchItem('$file', false);");
					}
					unset($_POST['file'][g2u($dir)]);
				} else {
					$file = g2u($dir);
					echo("parent.addUnmatchItem('$file', 'local');");
				}

				return 1;
			}

			if($handle = @opendir("$realdir")) {
				$sublevel++;
				while($file = readdir($handle)) {
					if(preg_match($ignores, $file)) continue;
					$sub_file_num += listfiles("$dir/$file");
				}
				closedir($handle);
				$sublevel--;
			}

			return $sub_file_num;
		}

		echo $javascriptHTML;
		$filenum  = 0;
		$sublevel = 0;
		if(!@$_REQUEST['includefiles']) {
			$_REQUEST['includefiles'] = array();
		} else {
			$_REQUEST['includefiles'] = @unserialize($_REQUEST['includefiles']);
		}
		$list = array_merge(explode(' ', @$_REQUEST['list']), $_REQUEST['includefiles']);


		foreach($list as $file) {
			$filenum += listfiles($file);
		}
		if(count($_POST['file'])) {
			foreach($_POST['file'] as $file => $md5) {
				echo("parent.addUnmatchItem('$file', 'remote');");
			}
		}
		exit('parent.output();</script>');
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
<form id="pull" action="http://localhost/manage/sync.php?operation=pulltolocal" method="post" enctype="multipart/form-data">
<input type='hidden' name='talkingSite' value='tryanderror.cn' />
<br />
</form>
<script type="text/javascript">
document.getElementById('pull').submit();
</script>
FOM;

			} else {
				die("Error : ".$Zip->errorInfo(TRUE));
			}
		}
		exit;
	} elseif($operation == 'push') {
		catchthepackage();
		exit('OK');

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
<form action="http://localhost/manage/sync.php?operation=md5checkedsync" method="post">
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
选择要压缩的文件或目录：<a href="http://localhost/manage/sync.php">浏览本地文件</a><br>
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
parent.document.getElementsByTagName('FORM')[0].action = "http://localhost/manage/sync.php?operation=md5ResultToLocal";


FOM;

	exit('parent.document.getElementById("displayRect").innerHTML = html;</script>');
} elseif($submit == 'zip') {
	if(!@$_REQUEST['includefiles']) {
		$_REQUEST['includefiles'] = array();
	}
	packfiles(array_merge(explode(' ', @$_REQUEST['list']), $_REQUEST['includefiles']));
	echo <<<FOM
<form action="http://localhost/manage/sync.php?operation=messagetopick" method="post" enctype="multipart/form-data">
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

function plusHTML($HTML) {
	$HTML    = addslashes($HTML);
	$pattern = '/\S(\r|\n|\r\n)\S/';
	if(preg_match($pattern, $HTML, $matches)) {
		$HTML = explode($matches[1], $HTML);
		$HTML = implode("\";\nhtml += \"", $HTML);
	}
	echo "html += \"".$HTML."\";\n";
}

function curlrequest($url, $data, $method = 'post') {
	$ch = curl_init(); //初始化CURL句柄
	curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出
	//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
	//curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-HTTP-Method-Override: $method")); //设置HTTP头信息
	curl_setopt($ch, CURLOPT_POST, 1); //以post方式提交数据
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //设置提交的字符串
	$document = curl_exec($ch); //执行预定义的CURL
	if(!curl_errno($ch)) {
		$info = curl_getinfo($ch);
		//echo '<div>Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'] . '</div>';
	} else {
		//echo 'Curl error: ' . curl_error($ch);
	}
	curl_close($ch);

	return $document;
}

function packfiles($files) {
	$Zip = new PclZip('./package.zip');
	//$_REQUEST['includefiles'] = array('./xwb.php', './userapp.php');
	$Zip->create($files);
	$list = $Zip->listContent();
	if($list) {
		info($zip);
	} else {
		exit ($localdir."package.zip 不能写入,请检查路径或权限是否正确.<br>");
	}
}


function catchthepackage() {
	global $_FILES;
	if($_FILES["file"]["error"] > 0) {
		echo "Return Code: ".$_FILES["file"]["error"]."<br />";
	} else {
		echo '<br />';
		echo "Upload: ".$_FILES["file"]["name"]."<br />";
		echo "Type: ".$_FILES["file"]["type"]."<br />";
		echo "Size: ".($_FILES["file"]["size"] / 1024)." Kb<br />";
		echo "Temp file: ".$_FILES["file"]["tmp_name"]."<br />";

		if(file_exists("upload/".$_FILES["file"]["name"])) {
			echo $_FILES["file"]["name"]." already exists. ";
		} else {
			move_uploaded_file($_FILES["file"]["tmp_name"], "./".$_FILES["file"]["name"]);
			echo "Stored in: "."./".$_FILES["file"]["name"].'<br />';

			dounzip();
		}
	}
}

function dounzip() {
	$path      = './';
	$name      = 'package.zip';
	$remove    = 0;
	$unzippath = './';
	if(file_exists($path.$name) && is_file($path.$name)) {
		$Zip    = new PclZip($path.$name);
		$result = $Zip->extract($path.(('./' == $unzippath || '。/' == @$_POST['unzippath']) ? '' : $unzippath), $remove);
		if($result) {
			$statusCode = 200;
			info($zip);
			//$message .= '<font color="green">解压总计耗时：</font><font color="red">' . G('_run_start', '_run_end', 6) . ' 秒</font><br />';
		} else {
			$statusCode = 300;
			$message .= '<font color="blue">解压失败：</font><font color="red">'.$Zip->errorInfo(TRUE).'</font><br />';
			echo $message;
			//$message .= '<font color="green">执行耗时：</font><font color="red">' . G('_run_start', '_run_end', 6) . ' 秒</font><br />';
		}
	}
}

function info($zip) {
	$list       = $zip->listContent();
	$fold       = 0;
	$fil        = 0;
	$tot_comp   = 0;
	$tot_uncomp = 0;
	foreach($list as $key => $val) {
		if($val['folder'] == '1') {
			++$fold;
		} else {
			++$fil;
			$tot_comp += $val['compressed_size'];
			$tot_uncomp += $val['size'];
		}
	}
	$message = '<font color="green">解压目标文件：</font><font color="red"> '.g2u($name).'</font><br />';
	$message .= '<font color="green">解压文件详情：</font><font color="red">共'.$fold.' 个目录，'.$fil.' 个文件</font><br />';
	$message .= '<font color="green">压缩文档大小：</font><font color="red">'.dealsize($tot_comp).'</font><br />';
	$message .= '<font color="green">解压文档大小：</font><font color="red">'.dealsize($tot_uncomp).'</font><br />';
	//$message .= '<font color="green">压缩执行耗时：</font><font color="red">' . G('_run_start', '_run_end', 6) . ' 秒</font><br />';

	echo $message;
}
