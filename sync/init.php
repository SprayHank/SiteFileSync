<?php defined('SYNCSYSTEM') || die('No direct script access.');
//
//
//
//
//
//
//
//
//
spl_autoload_register('sync_autoload');
function sync_autoload($class) {
	$cls = dirname(__FILE__).'/../SyncClass/'.$class.'.Class.php';
	is_file($cls) && is_readable($cls) && require($cls); //目标为文件（非目录），可读，载入
}

//兼容转义字符处理
version_compare(PHP_VERSION, '5.3') < 0 && set_magic_quotes_runtime(0);
if(version_compare(PHP_VERSION, '5.4') < 0 && get_magic_quotes_gpc()) {
	function stripslashes_deep($value) {
		$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);

		return $value;
	}

	$_POST    = array_map('stripslashes_deep', $_POST);
	$_GET     = array_map('stripslashes_deep', $_GET);
	$_COOKIE  = array_map('stripslashes_deep', $_COOKIE);
	$_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}
require dirname(__FILE__).'/config.php';
Sync::init_ignores();
GLOBAL $IGNORES;
$submit = '';
isset($_REQUEST['submit']) && $submit = $_REQUEST['submit'];
$operation = '';
isset($_REQUEST['operation']) && $operation = $_REQUEST['operation'];
$do = '';
isset($_REQUEST['do']) && $do = $_REQUEST['do'];
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$operation == '' && $submit == '' && exit(Sync::init_page());
if($operation != '') {
	$includefiles = isset($_REQUEST['includefiles']) ? $_REQUEST['includefiles'] : array();
	$list         = isset($_REQUEST['list']) ? str_replace('"', '', str_replace(LOCAL_DIR, '', str_replace('\\', '/', $_REQUEST['list']))) : '';
	$listArray    = explode(' ', $list);
	$targetList   = array_merge($listArray, $includefiles);
	switch($operation){
		case 'after MD5 Compare on local':
		case 'push':
			$func       = str_replace(' ', '_', $operation);
			$hiddenform = call_user_func_array(array('Sync', $func), array($targetList));
			break;
		default:
			exit('Unkonwn operation');
			break;
	}
	exit;
	$includefilesHiddenform = '';
	while($includefile = $includefiles){
		$includefilesHiddenform .= "<input type='hidden' name='includefiles[]' value='$includefile' />";
	}
	echo <<<FOM
		\n
<form action="http://$SessionSite/sync.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="operation" value="after $do on local" />
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
<script type="text/javascript">
document.getElementById('pull').submit();
</script>
FOM;

			} else {
				die("Error : ".$Zip->errorInfo(TRUE));
			}
		}
		exit;
		$localdir     = './';
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
			info($Zip);
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

function redirect($url) {
	exit("<script type='text/javascript'>document.location.href = '{$url}';</script>");
}


function return_json($statusCode, $message = '', $result = array()) {
	$data = array(
		'statusCode' => $statusCode,
		'message'    => $message,
		'result'     => $result,
		'request'    => array('get' => $_GET, 'post' => $_POST, 'request' => $_REQUEST, 'cookie' => $_COOKIE)
	);
	exit(json_encode($data));
}


function u2g($str) { return iconv('UTF-8', 'GB2312//IGNORE', $str); }


function g2u($str) { return iconv('GB2312', 'UTF-8//IGNORE', $str); }


//去除UTF-8 BOM 文件头
function stripBOM($string) {
	$string = trim($string);
	if(chr(239).chr(187).chr(191) == substr($string, 0, 3)) {
		$string = substr($string, 3);
	}

	return $string;
}


//文件大小格式化
function dealsize($size) {
	$dna = array('Byte', 'KB', 'MB', 'GB', 'TB', 'PB');
	$did = 0;
	while($size >= 900) {
		$size = round($size * 100 / 1024) / 100;
		$did++;
	}

	return $size.' '.$dna[$did];
}


//获取扩展名
function get_ext($filename) {
	$ext = 'unknown';
	$arr = explode('.', basename($filename));
	if(isset($arr[count($arr) - 1])) {
		$ext = $arr[count($arr) - 1];
	}

	return strtolower($ext);
}


//获取文件编码('UTF-8 BOM', 'UTF-8','GB2312','ASCII')
function get_encode($file) {
	$CodeList = array('UTF-8', 'ASCII', 'GB2312');
	$str      = file_get_contents($file);
	if($str) {
		if(chr(239).chr(187).chr(191) == substr($str, 0, 3)) return ('UTF-8 BOM');
		foreach($CodeList as $code) {
			if($str === iconv('UTF-8', "$code//IGNORE", iconv($code, 'UTF-8//IGNORE', $str))) return ($code);
		}
	}

	return 'unknown';
}


function is_utf8($string) { return preg_match('/^([\x09\x0A\x0D\x20-\x7E])+/xs', trim($string)); }


// 循环创建目录
function mk_dir($dir, $mode = 0777) {
	if(is_dir($dir) || mkdir($dir, $mode)) return TRUE;
	if(!mk_dir(dirname($dir), $mode)) return FALSE;

	return mkdir($dir, $mode);
}


// 获取配置值
function C($name = NULL, $value = NULL) {
	static $_config = array();
	// 无参数时获取所有
	if(empty($name)) {
		return $_config;
	}

	// 优先执行设置获取或赋值
	if(is_string($name)) {
		$name = strtolower($name);
		if(FALSE === strpos($name, '.')) {
			if(is_null($value)) {
				return isset($_config[$name]) ? $_config[$name] : NULL;
			} else {
				return $_config[$name] = $value;
			}
		}

		// 二、三维数组设置和获取支持
		$name = explode('.', $name);
		if(FALSE === isset($name[2])) {
			if(is_null($value)) {
				return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : NULL;
			} else {
				return $_config[$name[0]][$name[1]] = $value;
			}
		} else {
			if(is_null($value)) {
				return isset($_config[$name[0]][$name[1]][$name[2]]) ? $_config[$name[0]][$name[1]][$name[2]] : NULL;
			} else {
				return $_config[$name[0]][$name[1]][$name[2]] = $value;
			}
		}
	}
	//批量设置
	if(is_array($name)) {
		return $_config = array_merge($_config, array_change_key_case($name, CASE_LOWER));
	}

	//避免非法参数
	return NULL;
}


// 记录和统计时间（微秒）
function G($start, $end = '', $dec = 3) {
	static $_info = array();
	if(!empty($end)) {
		//统计时间
		if(!isset($_info[$end])) {
			$_info[$end] = microtime(TRUE);
		}

		return number_format(($_info[$end] - $_info[$start]), $dec);
	} else {
		$_info[$start] = microtime(TRUE); //记录时间
	}
}


// 浏览器友好的变量输出
function dump($var, $echo = TRUE, $label = NULL, $strict = TRUE) {
	$label = ($label === NULL) ? '' : rtrim($label).' ';
	if(!$strict) {
		if(ini_get('html_errors')) {
			$output = print_r($var, TRUE);
			$output = '<pre>'.$label.htmlspecialchars($output, ENT_QUOTES).'</pre>';
		} else {
			$output = $label.print_r($var, TRUE);
		}
	} else {
		ob_start();
		var_dump($var);
		$output = ob_get_clean();
		if(!extension_loaded('xdebug')) {
			$output = preg_replace('/\]\=\>\n(\s+)/m', "] => ", $output);
			$output = '<pre>'.$label.htmlspecialchars($output, ENT_QUOTES).'</pre>';
		}
	}
	if($echo) {
		echo($output);
	} else {
		return $output;
	}

	return NULL;
}


//数组保存到文件
function arr2file($filename, $arr = '') {
	if(is_array($arr)) {
		$con = var_export($arr, TRUE);
	} else {
		$con = $arr;
	}
	$con = "<?php if(!defined('WebFTP')){die('Forbidden Access');};?>\n<?php\nreturn $con;\n?>";

	return file_put_contents($filename, $con);
}


//自定义错误处理
function error_handler_fun($errno, $errmsg, $errfile, $errline, $errvars) {
	if(!C('LOG_EXCEPTION_RECORD')) return;
	$user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
	$errortype   = array(
		E_ERROR             => 'EMERG',
		E_WARNING           => 'WARNING', //非致命的 run-time 错误。不暂停脚本执行。
		E_PARSE             => 'EMERG', //语法错误
		E_NOTICE            => 'NOTICE', //Run-time 通知。
		E_CORE_ERROR        => 'EMERG',
		E_CORE_WARNING      => 'WARNING',
		E_COMPILE_ERROR     => 'EMERG',
		E_COMPILE_WARNING   => 'WARNING',
		E_USER_ERROR        => 'EMERG', //致命的用户生成的错误。
		E_USER_WARNING      => 'WARNING', //非致命的用户生成的警告。
		E_USER_NOTICE       => 'NOTICE', //用户生成的通知。
		E_STRICT            => 'NOTICE',
		E_RECOVERABLE_ERROR => 'EMERG', //可捕获的致命错误。
		'INFO'              => 'INFO', //信息: 程序输出信息
		'DEBUG'             => 'DEBUG', // 调试: 调试信息
		'SQL'               => 'SQL', // SQL：SQL语句
	);
	if(isset($errortype[$errno])) {
		$error['type'] = $errortype[$errno];
	} else {
		$error['type'] = $errno;
	}
	if(!in_array($error['type'], explode(',', C('LOG_EXCEPTION_TYPE')))) {
		return;
	}

	$err = date('[ Y-m-d H:i:s (T) ]').'  ';
	$err .= $error['type'].':  ';
	$err .= $errmsg.'  ';
	$err .= $errfile.'  ';
	$err .= '第'.$errline.'行  ';

	$err .= "\n";

	$destination = DATA_PATH.'Logs/'.date('y_m_d').'.log';
	if(is_file($destination) && floor(C('LOG_FILE_SIZE')) <= filesize($destination)) {
		if(1 == C('LOG_SAVE_TYPE')) {
			unlink($destination);
		} else {
			rename($destination, dirname($destination).'/'.time().'-'.basename($destination));
		}
	}
	error_log($err, 3, $destination);
}