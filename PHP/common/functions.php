<?php

	//全局打印信息的函数
	function p($name){
		echo "<pre style='font-size:14px;color:#666'>";
		print_r($name);
		echo "</pre>";
	}
	//数组转义函数，用于过滤上传的$_GET,$_POST,$_COOKIE
	function _addslashes($arr){
		foreach($arr as $k=>$v){
			if(is_string($v)){//如果不是数组则进行转义
				$arr[$k]=addslashes($v);
			}else if(is_array($v)){//如果是数组，则调用自身
				$arr[$k]=_addslashes($v);
			}
		}
		return $arr;
	}

	//标签恢复函数,用于读取数据库内容清除标签的转义，在页面正常显示
	function _strip($arr){
		foreach($arr as $k=>$v){
			if(is_string($v)){
				$arr[$k]=htmlspecialchars_decode($v);
			}elseif(is_array($v)){
				$arr[$k]=_strip($v);
			}
		}
		return $arr;
	}
	//数据转义函数，用于转义Ueditor等上传的数据，返回转义后的内容供数据库使用
	function _html($con){
		return htmlspecialchars(stripcslashes($con));
	}
	//错误处理函数
	function error($error){
		if(C('DEBUG')){
			if(!is_array($error)){
				$backtrace=debug_backtrace();
				$e['message']=$error;
				$info='';
				foreach($backtrace as $v){
					$file=isset($v['file'])?$v['file']:'';
					$line=isset($v['line'])?"[".$v['line'].']':'';
					$class=isset($v['class'])?$v['class']:'';
					$type=isset($v['type'])?$v['type']:'';
					$function=isset($v['function'])?$v['function']."()":'';
					$info.=$file.$line.$class.$type.$function."<br/>";
				}
				$e['info']=$info;
				//var_dump($e);
			}else{
					$e=$error;
			}
		}else{
			$e['message']=C('ERROR_MESSAGE');
		}
		include C('DEBUG_TPL');
		exit;
	}
	//提示错误处理
	function notice($e){
		if(C("DEBUG") && C("NOTICE_SHOW")){
			$time=number_format((microtime(true)-debug::$runtime["app_start"]),4);
			$memory=memory_get_usage();
			$message=$e[1];
			$file=$e[2];
			$line=$e[3];
			$msg="
			<h1 style='font-size:13px;background-color:#999;color:#fff;padding:3px;width:894px;'>NOTICE:$message</h1>
			<div style='width:900px'>
				<table style='border:1px solid #999;width:900px'>
					<tr><td>Time</td><td>Memory</td><td>File</td><td>Line</td></tr>
					<tr><td>$time</td><td>$memory</td><td>$file</td><td>$line</td></tr>
				</table>
			</div>";
			echo $msg;
		}
	}
	//生成一个唯一的序列号
	function _md5($str){
		return md5(serialize($str));
	}
	/**
	 **实例化控制器
	 **@param $control  控制器名称，不需要传入控制器后缀；支持"模块.控制器"参数形式
	 **若只传入控制器名且控制器存在返回实例化的控制器；若传入模块名.控制器名且存在，
	 **则返回实例化化后对应模块下的控制器；支持控制器缓存
	 */
	function A($control){
		if(strstr($control,'.')){
			$arr=explode('.',$control);
			$module=$arr[0];
			$control=$arr[1];
		}else{
			$module=MODULE;
		}
		static $_control=array();
		$control=$control.C("CONTROL_FIX");
		if(isset($_control[$control])){
			return $_control[$control];
		}
		if(C('APP_GROUP')){
			$control_path=MODULE_PATH.'/'.$module.'/'.C("CONTROL_DIR").'/'.$control.C("CLASS_FIX").'.php';
		}else{
			$control_path=MODULE_PATH.'/'.$module.'/'.$control.C("CLASS_FIX").'.php';
		}
		loadfile($control_path);
		if(class_exists($control)){
			$_control[$control]=new $control();
			return $_control[$control];
		}else{
			return false;
		}
	}
	/**
	 **实例化对象或或执行方法
	 **@param $class  类名
	 **@param $method 方法名
	 **@param $args   方法参数
	 **若只传入类名，返实例类；若只传类名和方法,执行类下的方法，参数默认；
	 **三者均传入则执行按参数方法；支持缓存
	 */
	function O($class,$method=null,$args=array()){
		static $result=array();
		$name=empty($args)?$class.$method:$class.$method._md5($args);
		if(!isset($result[$name])){
			$obj=new $class();
			if(!is_null($method) && method_exists($obj,$method)){
				if(!empty($args)){
					$result[$name]=call_user_func_array(array(&$obj,$method),array($args));
				}else{
					$result[$name]=$obj->$method();
				}
			}else{
				$result[$name]=$obj;
			}
		}
		return $result[$name];
	}
	//载入文件 @param $file 载入文件的名字（路径）
	function loadfile($file=''){
		static $fileArr=array();
		if(empty($file)){
			return $fileArr;
		}
		$filePath=realpath($file);
		if(isset($fileArr[$filePath])){
			return $fileArr[$filePath];
		}
		if(!is_file($filePath)){
			error("文件".$file."不存在");
		}
		require $filePath;
		$fileArr[$filePath]=true;
		return $fileArr[$filePath];
	}
	/**
	 ** 配置文件处理
	 ** @param $name  配置项名称
	 ** @param $value 配置项值
	 ** @return 不传参数返回所有配置项；传一个参数返回已存在对应的配置项;传两参数设置配置项值
	 ** @return 支持点操作访问二维数组；传入数组则合并配置项
	 */
	function C($name=null,$value=null){
		static $config=array();
		if(is_null($name)){
			return $config;
		}
		if(is_string($name)){
			$name=strtolower($name);
			if(!strstr($name,'.')){
				if(is_null($value)){
					return isset($config[$name])?$config[$name]:null;
				}else{
					$config[$name]=$value;
					return;
				}
			}
			$name=explode('.',$name);
			//print_r($name);
			if(is_null($value)){
				return isset($config[$name[0][1]])?$config[$name[0][1]]:null;
			}else{
				$config[$name[0][1]]=$value;
				return;
			}
		}
		if(is_array($name)){
			$config=array_merge($config,array_change_key_case($name));
			return;
		}
	}
	//实例化一个模型的方法M
	//@param  table   指定的表名  若存在基本继承模型，返回该模型，否则返回Model模型
	function M($table=''){
		static $model=null;
		$str=strtolower($table).'Model';
		if(!isset($model[$str])){
			if(file_exists(PHP_PATH.'/model/'.$str.'.class.php')){
			$model[$str]=new $str();
			}else{
				$model[$str]=new Model($table);
			}
		}
		return $model[$str];
	}
	//格式化编译文件 去空白，注释
	function del_space($file_name){
		$data=file_get_contents($file_name);
		$data=substr($data,0,5)=='<?php'?substr($data,5):$data;//去除php标识符
		$data=substr($data,-2)=='?>'?substr($data,0,-2):$data;
		$preg_arr=array('/\/\*.*?\*\/\s*/is','/\/\/.*?[\r\n]/is','/(?!\w)\s?(?!\w)/is');
		return preg_replace($preg_arr,'',$data);
	}
/**
 * @param $name  session 名称
 * @param $vlaue  session 值
 * @paran $set  销毁sesion的模式，one为清除指定session,all为摧毁所有session
 */
	function session($name,$value='',$set=''){
		session_start();
		if($value=='' && $set==''){//读取session
			return base64_decode($_SESSION[md5($name)]);
		}elseif(is_string($value)){//设置session
			$_SESSION[md5($name)]=base64_encode($value);
		}else{//销毁session
			if($set=='all'){//彻底摧毁
				session_destroy();
			}elseif($set='one'){
				unset($_SESSION[md5($name)]);
			}
		}
	}
	/**
	 * URL重定向
	 * @param string $url 重定向的URL地址
	 * @param integer $time 重定向的等待时间（秒）
	 * @param string $msg 重定向前的提示信息
	 * @return void
	 */
	function redirect($url, $time=0, $msg='') {
	    //多行URL地址支持
	    $url        = str_replace(array("\n", "\r"), '', $url);
	    //$url="http://".ltrim($url,'http://');
	    $msg    .= " 系统将在{$time}秒之后自动跳转到{$url}！";
	    if (!headers_sent()) {
	        // redirect
	        if (0 === $time) {
	            header('Location: ' . $url);
	        } else {
	            header("refresh:{$time};url={$url}");
	            echo($msg);
	        }
	        exit();
	    } else {
	        $str    = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
	        if ($time != 0)
	            $str .= $msg;
	        exit($str);
	    }
	}

	/**
	 * URL组装 支持不同URL模式
	 * @param string $url URL表达式，格式：'[分组/模块/操作#锚点@域名]?参数1=值1&参数2=值2...'
	 * @param string|array $vars 传入的参数，支持数组和字符串
	 * @param string $suffix 伪静态后缀，默认为true表示获取配置值
	 * @param boolean $redirect 是否跳转，如果设置为true则表示跳转到该URL地址
	 * @param integer $time 重定向的等待时间（秒）
	 * @param string $msg 重定向前的提示信息
	 * @return string
	 */
	function U($url='',$vars='',$suffix=true,$redirect=false,$time=0,$msg='') {
	    // 解析URL
	    $info   =  parse_url($url);
	    $url    =  !empty($info['path'])?$info['path']:ACTION;

	    // 解析参数
	    if(is_string($vars)) { // aaa=1&bbb=2 转换成数组
	        parse_str($vars,$vars);
	    }elseif(!is_array($vars)){
	        $vars = array();
	    }
	    if(isset($info['query'])) { // 解析地址里面参数 合并到vars
	        parse_str($info['query'],$params);
	        $vars = array_merge($params,$vars);
	    }
	    // URL组装
	    $depr = C('PATHINFO_DIL');//获取PATHINFO分隔符
	    if($url) {
	        if(0=== strpos($url,'/')) {// 定义路由
	            $route      =   true;
	            $url        =   substr($url,1);
	            if('/' != $depr) {
	                $url    =   str_replace('/',$depr,$url);
	            }
	        }else{
	            if('/' != $depr) { // 安全替换
	                $url    =   str_replace('/',$depr,$url);
	            }
	            // 解析分组、模块和操作
	            $url        =   trim($url,$depr);
	            $path       =   explode($depr,$url);
	            $var        =   array();
	            $var[C('VAR_ACTION')]       =   !empty($path)?array_pop($path):ACTION;
	            $var[C('VAR_CONTROL')]       =   !empty($path)?array_pop($path):CONTROL;
	            $var[C('VAR_MODULE')]		=   !empty($path)?array_pop($path):MODULE;
	        }
	    }

	    if(C('PATHINFO_MODEL') == 0) { // 普通模式URL转换
	        $url =  __APP__.'?'.http_build_query(array_reverse($var));
	        if(!empty($vars)) {
	            $vars   =   urldecode(http_build_query($vars));
	            $url   .=   '&'.$vars;
	        }
	    }else{ // PATHINFO模式或者兼容URL模式
	        if(isset($route)) {
	            $url    =   __APP__.'/'.rtrim($url,$depr);
	        }else{
	            $url    =   __APP__.'/'.implode($depr,array_reverse($var));
	        }
	        if(!empty($vars)) { // 添加参数
	            foreach ($vars as $var => $val){
	                if('' !== trim($val))   $url .= $depr . $var . $depr . urlencode($val);
	            }
	        }
	        if($suffix) {
	            $suffix  =  $suffix===true?C('PATHINFO_HTML'):$suffix;
	            if($pos = strpos($suffix, '|')){
	                $suffix = substr($suffix, 0, $pos);
	            }
	            if($suffix && '/' != substr($url,-1)){
	                $url  .=  '.'.ltrim($suffix,'.');
	            }
	        }
	    }
	    if($redirect) // 直接跳转URL
	        redirect($url,$time,$msg);
	    else
	        return $url;
	}


	//获取上传文件的格式
	function getExt($str){
	    return end(explode('.',$str));
	}

 ?>