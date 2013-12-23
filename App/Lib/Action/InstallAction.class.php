<?php 
class InstallAction extends Action {	
	
	private $upgrade_site = "http://upgrade.5kcrm.com/";
	
	public function index(){	
		if (file_exists(CONF_PATH . "install.lock")) {
			$this->error("请勿重复安装！");		
		}	
		if (!file_exists(getcwd() . "/Public/sql/5kcrm.sql")) {
			$this->error("缺少必要的数据库文件!");		
		}		
		if ($_POST['submit']) {
			$db_config['DB_TYPE'] = 'mysql';
			$db_config['DB_HOST'] = $_POST['DB_HOST'];
			$db_config['DB_PORT'] = $_POST['DB_PORT'];
			$db_config['DB_NAME'] = $_POST['DB_NAME'];
			$db_config['DB_USER'] = $_POST['DB_USER'];
			$db_config['DB_PWD'] = $_POST['DB_PWD'];		
			$db_config['DB_PREFIX'] = $_POST['DB_PREFIX'];
			
			$name = $_POST['name'];
			$password = $_POST['password'];
			
			$warnings = array();
			if (empty($db_config['DB_HOST'])) {
				$warnings[] = '请填写数据库主机';
			}			
			if (empty($db_config['DB_PORT'])) {
				$warnings[] = '请填写数据库端口';
			}
			if (preg_match('/[^0-9]/', $db_config['DB_PORT'])) {
				$warnings[] = '数据库端口只能是数字';
			}
			if (empty($db_config['DB_NAME'])) {
				$warnings[] = '请填写数据库名';
			}
			if (empty($db_config['DB_USER'])) {
				$warnings[] = '请填写数据库用户名';
			}
			if (empty($db_config['DB_PREFIX'])) {
				$warnings[] = '请填写表前缀';
			}
			if (preg_match('/[^a-z0-9_]/i', $db_config['DB_PREFIX'])) {
				$warnings[] = '表前缀只能包含数字、字母和下划线';
			}
			if (empty($name)) {
				$warnings[] = '请填写管理员用户名';
			}
			if (empty($password)) {
				$warnings[] = '请填写管理员密码';
			}

			if (empty($warnings)) {
				$connect = mysql_connect($db_config['DB_HOST'] . ":" . $db_config['DB_PORT'], $db_config['DB_USER'], $db_config['DB_PWD']);
				if(!$connect) {
					$warnings[] = '数据库连接失败，请检查配置！';
				} else {
					if(!mysql_select_db($db_config['DB_NAME'])) {
						if(!mysql_query("create database ".$db_config['DB_NAME']." DEFAULT CHARACTER SET utf8")) {
							$warnings[] = '没有找到您填写的数据库名且无法创建！请检查连接账号是否有创建数据库的权限！';
						}
					}
				} 
				if(!check_dir_iswritable(APP_PATH.'Runtime')){
					$warnings[] = APP_PATH.'Runtime 文件夹要求有写权限!';
				}
				if(!check_dir_iswritable(APP_PATH.'Conf')){
					$warnings[] = APP_PATH.'Conf 文件夹要求有写权限!';
				}
			}
			
			if (empty($warnings)) {
				$db_config_str 	 = 	"<?php\r\n";
				$db_config_str	.=	"return array(\r\n";
				foreach($db_config as $k => $v) {
					$db_config_str .= "'" . $k."'=>'".$v."',\r\n";
				}
				$db_config_str.=");";
				file_put_contents(CONF_PATH . "db.php", $db_config_str);
				$this->admin = array('name'=>$name ,'password'=>$password);				
				$this->display('install');
			} else {
				$this->assign('warnings', $warnings);
				$this->display();
			}
		} else {
			$this->assign('errors', $this->checkEnv());
			$this->display();	
		}
    }
	
	public function install() {
		@set_time_limit(1000);
		if (file_exists(CONF_PATH . "install.lock")) {
			$this->error("请勿重复安装！");		
		}	
		$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		$name = trim($_GET['name']);
		$password = trim($_GET['password']);
		
		$db = M();

		$sql = file_get_contents(getcwd() . "/Public/sql/5kcrm.sql");
		$sql = str_replace("http://demo.5kcrm.com", __ROOT__, $sql);
		$sql = str_replace("\r\n", "\n", $sql); 
		$sql = str_replace("5kcrm_", C('DB_PREFIX'), $sql); 
		$queries = explode(";\n", $sql); 
		$sum = sizeof($queries);
		if ($id < $sum) {
			if(trim($queries[$id])) { 
				$db->query($queries[$id]); 
			} 
		}		
		$id++;
		if ($id == $sum) {
			$salt = substr(md5(time()),0,4);
			$password = md5(md5(trim($password)) . $salt);
			$db->query('insert into ' . C('DB_PREFIX') . 'user (role_id, category_id, status, name, password, salt, reg_ip, reg_time) values (1, 1, 1, "'.$name.'", "'.$password.'", "'.$salt.'", "'.get_client_ip().'", '.time().')'); 
			touch(CONF_PATH . "install.lock");
		}
		
		$this->ajaxReturn($id, floor($id*100/$sum) . "%", 1);
	}
	
	public function upgradeProcess() {
		$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		$dir = getcwd() . "/Public/sql/";
		$upgrade_list = array();
		if(is_dir($dir)){  
			if( $dir_handle = opendir($dir) ){
				while (false !== ( $file_name = readdir($dir_handle)) ) {
					if($file_name=='.' or $file_name =='..'){
						continue;
					} elseif ($file_name != "5kcrm.sql" && strpos($file_name,'.sql')){
						$upgrade_list[] = $file_name;
					}
				}
			}
		}
		$db = M();
		foreach($upgrade_list as $upgrade){
			$sql .= file_get_contents($dir.$upgrade);
		}
		$sql = str_replace("http://demo.5kcrm.com", __ROOT__, $sql);
		$sql = str_replace("\r\n", "\n", $sql); 
		$sql = str_replace("5kcrm_", C('DB_PREFIX'), $sql); 
		$queries = explode(";\n", $sql); 
		
		$sum = sizeof($queries);
		if ($id < $sum) {
			if(trim($queries[$id])) { 
				$db->query($queries[$id]); 
			} 
		}
		$id++;
		if($id >= $sum){
			foreach($upgrade_list as $upgrade){
				@unlink($dir.$upgrade);
			}
		}
		$this->ajaxReturn($id, floor($id*100/$sum) . "%", 1);
	}
	public function upgrade() {
		$dir = getcwd() . "/Public/sql/";
		$upgrade_list = array();
		if(is_dir($dir)){  
			if( $dir_handle = opendir($dir) ){
				while (false !== ( $file_name = readdir($dir_handle)) ) {
					if($file_name=='.' or $file_name =='..'){
						continue;
					} elseif ($file_name != "5kcrm.sql" && strpos($file_name,'.sql')){
						$upgrade_list[] = $file_name;
					}
				}
			}
		}
		if (!empty($upgrade_list)) {
			sort($upgrade_list);
			deldir(DATA_PATH);
			$this->upgrade_list = $upgrade_list;
			$this->display();
		} else {
			$this->error("没有检查到升级文件！");	
		}			
	}
		
	private function checkEnv() {
		$errors = array();
		
		if(substr(PHP_VERSION, 0, 1) < 5) {
    		$errors[] = "请升级您服务器的PHP软件版本到5.0以上！";
    	}
		
		if(!extension_loaded('gd')) {
    		$errors[] = "请开启GD库";
    	}
		
		if(!function_exists("curl_init")) {
    		$errors[] = "请开启CURL扩展";
    	}
		
		if(!function_exists("mb_strlen")) {
    		$errors[] = "请开启MB_STRING函数库";
    	}
		
		if(!is_writable(RUNTIME_PATH)) {
    		$errors[] = "目录" . RUNTIME_PATH . "不可写";
    	}
		if(!is_writable(CONF_PATH)) {
    		$errors[] = "目录" . CONF_PATH . "不可写";
    	}
		if(!is_writable(DATA_PATH)) {
    		$errors[] = "目录" . DATA_PATH . "不可写";
    	}
		if(!is_writable(CACHE_PATH)) {
    		$errors[] = "目录" . CACHE_PATH . "不可写";
    	}
		if(!is_writable(TEMP_PATH)) {
    		$errors[] = "目录" . TEMP_PATH . "不可写";
    	}
		
		return $errors;
	}
	
	public function checkVersion(){	
		$params = array('version'=>C('VERSION'), 'release'=>C('RELEASE'));
		$info = sendRequest($this->upgrade_site . 'index.php?m=index&a=checkVersion', $params);
		if ($info){
			$this->ajaxReturn($info);
		} else {
			$this->ajaxReturn(0, '检查新版本出错', 0);
		}
	}
}