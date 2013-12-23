<?php 
/**
 *
 * 用户相关模块
 *
 **/ 

class UserAction extends Action {

	public function _initialize(){
		$action = array(
			'permission'=>array('login','lostpw','resetpw','active','weixinbinding','notice'),
			'allow'=>array('logout','role_ajax_add','getrolebydepartment','dialoginfo', 'listdialog', 'mutilistdialog', 'getrolelist', 'getpositionlist',  'weixin','changecontent')
		);
		B('Authenticate', $action);
	}
	//注册
	/*
	public function register() {
		$user = D('User');
		if($_GET['op'] == 'checkname'){
			$this->ajaxReturn(0, "用户名可以使用！",0); 
			
			if($user->where('name = "%s"', $_GET['name'])->find()){ 
				$this->ajaxReturn(1, "用户名不可以使用！",1);
			}else{ 
				$this->ajaxReturn(0, "用户名可以使用！"); 
			}
		}else{
			if (isset($_POST['name']) && $_POST['name'] != '') { 	
				if ($user->create()) {
					if ($user->add()) {					
						$this->success('恭喜，添加会员成功！');
					} else {
						$this->error('注册失败，请联系管理员！');
					}
				} else {	
					exit($user->getError());			
				}
			}else{
				$category = M('user_category');
				$this->categoryList = $category->select();
				
				$this->display();
				
			}
		}
	}*/
	//登录
	public function login() {
		if (session('?name')){
			$this->redirect('index/index',array(), 0, '');
		}elseif($_POST['submit']){
			if((!isset($_POST['name']) || $_POST['name'] =='')||(!isset($_POST['password']) || $_POST['password'] =='')){
				alert('error', '请正确输入用户名和密码！'); 
			}elseif (isset($_POST['name']) && $_POST['name'] != ''){
				$m_user = M('user');
				$user = $m_user->where(array('name' => trim($_POST['name'])))->find();
				
				if ($user['password'] == md5(md5(trim($_POST['password'])) . $user['salt'])) {				
					if (-1 == $user['status']) {
						alert('error', '您的账号未通过审核，请联系管理员！');
					} elseif (0 == $user['status']) {
						alert('error', '您的账号正在审核中，请耐心等待！');
					}else {
						$d_role = D('RoleView');
						$role = $d_role->where('user.user_id = %d', $user['user_id'])->find();
						if ($_POST['autologin'] == 1) {
							session(array('expire'=>259200));
						} else {
							session(array('expire'=>3600));
						}
						if (!is_array($role) || empty($role)) {
							alert('error', '系统没有给您分配任何岗位，请联系管理员！'); 
						} else {
							if($user['category_id'] == 1){
								session('admin', 1);
							}
							session('role_id', $role['role_id']);
							session('position_id', $role['position_id']);
							session('role_name', $role['role_name']);
							session('department_id', $role['department_id']);
							session('name', $user['name']);
							session('user_id', $user['user_id']);
							alert('success', '登录成功', U('Index/index'));		
						}
					}
				} else {
					alert('error', '用户名或密码错误！'); 				
				}
			}			
			$this->alert = parseAlert();
			$this->display();
		}else{
			$this->alert = parseAlert();
			$this->display();
		}
	}
	//找回密码
	public function lostpw() {
		if($_POST['submit']){
			if ($_POST['name'] || $_POST['email']){
				$user = M('User');
				if ($_POST['name']){
					$info = $user->where('name = "%s"',trim($_POST['name']))->find();
					if(!isset($info) || $info == null){
						$this->error('用户名不存在');
					}
				} elseif ($_POST['email']){
					$info = $user->where('email = "%s"',trim($_POST['email']))->find();
					if (ereg('^([a-zA-Z0-9]+[_|_|.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|_|.]?)*[a-zA-Z0-9]+.[a-zA-Z]{2,3}$',$_POST['email'])){
						if (!isset($info) || $info == null){
							$this->error('没有用户使用该邮箱');
						}
					}else{
						$this->error('邮箱格式不正确！');
					}					
				}				
				$time = time();
				$user->where('user_id = ' . $info['user_id'])->save(array('lostpw_time' => $time));
				$verify_code = md5(md5($time) . $info['salt']);
				C(F('smtp'),'smtp');
				import('@.ORG.Mail');
				$url = U('user/resetpw', array('user_id'=>$info['user_id'], 'verify_code'=>$verify_code),'','',true);
				$content ='尊敬的' . $_POST['name'] . '：<br/><br/>请点击下面的链接完成找回密码：<br/><br/>' . $url .'<br/><br/>如果以上链接无法点击，请将上面的地址复制到你的浏览器(如IE)的地址栏进入网站。<br/><br/>--悟空CRM(这是一封自动产生的email，请勿回复。)';
				if (SendMail($info['email'],'找回密码链接',$content,'悟空CRM管理员')){
					$this->success('邮件发送成功，请24小时之内到邮箱查看，请留意垃圾邮件！');
				}
			} else {
				$this->error('请输入用户名或邮箱！');
			}
		} else{
			if (!F('smtp')) {
				$this->error('SMTP未设置无法使用此功能，请联系管理员');
			}
			$this->alert = parseAlert();
			$this->display();			
		}
	}
	//密码重置
	public function resetpw(){
		$verify_code = trim($_REQUEST['verify_code']);
		$user_id = intval($_REQUEST['user_id']);
		$m_user = M('User');
		$user = $m_user->where('user_id = %d', $user_id)->find();
		if (is_array($user) && !empty($user)) {
			if ((time()-$user['lostpw_time'])>86400){
				alert('error', '链接失效，请重新找回密码',U('user/lostpw'));
			}elseif (md5(md5($user['lostpw_time']) . $user['salt']) == $verify_code) {
				if ($_REQUEST['password']) {
					$password = md5(md5(trim($_REQUEST["password"])) . $user['salt']);
					$m_user->where('user_id =' . $_REQUEST['user_id'])->save(array('password'=>$password, 'lostpw_time'=>0));
					alert('success', '密码修改成功，请登录', U('user/login'));
				} else {
					$this->alert = parseAlert();
					$this->display();
				}
			} else{
				$this->error('找回密码链接无效或链接已失效！');
			}		
		} else {
			$this->error('找回密码链接无效或链接已失效！');
		}
	}
	
	//退出
	public function logout() {
		session(null);
		$this->success('已经退出！', U('User/login'));
	}
	
	public function listDialog() {
		if ($_GET['by'] == "all") {
			$role_list = $this->role_list = D('RoleView')->select();
			$this->display();
		} else {
			$d_role_view = D('RoleView');
			$all_role = M('role')->where('user_id <> 0')->select();
			$below_role = getSubRole(session('role_id'), $all_role);
			$below_ids[] = session('role_id');
			foreach ($below_role as $key=>$value) {
				$below_ids[] = $value['role_id'];
			}
			$role_list = $this->role_list = $d_role_view->where('role.role_id in (%s)', implode(',', $below_ids))->select();
			$departments = M('roleDepartment')->select();
			$department_id = M('Role')->where('role_id = %d', session('role_id'))->getField('department_id'); 
			$departmentList[] = M('roleDepartment')->where('department_id = %d', $department_id)->find();
			$departmentList = array_merge($departmentList, getSubDepartment($department_id,$departments,''));
			$this->assign('departmentList', $departmentList);
			
			$this->display();
		}
	}
	
	public function mutiListDialog(){
		//1表示所有人  2表示下属
		if($_GET['by'] == 'task'){
			$all_or_below = C('defaultinfo.task_model') == 2 ? 1 : 0;
		}else{
			$all_or_below = $_GET['by'] == 'all' ? 1 : 0;
		}
		$d_role = D('RoleView');
		$sub_role_id = getSubRoleId(false);
		$departments_list = M('roleDepartment')->select();	
		foreach($departments_list as $k=>$v){
			$where = array();
			if(!$all_or_below)
				$where['role_id'] = array('in', $sub_role_id);
			$where['position.department_id'] =  $v['department_id'];
			$roleList = $d_role->where($where)->select();
			$departments_list[$k]['user'] = $roleList;
		}
		$this->departments_list = $departments_list;
		$this->display();
	}
	
	//删除员工
	public function delete(){
alert('error', '无法删除用户！', U('user/index'));
		$m_user = M('user');
		$r_module = array('Log'=>'RLogUser', 'File'=>'RFileUser');
		if($this->isPost()){
			$user_ids = is_array($_POST['user_id']) ? implode(',', $_POST['user_id']) : '';
			if(in_array(session('user_id'), $_POST['user_id'])) alert('error', '删除失败，请确认您的操作，您的删除操作中包含自己；', U('user/index'));

			if ('' == $user_ids) {
				alert('error', '您没有选择任何内容！', U('user/index'));
			} else {
				if($m_user->where('user_id in (%s) and user_id <> 1 and user_id <> %d', $user_ids, session('user_id'))->delete()){
					if(M('role')->where('user_id in (%s)', $user_ids)->delete()){
						foreach ($_POST['user_id'] as $value) {
							foreach ($r_module as $key2=>$value2) {
								$module_ids = M($value2)->where('user_id = %d', $value)->getField($key2 . '_id', true);
								M($value2)->where('user_id = %d', $value) -> delete();
								if(!is_int($key2)){	
									M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
								}
							}
						}
						alert('success', '删除成功!',$_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '删除失败，联系管理员！', U('user/index'));
					}
				} else {
					alert('error', '删除失败，联系管理员！', U('user/index'));
				}
			}
		} elseif($_GET['id']) {
			if(session('user_id') == intval($_GET['id'])) alert('error', '删除失败，请确认您的操作，您的删除操作中包含自己；', U('user/index'));
			$user = $m_user->where('user_id = %d', $_GET['id'])->find();
			if (is_array($user)) {
				if($m_user->where('user_id = %d and user_id <> 1 and user_id <> %d', $_GET['id'], session('user_id'))->delete()){
					if(M('role')->where('user_id = %d', $_GET['id'])->delete()){
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('user_id = %d', $_GET['id'])->getField($key2 . '_id', true);
							M($value2)->where('user_id = %d', $_GET['id']) -> delete();
							if(!is_int($key2)){
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
						alert('success', '删除成功!', U('user/index'));
					} else {
						alert('error', '删除失败，联系管理员！', U('user/index'));
					}
				}else{
					alert('error', '删除失败，请联系管理员！', U('user/index'));
				}				
			} else {
				alert('error', '记录不存在！', U('user/index'));
			}			
		} else {
			alert('error', '请选择要删除的记录!',$_SERVER['HTTP_REFERER']);
		}
	}
	//修改自己的信息
	public function edit(){
		if ($this->isPost()) {
			$m_user = M('user');
			$m_role = M('role');
			if (!ereg('^[_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}$', $_POST['email'])){
				alert('error', '邮箱格式不正确', $_SERVER['HTTP_REFERER']);
			}
			$user=M('user')->where('user_id = %d', $_POST['user_id'])->find();
			if ($_POST['category_id'] && $_POST['category_id'] != $user['category_id']) {
				if(!session('?admin')) alert('error','您没有权限修改用户类别！',$_SERVER['HTTP_REFERER']);
				if(!session('?admin')) alert('error','您没有权限修改用户名！',$_SERVER['HTTP_REFERER']);
			}
			if ($m_user->create()) {
				if(isset($_POST['password']) && $_POST['password']!=''){
					$m_user->password = md5(md5(trim($_POST["password"])) . $user['salt']);
				} else {
					unset($m_user->password);
				}
				$is_update = false;
				if($m_user->save()) $is_update = true;
				if(session('?admin')){
					if($m_role->where('user_id = %d', $_POST['user_id'])->setField('position_id', $_POST['position_id'])) $is_update = true;
				}
				if( $is_update ){
					if($_POST['submit'] == '保存'){
						alert('success','员工信息修改成功！',U('user/index'));
					}else{
						alert('success','员工信息修改成功！',U('user/add'));
					}
				}else{
					alert('error','员工信息无变化！',$_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error','员工信息修改失败！',$_SERVER['HTTP_REFERER']);
			}
		}else{
			$user_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : session('user_id');
			$d_user = D('RoleView');
			$user = $d_user->where('user.user_id = %d', $user_id)->find();
			$user['category'] = M('user_category')->where('category_id = %d', $user['category_id'])->getField('name');
			$this->categoryList = M('user_category')->select();
			if($user['department_id']){
				$this->position_list = M('position')->where('department_id = %d', $user['department_id'])->select();
			}

			$department_list = getSubDepartment(0, M('role_department')->select());
			$this->assign('department_list', $department_list);
			
			$this->user = $user;
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function dialogInfo(){
		$role_id = intval($_REQUEST['id']);
		$role = D('RoleView')->where('role.role_id = %d', $role_id)->find();
		$user = M('user')->where('user_id = %d', $role['user_id'])->find();
		$user[role] = $role;
		$this->user = $user;
		$this->categoryList = M('user_category')->select();
		$this->alert = parseAlert();
		$this->display();
	}

	
	public function changeContent(){
		if($this->isAjax()){
			if(!$_GET['name']){
				$m_leads = M('leads');
				if($_GET['department'] & $_GET['department'] != 'all'){
					$department_id = intval($_GET['department']);
				}else{
					$department_id = session('department_id');
				}
				$data = getRoleByDepartmentId($department_id);
			}else{
				$d_role_view = D('RoleView');
				$where['user.name'] = array('like', '%'.trim($_GET['name']).'%');
				$where['department_id'] = array('in', implode(',', getSubRoleId()));
				$data = $d_role_view->where($where)->select();
			}
			$this->ajaxReturn($data, '', 1);
		}
	}
	//添加员工
	/*public function add2(){
		$user = D('User');
		$category = M('user_category');
		$this->categoryList = $category->select();
		if ($_POST['submit']) {
			$this->value = $_POST;
			if(!isset($_POST['name']) || $_POST['name'] == ''){
				alert('error','请输入用户名');				
				$this->alert = parseAlert();
				$this->display();
			}if(!isset($_POST['password']) || $_POST['password'] == ''){
				alert('error', '请输入密码！');
				$this->alert = parseAlert();
				$this->display();
			}elseif(!isset($_POST['repassword']) || $_POST['repassword'] == ''){
				alert('error', '请输入确认密码！');
				$this->alert = parseAlert();
				$this->display();
			}elseif(!isset($_POST['email']) || $_POST['email'] == ''){
				alert('error', '请输入邮箱！');
				$this->alert = parseAlert();
				$this->display();
			}elseif(!isset($_POST['category_id']) || $_POST['category_id'] == ''){
				alert('error', '请选择用户身份!');
				$this->alert = parseAlert();
				$this->display();
			}elseif($_POST['password'] != $_POST['repassword']){
				alert('error', '两次输入密码不一致');
				$this->alert = parseAlert();
				$this->display();
			}else{
				if ($user->create()) {
					if ($user->add()) {
						if($_POST['submit'] == "保存") {
							alert('success', '员工添加成功！', U('user/index'));
						} else {
							alert('success', '员工添加成功！', U('user/add'));
						}
					} else {
						$this->error('添加失败，请联系管理员！');
					}
				} else {
					alert('error',$user->getError());
					$this->alert = parseAlert();
					$this->display();
				}
			}
		}else{
			$role_list = M('role')->select();	
			if (session('?admin')){
				$this->assign('roleList', getSubRole(0, $role_list, ''));
			} else {
				$this->assign('roleList', getSubRole(session('role_id'), $role_list, ''));
			}
			$this->alert = parseAlert();
			$this->display();
		}
	}
	*/
	public function add(){
		$m_role = M('Role');
		$m_user = D('User');
		if ($this->isPost()){
			$m_user->create(); 
			// echo $m_user->name; 
			if($_POST['radio_type'] == 'email'){
				//邮箱激活
				if (!isset($_POST['name']) || $_POST['name'] == '') {
					alert('error', '请输入用户名', $_SERVER['HTTP_REFERER']);				
				} elseif (!isset($_POST['email']) || $_POST['email'] == ''){
					alert('error', '请输入邮箱', $_SERVER['HTTP_REFERER']);	
				} elseif (!ereg('^[_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}$', $_POST['email'])){
					alert('error', '邮箱格式不正确', $_SERVER['HTTP_REFERER']);
				} elseif ($m_user->where('email = "%s"', $_POST['email'])->find()) {
					alert('error', '此邮箱已绑定用户!', $_SERVER['HTTP_REFERER']);
				} elseif (!isset($_POST['category_id']) || $_POST['category_id'] == ''){
					alert('error', '请选择用户类别！', $_SERVER['HTTP_REFERER']);
				} elseif (!session('?admin') && intval($_POST['category_id'])==1) {
					alert('error', '你没有添加管理员用户的权利！', $_SERVER['HTTP_REFERER']);
				} elseif (!isset($_POST['position_id']) || $_POST['position_id'] == ''){
					alert('error', '请选择要添加用户的岗位!', $_SERVER['HTTP_REFERER']);
				} elseif ($m_user->where('name = "%s"', $_POST['name'])->find()){
					alert('error', '该用户已存在!', $_SERVER['HTTP_REFERER']);
				}
				$m_user->status = 0;
				
				
				if($re_id = $m_user->add()){
					// echo $m_user->getLastSql();
					// die();  
					$time = time();
					$info = $m_user->where('user_id = %d', $re_id)->find();
					$m_user->where('user_id = %d' . $info['user_id'])->setField('reg_time', $time);
					$verify_code = md5(md5($time) . $info['salt']);
					C(F('smtp'),'smtp');
					import('@.ORG.Mail');
					$url = U('user/active', array('user_id'=>$info['user_id'], 'verify_code'=>$verify_code),'','',true);
					$content ='尊敬的' . $_POST['name'] . '：<br/><br/>您好！您的CRM管理员已经给您发送了邀请，请查收！
			请点击下面的链接完成注册：<br/><br/>' . $url .'<br/><br/>如果以上链接无法点击，请将上面的地址复制到你的浏览器(如IE)的地址栏进入网站。<br/><br/>--悟空CRM管理员(这是一封自动产生的email，请勿回复。)';
					//echo $info['email'].$content;
					//die();
					if (SendMail($info['email'], '从悟空CRM添加用户邀请', $content,'悟空CRM管理员')){
						$data['position_id'] = $_POST['position_id'];
						$data['user_id'] = $re_id;
						if($role_id = $m_role->add($data)){
							$m_user->where('user_id = %d', $re_id)->setField('role_id', $role_id);
							alert('success', '添加成功，等待被邀请用户激活!', U('user/index'));
						}
					} else {
						alert('error', '无法发送邀请，请检查smtp设置信息!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			}else{
				//填写密码
				if (!isset($_POST['name']) || $_POST['name'] == '') {
					alert('error', '请输入用户名', $_SERVER['HTTP_REFERER']);				
				} elseif (!isset($_POST['password']) || $_POST['password'] == ''){
					alert('error', '请输入密码', $_SERVER['HTTP_REFERER']);	
				} elseif (!isset($_POST['category_id']) || $_POST['category_id'] == ''){
					alert('error', '请选择用户类别！', $_SERVER['HTTP_REFERER']);
				} elseif (!session('?admin') && intval($_POST['category_id'])==1) {
					alert('error', '你没有添加管理员用户的权利！', $_SERVER['HTTP_REFERER']);
				} elseif (!isset($_POST['position_id']) || $_POST['position_id'] == ''){
					alert('error', '请选择要添加用户的岗位!', $_SERVER['HTTP_REFERER']);
				} elseif ($m_user->where('name = "%s"', $_POST['name'])->find()){
					alert('error', '该用户已存在!', $_SERVER['HTTP_REFERER']);
				} elseif (!session('?admin') && intval($_POST['category_id'])==1) {
					alert('error', '你没有添加管理员用户的权利！', $_SERVER['HTTP_REFERER']);
				}
				
				$m_user->status = 1;

				if($re_id = $m_user->add()){
					$data['position_id'] = $_POST['position_id'];
					$data['user_id'] = $re_id;
					if($role_id = $m_role->add($data)){
						$m_user->where('user_id = %d', $re_id)->setField('role_id', $role_id);
						alert('success', '添加成功，该用户已可以登录系统!', U('user/index'));
					}
				}else{
					alert('error','添加失败，请联系管理员！',$_SERVER['HTTP_REFERER']);
				}
			}
		} else {
			$m_config = M('Config');
			if($m_config->where('name = "smtp"')->find()){
				$category = M('user_category');
				$m_position = M('position');
				if(!session('?admin')){
					$department_list = getSubDepartment2(session('department_id'), M('role_department')->select(), 1);
				}else{
					$department_list =  M('role_department')->select();
				}
				
				$where['department_id'] = session('department_id');
				$position_list = getSubPosition(session('position_id'), $m_position->where($where)->select());

				$position_id_array = array();
				foreach($position_list as $k => $v){
					$position_id_array[] = $v['position_id'];
				}
				$where['position_id'] = array('in', implode(',', $position_id_array));
				$role_list = $m_position->where($where)->select();
				
				if(empty($role_list) && !session('?admin')){
					alert('error', '您没有添加用户的权限!', U('setting/smtp'));
				}else{
					$this->categoryList = $category->select();
					$this->assign('department_list', $department_list);
					$this->alert = parseAlert();
					$this->display();
				}
			} else {
				alert('error','请先设置smtp用于邀请用户',U('setting/smtp'));
			}
		}
	}
	
	public function getPositionList() {
		if($_GET[id]){
			$m_position = M('position');
			$where['department_id'] = $_GET['id'];
			$position_list = getSubPosition(session('position_id'), $m_position->where($where)->select());

			$position_id_array = array();
			foreach($position_list as $k => $v){
				$position_id_array[] = $v['position_id'];
			}
			if(!session('?admin')){
				$where['position_id'] = array('in', implode(',', $position_id_array));
			}
			$role_list = $m_position->where($where)->select();
			$this->ajaxReturn($role_list, "获取成功！", 1);
		}else{
			$this->ajaxReturn($role_list, "请选择部门！", 0);
		}
		
	}
	
	
	public function active() {
		$verify_code = trim($_REQUEST['verify_code']);
		$user_id = intval($_REQUEST['user_id']);
		$m_user = M('User');
		$user = $m_user->where('user_id = %d', $user_id)->find();
		if (is_array($user) && !empty($user)) {
			if (md5(md5($user['reg_time']) . $user['salt']) == $verify_code) {
				if ($_REQUEST['password']) {
					$password = md5(md5(trim($_REQUEST["password"])) . $user['salt']);
					$m_user->where('user_id =' . $_REQUEST['user_id'])->save(array('password'=>$password,'status'=>1, 'reg_time'=>time(), 'reg_ip'=>get_client_ip()));
					alert('success', '设置密码成功，请登录', U('user/login'));
				} else {
					$this->alert = parseAlert();
					$this->display();
				}
			} else {
				$this->error('找回密码链接无效或链接已失效！');
			}
		} else {
			$this->error('找回密码链接无效或链接已失效！');
		}
	}
	
	public function view(){
		if($this->isGet()){
			$user_id = isset($_GET['id']) ? $_GET['id'] : 0;
			$d_user = D('RoleView');
			$user = $d_user->where('user.user_id = %d', $user_id)->find();

			$log_ids = M('rLogUser')->where('user_id = %d', $user_id)->getField('log_id', true);
			$user['log'] = M('log')->where('log_id in (%s)', implode(',', $log_ids))->select();
			$log_count = 0;
			foreach ($user['log'] as $key=>$value) {
				$user['log'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$log_count++;
			}
			$user['log_count'] = $log_count;
			
			$file_ids = M('rFileUser')->where('user_id = %d', $user_id)->getField('file_id', true);
			$user['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$file_count = 0;
			foreach ($user['file'] as $key=>$value) {
				$user['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$file_count++;
			}
			$user['file_count'] = $file_count;
			$this->categoryList = M('UserCategory')->select();
			$this->user = $user;
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function index(){
		if(!session('?name') || !session('?user_id')){
			redirect(U('User/login/'), 1, '请先登录...');
		}
		$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
		$status = isset($_GET['status']) ? intval($_GET['status']) : 1 ;
		$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		$d_user = D('UserView'); // 实例化User对象
		
		if(!session('?admin')) $where['role_id'] = array('in', getSubRoleId(true));
		$where['status'] = $status;
		if($id) $where['category_id'] = $id;
		
		import('@.ORG.Page');// 导入分页类
		$count = $d_user->where($where)->count();
	
		$Page = new Page($count,15);// 实例化分页类 传入总记录数和每页显示的记录数
		$Page->parameter = "id=".$id.'&status=' . $status;
		$show  = $Page->show();// 分页显示输出
		$user_list = $d_user->order('reg_time')->where($where)->page($p.',15')->select();

		$this->assign('user_list',$user_list);// 赋值数据集
		$this->assign('page',$show);// 赋值分页输出
		
		$category = M('user_category');
		$this->categoryList = $category->select();
		$this->alert = parseAlert();
		$this->display();
	}
	
	
	//查看部门信息
	public function department(){
		if(!session('?name') || !session('?user_id')){
			redirect(U('User/login/'), 0, '请先登录...');
		}elseif(!session('?admin')){
			alert('error','您没有此权限',$_SERVER['HTTP_REFERER']);
		}
		
		$this->assign('tree_code', getSubDepartmentTreeCode(0, 1));
		$this->alert = parseAlert();
		$this->display(); 
	}
	
	//添加部门信息
	public function department_add(){
		if(!session('?name') || !session('?user_id')){
			redirect(U('User/login/'), 0, '请先登录...');
		}
		
		if($this->isPost()){
			$department = D('roleDepartment');
			if($department->create()){
				$department->name ? '' :alert('error','请填写部门名称',$_SERVER['HTTP_REFERER']);
				if($department->add()){
					alert('success','添加部门成功！',$_SERVER['HTTP_REFERER']);
				}else{
//$department->getLastSql();die();
					alert('error','添加部门失败，请联系管理员！',$_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error',$department->getError(),$_SERVER['HTTP_REFERER']);
			}
		}else{
			$department = M('roleDepartment');
			$department_list = $department->select();	
			$this->assign('departmentList', getSubDepartment(0,$department_list,''));
			$this->display();
		}
	}
	
	public function department_edit(){
		if(!session('?name') || !session('?user_id')){
			redirect(U('User/login/'), 0, '请先登录...');
		}
		
		if($_POST['name']){
			$department = M('roleDepartment');
			$department->create();
			if($department->save($data)){
				alert('success','部门信息修改成功！',$_SERVER['HTTP_REFERER']);
			}else{
				alert('error','数据无变化，修改失败！',$_SERVER['HTTP_REFERER']);
			}
		}elseif($_GET['id']){
			$department = M('roleDepartment');
			$this->assign('vo',$department->where('department_id=' . $_GET['id'])->find());

			$department_list = $department->select();	
			
			foreach($department_list as $key=>$value){
				if($value['department_id'] == $_GET['id']){
					unset($department_list[$key]);
				}
				if($value['parent_id'] == $_GET['id']){
					unset($department_list[$key]);
				}
			}
			$this->assign('departmentList', getSubDepartment(0,$department_list,''));
			$this->display();
		}else{
			$this->error('参数不正确！');
		}
	}
	
	public function department_delete(){
		if(!session('?name') || !session('?user_id')){
			redirect(U('User/login/'), 0, '请先登录...');
		}
		$department = M('roleDepartment');
		if($_POST['dList']){
			
			foreach($_POST['dList'] as $key=>$value){
				$name = $department->where('department_id = %d',$value)->getField('name');
				if($department->where('parent_id=%d',$value)->select()){
					alert('error','请先删除"' . $name . '"部门下的子部门', $_SERVER['HTTP_REFERER']);
				}
				$m_position = M('position');
				if($m_position->where('department_id=%d',$value)->select()){
					alert('error','请先删除"' . $name . '"部门下的岗位', $_SERVER['HTTP_REFERER']);
				}
			}
			if($department->where('department_id in (%s)', join($_POST['dList'],','))->delete()){
				alert('success', '删除成功!',$_SERVER['HTTP_REFERER']);
			}else{
				$this->error('删除失败，联系管理员！');
			}
		}elseif($_GET['id']){
			$department_id = intval($_GET['id']); 
			$name = $department->where('department_id = %d', $department_id)->getField('name');
			if($department->where('parent_id=%d', $department_id)->select()){
				alert('error','请先删除"' . $name . '"部门下的子部门', $_SERVER['HTTP_REFERER']);
			}
			$m_position = M('position');
			if($m_position->where('department_id=%d', $department_id)->select()){
				alert('error','请先删除"' . $name . '"部门下的岗位', $_SERVER['HTTP_REFERER']);
			}
			if($department->where('department_id = %d', $department_id)->delete()){
				alert('success', '删除成功!',$_SERVER['HTTP_REFERER']);
			}else{
				$this->error('删除失败，联系管理员！');
			}
		}else{
			alert('error', '请选择要删除的部门!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function role(){
		if(!session('?name') || !session('?user_id')){
			redirect(U('User/login/'), 0, '请先登录...');
		}elseif(!session('?admin')){
			alert('error','您没有此权限',$_SERVER['HTTP_REFERER']);
		}
		// $m_position = M('Position');
		// $m_department = M('RoleDepartment');
		// $departments = $m_department->select();	
		// $this->assign('departmentList', getSubDepartment(0,$departments,''));
		
		// $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
		
		// if($department_id){
			// $positionList = $m_position->where('department_id = %d', $department_id)->select();
		// }else{
			// $positionList = $m_position->select();
		// }
		

		// $d_role = D('RoleView');
		// foreach($positionList as $k=>$value){
			// $positionList[$k]['department'] = $m_department->where('department_id = %d', $value['department_id'])->find();
			// $positionList[$k]['user'] = $d_role->where('role.position_id = %d', $value['position_id'])->select();
		// }
		// $this->assign('positionList',$positionList);
		$this->assign('tree_code', getSubPositionTreeCode(0, 1));
		$this->alert=parseAlert();
		$this->display();
	}
	
	public function role_ajax_add(){
		if($_POST['name']){
			$role = D('role');
			if($role->create()){
				$role->name ? '' :alert('error','请填写岗位名称',$_SERVER['HTTP_REFERER']);
				if($role_id = $role->add()){
					$role_list = M('role')->select();
					if (session('?admin')) {
						$role_list = getSubRole(0, $role_list, '');
					} else {
						$role_list = getSubRole(session('role_id'), $role_list, '');
					}
					foreach ($role_list as $key=>$value) {
						if ($value['user_id'] == 0) {
							$rs_role[] = $role_list[$key];
						}
					}
				
					$data['role_id'] = $role_id;
					$data['role_list'] = $rs_role;
					$this->ajaxReturn($data,"发送成功！",1);
				}else{
					$this->ajaxReturn("","发送失败！",0);
				}
			}else{
				$this->ajaxReturn("","发送失败！",0);
			}
		}else{
			$department = M('roleDepartment');
			$department_list = $department->select();	
			$this->assign('departmentList', getSubDepartment(0,$department_list,''));
			$role = M('role');
			$role_list = $role->select();	
			$this->assign('roleList', getSubRole(0,$role_list,''));
			$this->display();
		}
	}
	
	public function role_add(){
		if ($this->isPost()) {
			$d_position = D('Position');
			if($d_position->create()){
				$d_position->name ? '' :alert('error','请填写岗位名称',$_SERVER['HTTP_REFERER']);
				if($position_id = $d_position->add()){
					alert('success','添加岗位成功！',$_SERVER['HTTP_REFERER']);
				}else{
					$this->error('添加失败，请联系管理员');
				}
			}else{
				$this->error('添加失败，请联系管理员');
			}
		} else {
			$department_list = M('RoleDepartment')->select();	
			$position_list = M('Position')->select();
			$this->assign('departmentList', getSubDepartment(0,$department_list,''));
			$this->assign('positionList', getSubPosition(0,$position_list,''));
			$this->display();
		}
	}
	
	public function getRoleByDepartment(){
		if($this->isAjax()) {
			$department_id = $_GET['department_id'];
			$roleList = getRoleByDepartmentId($department_id);
			$this->ajaxReturn($roleList, '', 1); 
		}
	}
	
	public function role_edit(){
		if($_POST['name']){
			$m_position = M('Position');
			if($m_position -> create()){
				if($m_position->save()){
					alert('success','修改成功！',$_SERVER['HTTP_REFERER']);
				}else{
					alert('error','无数据变化，修改失败！',$_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error','修改失败，请联系管理员',$_SERVER['HTTP_REFERER']);
			}
			
		}elseif($_GET['id']){
			$m_position = M('position');
			$department_list = M('RoleDepartment')->select();	
			$position_list = $m_position->select();
			$this->assign('position', $m_position->where('position_id=%d', $_GET['id'])->find());
			$this->assign('departmentList', getSubDepartment(0,$department_list,''));
			$this->assign('positionList', getSubPosition(0,$position_list,''));
			$this->display();
		}else{
			$this->error('参数不正确！');
		}
	}
	

	public function role_delete(){
		$m_position = M('position');
		$d_role = D('RoleView');
		if($_POST['roleList']){
			foreach($_POST['roleList'] as $key=>$value){
				$name = $m_position->where('role_id = %d', $value)->getField('name');
				if($d_role->where('position_id = %d', $value)->select()){
					alert('error',$name . '该岗位上已有员工在职！', $_SERVER['HTTP_REFERER']);
				}
			}
			if($m_position->where('role_id in (%s)', join($_POST['roleList'],','))->delete()){
				alert('success', '删除成功!',$_SERVER['HTTP_REFERER']);
			}else{
				$this->error('删除失败，联系管理员！');
			}
		}elseif($_GET['id']){
			if($d_role->where('position.position_id = %d', intval($_GET['id']))->select()){
				alert('error',$name . '该岗位上已有员工在职！', $_SERVER['HTTP_REFERER']);
			}else{
				if($m_position->where('position_id = %d', intval($_GET['id']))->delete()){
					alert('success', '删除成功!',$_SERVER['HTTP_REFERER']);
				}else{
					$this->error('删除失败，联系管理员！');
				}
			}
		}else{
			alert('error', '请选择要删除的岗位!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function user_role_relation(){
		if(!session('?name') || !session('?user_id')){
			redirect(U('User/login/'), 0, '请先登录...');
		}
		//用户添加到岗位
		if($_GET['by'] == 'user_role'){
			if($_GET['id']){
				$this->user = M('User')->where('user_id = %d', $_GET['id'])->find(); //占位符操作 %d整型 %f浮点型 %s字符串 
				
				$department = M('roleDepartment');
				$department_list = $department->select();	
				$departmentList = getSubDepartment(0, $department_list, '');				

				$role = M('Role');				
				foreach($departmentList as $key => $value) {					
					$roleList = $role->where('department_id =' . $value['department_id'])->select();
					$departmentList[$key]['roleList'] = $roleList;				
				}

				$this->assign('departmentList', $departmentList);
				$this->display('User:user_role');
			} elseif($_POST['user_id']){
				$m_user = M('user');
				$user = $m_user->where('user_id = %d' , $_POST['user_id'])->find();
				if($user['status'] == 0){
					alert('error', $user['name'] . '未通过审核，授职失败！',$_SERVER['HTTP_REFERER']);
				} elseif($user['status'] == -1){
					alert('error', $user['name'] . '审核被拒绝，授职失败！',$_SERVER['HTTP_REFERER']);
				} else {
					$role_ids = is_array($_POST['role']) ? implode(',', $_POST['role']) : '';
					$m_role = M('role');	
					$m_role->where("role_id in ('%s')", $role_ids)->setField('user_id', $_POST['user_id']);
					$m_role->where("role_id not in ('%s') and user_id=%d", $role_ids, $_POST['user_id'])->setField('user_id', '');
					
					alert('success', $user['name'] . '岗位修改成功！',$_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error','参数错误！',$_SERVER['HTTP_REFERER']);
			}
		//岗位添加用户
		}else if($_GET['by'] == 'role_user'){
			$role = M('role');
			if($_GET['role_id']){
				$this->role = $role->where('role_id = %d',$_GET['role_id'])->find();
				$this->userList =  M('user')->where('status = %d',1)->select();
				$this->display('User:role_user_add');
			}elseif($_POST['role_id']){
				$role->create();
				$m_user = M('user');
				$user = $m_user->where('user_id = %d' , $_POST['user_id'])->find();
				if (!$user['role_id']) {
					$m_user->where('user_id = %d' , $_POST['user_id'])->setField('role_id', $_POST['role_id']);
				}
				if($role->save()){
					alert('success','设置成功！',$_SERVER['HTTP_REFERER']);
				}else{
					alert('error','设置失败！',$_SERVER['HTTP_REFERER']);
				}			
			}
		}
	}
	
	public function changRole(){
		
	}
	
	public function getRoleList(){	
		$idArray = getSubRoleId();
		$roleList = array();
		foreach($idArray as $roleId){				
			$roleList[$roleId] = getUserByRoleId($roleId);
		}
		
		$this->ajaxReturn($roleList, '', 1);
	}
	public function weixinbinding(){
		if($_POST['submit']){
			if(!$weixinid = trim($_POST['id'])){
				alert('error', '参数错误',U('User/notice')); 
			}
			if((!isset($_POST['name']) || $_POST['name'] =='')||(!isset($_POST['password']) || $_POST['password'] =='')){
				alert('error', '请正确输入用户名和密码！',U('User/weixinbinding').'&id='.$weixinid); 
			}elseif (isset($_POST['name']) && $_POST['name'] != ''){
				$m_user = M('user');
				$user = $m_user->where(array('name' => trim($_POST['name'])))->find();
				if ($user['password'] == md5(md5(trim($_POST['password'])) . $user['salt'])) {
					$m_user->where(array('user_id' => $user['user_id']))->save(array('weixinid'=>$weixinid));
					alert('error', '绑定成功！',U('User/notice'));
				} else {
					alert('error', '用户名或密码错误！',U('User/weixinbinding').'&id='.$weixinid); 				
				}
			}
		}else{
			if(!$weixinid = trim($_GET['id'])){
				alert('error', '参数错误',U('user/notice')); 
			}else{
				$this->assign('id',$weixinid);
			}
			$this->alert = parseAlert();
			$this->display();
		}
	}
	public function notice(){
		$this->alert = parseAlert();
		$this->display();
	}
	public function weixin(){
		$weixin = M('Config')->where('name = "weixin"')->getField('value');
		$weixin_config = unserialize($weixin);
		$this->assign('weixin_config',$weixin_config);
		$this->display();
	}
}