<?php
class TaskAction extends Action{

	public function _initialize(){
		$action = array(
			'permission'=>array('tips'),
			'allow'=>array('close', 'revert')
		);
		B('Authenticate', $action);
	}

	public function add(){
		if ($this->isPost()) {
			$m_task = M('Task');
			if ($task = $m_task->create()) {
				$task['create_date'] = time();
				$task['update_date'] = time();
				$task['due_date'] = isset($_POST['due_date']) ? strtotime($_POST['due_date']) : time();
				
				if(!$_POST['subject']) alert('error', '任务标题不能为空!',  $_SERVER['HTTP_REFERER']);
				if($_POST['owner_role_id_str']){
					$owner_role_id_array = explode(',', $_POST['owner_role_id_str']);
					$creator = getUserByRoleId(session('role_id'));
					$message_content =' 您有新的任务，这是一封CRM系统自动生成的任务站内信通知!
						<br/> &nbsp; &nbsp; &nbsp; 内容如下：<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 主题：' . $_POST['subject'] . '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 分配者:' . $creator['user_name'].'['.$creator['department_name'].'-'.$creator['role_name'].']' .  '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 任务截止日期:' . $_POST['due_date'] .  '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 优先级：' . $_POST['priority'] . '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 描述：' . $_POST['description'];
					$email_content =' 这是一封CRM系统自动生成的任务通知邮件!
						<br/> &nbsp; &nbsp; &nbsp; 内容如下：<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 主题：' . $_POST['subject'] . '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 分配者:' . $creator['user_name'].'['.$creator['department_name'].'-'.$creator['role_name'].']' .  '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 任务截止日期:' . $_POST['due_date'] .  '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 优先级：' . $_POST['priority'] . '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 描述：' . $_POST['description'];
				
					foreach($owner_role_id_array as $k => $v){
						if(!$v) break;
						$task['owner_role_id'] = $v;
						if ($task_id = $m_task->add($task)) {
							$module = isset($_POST['module']) ? $_POST['module'] : '';
							
							if($module != ''){
								switch ($module) {
									case 'contacts' : $m_r = M('RContactsTask'); $module_id = 'contacts_id'; break;
									case 'leads' : $m_r = M('RLeadsTask'); $module_id = 'leads_id'; break;
									case 'customer' : $m_r = M('RCustomerTask'); $module_id = 'customer_id'; break;
									case 'product' : $m_r = M('RProductTask'); $module_id = 'product_id'; break;
									case 'business' : $m_r = M('RBusinessTask'); $module_id = 'business_id'; break;
								}
								if ($_POST['module_id']) {
									$data[$module_id] = intval($_POST['module_id']);
									$data['task_id'] = $task_id;
									$rs = $m_r->add($data);
									if ($rs<=0) {
										alert('error', '关联失败！', $_SERVER['HTTP_REFERER']);
									}
								} else {
									alert('error', '请选择对应项', $_SERVER['HTTP_REFERER']);
								}
							}
							
							if(intval($_POST['message_alert']) == 1) {
								sendMessage($v,$message_content,1);
							}
							if(intval($_POST['email_alert']) == 1){
								sysSendEmail($v,'您有新的任务-这是一封CRM系统自动生成的任务通知邮件 ',$email_content);
							}
						} else {
							alert('error', '添加失败！',  $_SERVER['HTTP_REFERER']);
						}
					}
					if($_POST['submit'] == "保存") {
						alert('success', '添加成功！', U('task/index'));
					} elseif($_POST['submit'] == '保存并新建') {
						alert('success', '添加成功！', U('task/add'));
					} else {
						alert('success', '添加成功！', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '请选择任务执行人！',  $_SERVER['HTTP_REFERER']);
				}
				
				
			} else {
				$this->error('添加失败，请联系管理员!');
			}
		} elseif($_GET['r'] & $_GET['module'] & $_GET['id']) {
			$this->r = $_GET['r'];
			$this->module = $_GET['module'];
			$this->id = $_GET['id'];
			$this->display('Task:add_dialog');
		}  else {
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function edit(){
		if($_POST['owner_name']){
			$d_task = D('Task');
			$d_task->create();
			$d_task->due_date = strtotime($_POST['due_date']);
			$d_task->update_time = time();

			$is_updated = false;
			$module = isset($_POST['module']) ? $_POST['module'] : '';
			$task_id = intval($_POST['task_id']);
			$task = M('Task')->where('task_id = %d', $task_id)->find();
			if(session('role_id') == $task['creator_role_id'] || session('?admin')){
				if ($module != '') {
					switch ($module) {
						case 'contacts' : $m_r = M('RContactsTask'); $module_id = 'contacts_id'; break;
						case 'leads' : $m_r = M('RLeadsTask'); $module_id = 'leads_id'; break;
						case 'customer' : $m_r = M('RCustomerTask'); $module_id = 'customer_id'; break;
						case 'product' : $m_r = M('RProductTask'); $module_id = 'product_id'; break;
						case 'business' : $m_r = M('RBusinessTask'); $module_id = 'business_id'; break;
					}
					if ($_POST['module_id']) {
						if (!$m_r->where('task_id = %d and '.$module.'_id = %d', $task_id, intval($_POST['module_id']))->find()) {
							$r_module = array('Business'=>'RBusinessTask', 'Contacts'=>'RContactsTask', 'Customer'=>'RCustomerTask', 'Product'=>'RProductTask','Leads'=>'RLeadsTask');
							foreach ($r_module as $key=>$value) {
								$r_m = M($value);
								$r_m->where('task_id = %d', $task_id)->delete();
							}
							$data[$module_id] = intval($_POST['module_id']);
							$data['task_id'] = $task_id;
							$rs = $m_r->add($data);
							if ($rs<=0) {
								alert('error', '关联失败1！', $_SERVER['HTTP_REFERER']);
							}
							$is_updated = true;
						}
					} else {
						alert('error', '请选择对应项', $_SERVER['HTTP_REFERER']);
					}
				}
				if ($d_task->save()) $is_updated = true;
			}elseif(session('role_id') == $task['owner_role_id']){
				$data['status'] = $_POST['status'];
				if($d_task->where('task_id = %d', $task_id)->save($data)){ 
					$is_updated = true;
				}else{
					alert('error', '您只可以改变任务的状态，无数据变化修改失败！', $_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '您没有权限修改！', $_SERVER['HTTP_REFERER']);
			}
			
			if($is_updated){
				alert('success', '任务修改成功！', U('task/view', 'id='.$task_id));
			}else{
				alert('error', '数据无变化，修改失败！', $_SERVER['HTTP_REFERER']);
			}
		}elseif($_GET['id']){
			$m_task = M('Task');
			$task = $m_task->where('task_id = %d',$_GET['id'])->find();
			if($task['isclose'] == 1){
				alert('error','此任务已关闭,不能再次编辑！',$_SERVER['HTTP_REFERER']);
			}
			if(is_array($task)){
				$task['owner']  = D('RoleView')->where('role.role_id = %d', $task['owner_role_id'])->find();
				
				$r_module = array('Business'=>'RBusinessTask', 'Contacts'=>'RContactsTask', 'Customer'=>'RCustomerTask', 'Product'=>'RProductTask','Leads'=>'RLeadsTask');
				foreach ($r_module as $key=>$value) {
					$r_m = M($value);
					
					if($module_id = $r_m->where('task_id = %d', trim($_GET['id']))->getField($key . '_id')){
						if($key == 'Leads') {
							$leads = M($key)->where($key.'_id = %d', $module_id)->find();
							$name = $leads['first_name'].$leads['last_name']. ' ' . $leads['company'];
						} else {
							$name = M($key)->where($key.'_id = %d', $module_id)->getField('name');
						}
						$module = M($key)->where($key.'_id = %d', $module_id)->find();
						$task['module']=array('module_name'=>$key,'name'=>$name,'module_id'=>$module_id);
						break;
					}
				}
				
				$this->task = $task;
				$this->alert = parseAlert();
				$this->display();
			} else {
				alert('error', '任务不存在!',$_SERVER['HTTP_REFERER']);
			}
		}else{
			$this->error('参数错误');
		}
	}
	
	public function delete(){
		$m_task = M('Task');
		if($this->isPost()){
			$task_ids = is_array($_POST['task_id']) ? implode(',', $_POST['task_id']) : '';
			if ('' == $task_ids) {
				alert('error', '您没有选择任何内容！', U('task/index'));
			} else {
				if(!session('?admin')){
					foreach($_POST['task_id'] as $key => $value){
						if(!$m_task->where('owner_role_id = %d and task_id = %d', session('role_id'), $value) -> find()){
							alert('error', '您没有全部权限进行操作！', $_SERVER['HTTP_REFERER']);
						}
					}
				}
				$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
				if($m_task->where('task_id in (%s)', $task_ids)->save($data)){	
					alert('success', '删除成功!',U('task/index'));
				} else {
					alert('error', '删除失败，联系管理员！', U('task/index'));
				}
			}
		} elseif ($_GET['id']) {
			$task = $m_task->where('task_id = %d', $_GET['id'])->find();
			if (is_array($task)) {
				if($task['owner_role_id'] == session('role_id') || session('?admin')){
					$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time);
					if($m_task->where('task_id = %d', $_GET['id'])->save($data)){
						if($_GET['redirect']){
							alert('success', '删除成功！', U('task/index'));
						} else {
							alert('success', '删除成功！', $_SERVER['HTTP_REFERER']);
						}
					}else{
						alert('error', '删除失败，请联系管理员！', $_SERVER['HTTP_REFERER']);
					}	
				} else {
					alert('error', '您没有权限！', $_SERVER['HTTP_REFERER']);
				}
					
			} else {
				alert('error', '记录不存在！', U('leads/index'));
			}			
		} else {
			alert('error', '请选择要删除的任务!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function completeDelete(){
		$m_task = M('Task');
		$r_module = array('Log'=>'RLogTask', 'File'=>'RFileTask', 'RBusinessTask', 'RContactsTask', 'RCustomerTask', 'RProductTask', 'RLeadsTask');
		if($this->isPost()){
			$task_ids = is_array($_POST['task_id']) ? implode(',', $_POST['task_id']) : '';
			if ('' == $task_ids) {
				alert('error', '您没有选择任何内容！', U('leads/index'));
			} else {
				if(!session('?admin')){
					foreach($_POST['task_id'] as $key => $value){
						if(!$m_task->where('owner_role_id = %d and task_id = %d', session('role_id'), $value) -> find()){
							alert('error', '您没有全部权限进行操作！', $_SERVER['HTTP_REFERER']);
						}
					}
				}
				if($m_task->where('task_id in (%s)', $task_ids)->delete()){	
					foreach ($_POST['task_id'] as $value) {
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('task_id = %d', $value)->getField($key2 . '_id', true);
							M($value2)->where('task_id = %d', $value) -> delete();
							if(!is_int($key2)){	
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
					}
					alert('success', '删除成功!',$_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败，联系管理员！', $_SERVER['HTTP_REFERER']);
				}
			}
		} elseif ($_GET['id']) {
			$task = $m_task->where('task_id = %d', $_GET['id'])->find();
			if (is_array($task)) {
				if($task['owner_role_id'] == session('role_id') || session('?admin')){
					if($m_task->where('task_id = %d', $_GET['id'])->delete()){
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('task_id = %d', $_GET['id'])->getField($key2 . '_id', true);
							M($value2)->where('task_id = %d', $_GET['id']) -> delete();
							if(!is_int($key2)){
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
						if($_GET['redirect']){
							alert('success', '删除成功！', $_SERVER['HTTP_REFERER']);
						} else {
							alert('success', '删除成功！', $_SERVER['HTTP_REFERER']);
						}
					}else{
						alert('error', '删除失败，请联系管理员！', $_SERVER['HTTP_REFERER']);
					}	
				} else {
					alert('error', '您没有权限！', $_SERVER['HTTP_REFERER']);
				}
					
			} else {
				alert('error', '记录不存在！', $_SERVER['HTTP_REFERER']);
			}			
		} else {
			alert('error', '请选择要删除的线索!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function revert(){
		$task_id = isset($_GET['id']) ? intval(trim($_GET['id'])) : 0;
		if ($task_id > 0) {
			$m_task = M('task');
			$task = $m_task->where('task_id = %d', $task_id)->find();
			if (session('?admin') || $task['delete_role_id'] == session('role_id')) {
				if ($m_task->where('task_id = %d', $task_id)->setField('is_deleted', 0)) {
					alert('success', '还原成功！', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '还原失败！', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '您没有权限还原！', $_SERVER['HTTP_REFERER']);
			}
		} else {
			alert('error', '参数错误！', $_SERVER['HTTP_REFERER']);
		}
	}
	
	public function index(){
		$by = isset($_GET['by']) ? trim($_GET['by']) : '';
		$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
		$m_task = M('Task');
		$below_ids = getSubRoleId(false);
		$all_ids = getSubRoleId();
		$where = array();
		$params = array();
		$order = "";
		
		switch ($by) {
			case 'sub' : $where['owner_role_id'] = array('in',implode(',', $below_ids)); break;
			case 'create' : $where['creator_role_id'] = session('role_id');break;
			case 's1' : $where['status'] = '未启动';  break;
			case 's2' : $where['status'] = '推迟';  break;
			case 's3' : $where['status'] = '进行中';  break;
			case 's4' : $where['status'] = '完成';  break;
			case 'closed' : $where['isclose'] = 1; break;
			case 'deleted' : $where['is_deleted'] = 1; break;
			case 'today' : 
				$where['due_date'] =  array('between',array(strtotime(date('Y-m-d')) -1 ,strtotime(date('Y-m-d')) + 86400)); 
				break;
			case 'week' : 
				$week = (date('w') == 0)?7:date('w');
				$where['due_date'] =  array('between',array(strtotime(date('Y-m-d')) - ($week-1) * 86400 -1 ,strtotime(date('Y-m-d')) + (8-$week) * 86400));
				break;
			case 'month' : 
				$next_year = date('Y')+1;
				$next_month = date('m')+1;
				$month_time = date('m') ==12 ? strtotime($next_year.'-01-01') : strtotime(date('Y').'-'.$next_month.'-01');
				$where['due_date'] = array('between',array(strtotime(date('Y-m-01')) -1 ,$month_time));
				break;
			case 'add' : $order = 'create_date desc';  break;
			case 'update' : $order = 'update_date desc';  break;
			case 'me' : $where['owner_role_id'] = session('role_id'); break;
			default :  $where['owner_role_id'] = array('in',implode(',', $all_ids)); break;
		}
		if (!isset($where['isclose'])) {
			$where['isclose'] = 0;
		}
		if (!isset($where['is_deleted'])) {
			$where['is_deleted'] = 0;
		}
		if (!isset($where['status'])) {
			$where['status'] = array('neq','完成');
		}
		if (!isset($where['owner_role_id'])) {
			$where['owner_role_id'] = array('in',implode(',', getSubRoleId())); 
		}

		if ($_REQUEST["field"]) {
			$field = trim($_REQUEST['field']) == 'all' ? 'subject|status|priority|description|due_date' : $_REQUEST['field'];
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
			if	('due_date' == $field || $field == 'update_date' || $field == 'create_date') {
				$search = is_numeric($search)?$search:strtotime($search);
			}
			switch ($condition) {
				case "is" : $where[$field] = array('eq',$search);break;
				case "isnot" :  $where[$field] = array('neq',$search);break;
				case "contains" :  $where[$field] = array('like','%'.$search.'%');break;
				case "not_contain" :  $where[$field] = array('notlike','%'.$search.'%');break;
				case "start_with" :  $where[$field] = array('like',$search.'%');break;
				case "end_with" :  $where[$field] = array('like','%'.$search);break;
				case "is_empty" :  $where[$field] = array('eq','');break;
				case "is_not_empty" :  $where[$field] = array('neq','');break;
				case "gt" :  $where[$field] = array('gt',$search);break;
				case "egt" :  $where[$field] = array('egt',$search);break;
				case "lt" :  $where[$field] = array('lt',$search);break;
				case "elt" :  $where[$field] = array('elt',$search);break;
				case "eq" : $where[$field] = array('eq',$search);break;
				case "neq" : $where[$field] = array('neq',$search);break;
				case "between" : $where[$field] = array('between',array($search-1,$search+86400));break;
				case "nbetween" : $where[$field] = array('not between',array($search,$search+86399));break;
				case "tgt" :  $where[$field] = array('gt',$search+86400);break;
				default : $where[$field] = array('eq',$search);
			}
			$params = array('field='.$field, 'condition='.$condition, 'search='.trim($_REQUEST['search']));
		}
		
		$order = empty($order) ? 'due_date asc' : $order;
		$task_list = $m_task->where($where)->order($order)->page($p.',15')->select();

		$count = $m_task->where($where)->count();
		import("@.ORG.Page");
		$Page = new Page($count,15);
		if (!empty($_GET['by'])) {
			$params[]=   "by=".trim($_GET['by']);
		}
		$Page->parameter = implode('&', $params);
		$this->assign('page', $Page->show());
		
		foreach ($task_list as $key=>$value) {
			$task_list[$key]['owner'] = getUserByRoleId($value['owner_role_id']);
			$task_list[$key]['creator'] = getUserByRoleId($value['creator_role_id']);
			$task_list[$key]['deletor'] = getUserByRoleId($value['delete_role_id']);
			//关联模块
			$r_module = array('Business'=>'RBusinessTask', 'Contacts'=>'RContactsTask', 'Customer'=>'RCustomerTask', 'Product'=>'RProductTask','Leads'=>'RLeadsTask');
			foreach ($r_module as $k=>$v) {
				$r_m = M($v);
				if($module_id = $r_m->where('task_id = %d', $value['task_id'])->getField($k . '_id')){			
					
					$name = M($k)->where($k.'_id = %d', $module_id)->getField('name');
					$is_deleted = M($k)->where($k.'_id = %d', $module_id)->getField('is_deleted');
					$name_str = msubstr($name,0,20,'utf-8',false);
					$name_str .= $is_deleted == 1 ? '<font color="red">(已删除)</font>' : '';
					switch ($k){
						case 'Product' : $module_name='产品'; 
							$name = '<a href="index.php?m=product&a=view&id='.$module_id.'" title="'.$name.'">'.$name_str.'</a>';
							break;
						case 'Leads' : $module_name='线索'; 
							$name = '<a href="index.php?m=leads&a=view&id='.$module_id.'" title="'.$name.'">'.$name_str.'</a>';
						break;
						case 'Contacts' : $module_name='联系人'; 
							$name = '<a href="index.php?m=contacts&a=view&id='.$module_id.'" title="'.$name.'">'.$name_str.'</a>';
						break;
						case 'Business' : $module_name='商机'; 
							$name = '<a href="index.php?m=business&a=view&id='.$module_id.'" title="'.$name.'">'.$name_str.'</a>';
						break;
						case 'Customer' : $module_name='客户'; 
							$name = '<a href="index.php?m=customer&a=view&id='.$module_id.'" title="'.$name.'">'.$name_str.'</a>';
						break;
					}
					$task_list[$key]['module']=array('module'=>$k,'module_name'=>$module_name,'name'=>$name,'module_id'=>$module_id);
					break;
				}
			}
			$due_time = $task_list[$key]['due_date'];
			if($due_time){
				$tomorrow_time = strtotime(date('Y-m-d', time()))+86400;
				$diff_days = ($due_time-$tomorrow_time)%86400>0 ? intval(($due_time-$tomorrow_time)/86400)+1 : intval(($due_time-$tomorrow_time)/86400);
				$task_list[$key]['diff_days'] = $diff_days;
			}
		}
		
		$this->task_list = $task_list;
		$this->alert = parseAlert();
		$this->display();
	}
	
	public function view() {
		$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if (0 == $task_id) {
			alert('error', '参数错误！', U('task/index'));
		} else {
			$m_task = M('Task');
			$task = $m_task->where('task_id = %d',$task_id)->find();
			
			$task['owner'] = getUserByRoleId($task['owner_role_id']);
			$task['creator'] = getUserByRoleId($task['creator_role_id']);
			$r_module = array('Business'=>'RBusinessTask', 'Contacts'=>'RContactsTask', 'Customer'=>'RCustomerTask', 'Product'=>'RProductTask','Leads'=>'RLeadsTask');
			foreach ($r_module as $key=>$value) {
				$r_m = M($value);
				if($module_id = $r_m->where('task_id = %d', $task_id)->getField($key . '_id')){			
					if($key == 'Leads') {
						$leads = M($key)->where($key.'_id = %d', $module_id)->find();
						$name = $leads['first_name'].$leads['last_name'].$leads['saltname'].' ' . $leads['company'];
					} else {
						$name = M($key)->where($key.'_id = %d', $module_id)->getField('name');
					}
					switch ($key){
						case 'Product' : $module_name='产品'; break;
						case 'Leads' : $module_name='线索'; break;
						case 'Contacts' : $module_name='联系人'; break;
						case 'Business' : $module_name='商机'; break;
						case 'Customer' : $module_name='客户'; break;
					}
					$task['module']=array('module'=>$key,'module_name'=>$module_name,'name'=>$name,'module_id'=>$module_id);
					break;
				}
			}
			$log_ids = M('rLogTask')->where('task_id = %d', $task_id)->getField('log_id', true);
			$task['log'] = M('log')->where('log_id in (%s)', implode(',', $log_ids))->select();
			$log_count = 0;
			foreach ($task['log'] as $key=>$value) {
				$task['log'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$log_count ++;
			}
			$task['log_count'] = $log_count;

			$file_ids = M('rFileTask')->where('task_id = %d', $task_id)->getField('file_id', true);
			$task['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$file_count = 0;
			foreach ($task['file'] as $key=>$value) {
				$task['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$file_count++;
			}
			$task['file_count'] = $file_count;
			
			if (in_array($task['owner_role_id'], getSubRoleId(false))) {
				if(!($task['comment_role_id'] > 0)){
					$this->comment_role_id = session('role_id');
				}
			}
			
			$this->comment_list = D('CommentView')->where('module = "task" and module_id = %d', $task['task_id'])->order('comment.create_time desc')->select();
			$this->task = $task;
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function close(){
		$id = isset($_GET['id']) ? $_GET['id'] : 0; 
		if ($id >= 0) {
			$m_task = M('task');
			$task = $m_task->where('owner_role_id = %d and task_id = %d', session('role_id'), $id)->find();
			if (is_array($task) && !empty($task)) {
				if($m_task->where('task_id = %d', $id)->setField('isclose', 1)){
					alert('success', '已关闭', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '关闭任务失败!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '您没有权限关闭该任务!', $_SERVER['HTTP_REFERER']);
			}
		}else{
			alert('error', '参数错误', $_SERVER['HTTP_REFERER']);
		}
	}

	public function excelExport(){
		import("ORG.PHPExcel.PHPExcel");
		$objPHPExcel = new PHPExcel();    
		$objProps = $objPHPExcel->getProperties();    
		$objProps->setCreator("5kcrm");    
		$objProps->setLastModifiedBy("5kcrm");    
		$objProps->setTitle("5kcrm Task Data");    
		$objProps->setSubject("5kcrm Task Data");    
		$objProps->setDescription("5kcrm Task Data");    
		$objProps->setKeywords("5kcrm Task Data");    
		$objProps->setCategory("Task");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
		$objActSheet->setCellValue('A1', '主题');
		$objActSheet->setCellValue('B1', '负责人');
		$objActSheet->setCellValue('C1', '截止日期');
		$objActSheet->setCellValue('D1', '状态');
		$objActSheet->setCellValue('E1', '优先级');
		$objActSheet->setCellValue('F1', '是否发生通知email');
		$objActSheet->setCellValue('G1', '描述');
		$objActSheet->setCellValue('H1', '创建人');
		$objActSheet->setCellValue('I1', '创建时间');

		$where['owner_role_id'] = array('in',implode(',', getSubRoleId()));
		$list = M('task')->where($where)->select();
		$i = 1;
		foreach ($list as $k => $v) {
			$i++;
			$owner = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
			$creator = D('RoleView')->where('role.role_id = %d', $v['creator_role_id'])->find();
			$objActSheet->setCellValue('A'.$i, $v['subject']);
			$objActSheet->setCellValue('B'.$i, $owner['user_name'].'['.$owner['department_name'].'-'.$owner['role_name']).']';
			$v['due_date'] == 0 || strlen($v['due_date']) != 10 ? $objActSheet->setCellValue('C'.$i, '') : $objActSheet->setCellValue('C'.$i, date("Y-m-d H:i:s", $v['due_date']));
			$objActSheet->setCellValue('D'.$i, $v['status']);
			$objActSheet->setCellValue('E'.$i, $v['priority']);
			$v['send_email'] == 0 ? $objActSheet->setCellValue('F'.$i, '否') : $objActSheet->setCellValue('F'.$i, '是');
			$objActSheet->setCellValue('G'.$i, $v['description']);
			$objActSheet->setCellValue('H'.$i, $creator['user_name'].'['.$creator['department_name'].'-'.$creator['role_name'].']');
			$objActSheet->setCellValue('I'.$i, date("Y-m-d H:i:s", $v['create_date']));
		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition:attachment;filename=5kcrm_task_".date('Y-m-d',mktime()).".xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
	}

	public function excelImport(){
		$m_task = M('task');
		if($_POST['submit']){
			if (isset($_FILES['excel']['size']) && $_FILES['excel']['size'] != null) {
				import('@.ORG.UploadFile');
				$upload = new UploadFile();
				$upload->maxSize = 20000000;
				$upload->allowExts  = array('xls');
				$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					alert('error', '附件上传目录不可写', U('task/index'));
				}
				$upload->savePath = $dirname;
				if(!$upload->upload()) {
					alert('error', $upload->getErrorMsg(), U('task/index'));
				}else{
					$info =  $upload->getUploadFileInfo();
				}
			}
			if(is_array($info[0]) && !empty($info[0])){
				$savePath = $dirname . $info[0]['savename'];
			}else{
				alert('error', '上传失败', U('task/index'));
			};
			import("ORG.PHPExcel.PHPExcel");
			$PHPExcel = new PHPExcel();
			$PHPReader = new PHPExcel_Reader_Excel2007();
			if(!$PHPReader->canRead($savePath)){
				$PHPReader = new PHPExcel_Reader_Excel5();
			}
			$PHPExcel = $PHPReader->load($savePath);
			$currentSheet = $PHPExcel->getSheet(0);
			$allRow = $currentSheet->getHighestRow();
			for ($currentRow = 2;$currentRow <= $allRow;$currentRow++) {
				$data['subject'] = $currentSheet->getCell('B'.$currentRow)->getValue();
				$data['owner_role_id'] = $currentSheet->getCell('E'.$currentRow)->getValue();
				$data['due_date'] = strtotime($currentSheet->getCell('G'.$currentRow)->getValue());
				$data['status'] = $currentSheet->getCell('H'.$currentRow)->getValue();
				$data['priority'] = $currentSheet->getCell('I'.$currentRow)->getValue();
				$data['send_email'] = $currentSheet->getCell('J'.$currentRow)->getValue();
				$data['description'] = $currentSheet->getCell('K'.$currentRow)->getValue();
				$data['creator_role_id'] = $currentSheet->getCell('N'.$currentRow)->getValue();
				$data['create_time'] = strtotime($currentSheet->getCell('P'.$currentRow)->getValue());
				$data['update_time'] = strtotime($currentSheet->getCell('Q'.$currentRow)->getValue());
				$m_task->add($data);
			}
			alert('success', '导入成功', U('task/index'));
		}else{
			$this->display();
		}
	}

	public function analytics(){
		$m_task = M('Task');
		if($_GET['role']) {
			$role_id = intval($_GET['role']);
		}else{
			$role_id = 'all';
		}
		if($_GET['department'] & $_GET['department'] != 'all'){
			$department_id = intval($_GET['department']);
		}else{
			$department_id = D('RoleView')->where('role.role_id = %d', session('role_id'))->getField('department_id');
		}
		if($_GET['start_time']) $start_time = strtotime($_GET['start_time']);
		$end_time = $_GET['end_time'] ?  strtotime($_GET['end_time']) : time();
		if($role_id == "all") {
			$roleList = getRoleByDepartmentId($department_id);
			$role_id_array = array();
			foreach($roleList as $v2){
				$role_id_array[] = $v2['role_id'];
			}
			$where_role_id = array('in', implode(',', $role_id_array));
			$where_completion['owner_role_id'] = $where_role_id;
		}else{
			$where_completion['owner_role_id'] = $role_id;
		}
		if($start_time){
			$where_create_time = array(array('lt',$end_time),array('gt',$start_time), 'and');
			$where_completion['create_time'] = $where_create_time;
		}else{
			$where_completion['create_time'] = array('lt',$end_time);
		}
		
		$completion_count_array = array();
		$statusList = array("未启动", "推迟", "进行中", "完成");
		$where_completion['is_deleted'] = 0;
		$where_completion['is_close'] = 0;
		foreach($statusList as $v){
			$where_completion['status'] = $v;
			$target_count = $m_task ->where($where_completion)->count();
			$completion_count_array[] = '['.'"'.$v.'",'.$target_count.']';
		}

		$this->completion_count = implode(',', $completion_count_array);
		
		//统计表内容
		$role_id_array = array();
		if($role_id == "all"){
			if($department_id != "all"){
				$roleList = getRoleByDepartmentId($department_id);
				foreach($roleList as $v){
					$role_id_array[] = $v['role_id'];
				}
			}else{
				$role_id_array = getSubRoleId();
			}
		}else{
			$role_id_array[] = $role_id;
		}
		if($start_time){
			$create_time= array(array('lt',$end_time),array('gt',$start_time), 'and');
		}else{
			$create_time = array('lt',$end_time);
		}
		
		$own_count_total = 0;
		$new_count_total = 0;
		$late_count_total = 0;
		$deal_count_total = 0;
		$success_count_total = 0;
		$busi_customer_array = M('Business')->getField('customer_id', true);
		$busi_customer_id=implode(',', $busi_customer_array);
		foreach($role_id_array as $v){
			$user = getUserByRoleId($v);
			$own_count = $m_task->where(array('is_deleted'=>0,'isclose'=>0, 'owner_role_id'=>$v, 'create_date'=>$create_time))->count();
			$new_count = $m_task->where(array('is_deleted'=>0,'isclose'=>0,'status'=>'未启动', 'owner_role_id'=>$v, 'create_date'=>$create_time))->count();
			$late_count = $m_task->where(array('is_deleted'=>0,'isclose'=>0,'status'=>'推迟', 'owner_role_id'=>$v, 'create_date'=>$create_time))->count();
			$deal_count = $m_task->where(array('is_deleted'=>0,'isclose'=>0,'status'=>'进行中', 'owner_role_id'=>$v, 'create_date'=>$create_time))->count();
			$success_count =  $m_task->where(array('is_deleted'=>0,'isclose'=>0,'status'=>'完成', 'owner_role_id'=>$v, 'create_date'=>$create_time))->count();
			
			$reportList[] = array("user"=>$user,"new_count"=>$new_count,"late_count"=>$late_count,"own_count"=>$own_count,"success_count"=>$success_count,"deal_count"=>$deal_count);
			$late_count_total += $late_count;
			$own_count_total += $own_count;
			$success_count_total += $success_count;
			$deal_count_total += $deal_count;
			$new_count_total += $new_count;
		}
		$this->total_report = array("new_count"=>$new_count_total,"late_count"=>$late_count_total, "own_count"=>$own_count_total, "success_count"=>$success_count_total, "deal_count"=>$deal_count_total);
		$this->reportList = $reportList;
		
		$idArray = getSubRoleId();
		$roleList = array();
		foreach($idArray as $roleId){				
			$roleList[$roleId] = getUserByRoleId($roleId);
		}
		$this->roleList = $roleList;
		
		$departments = M('roleDepartment')->select();
		$department_id = D('RoleView')->where('role.role_id = %d', session('role_id'))->getField('department_id');
		$departmentList[] = M('roleDepartment')->where('department_id = %d', $department_id)->find();$departmentList = array_merge($departmentList, getSubDepartment($department_id,$departments,''));
		$this->assign('departmentList', $departmentList);
		$this->display();
	}
	
	public function tips(){
		$m_task = M('Task');
		$num = $m_task->where('owner_role_id = %d and isclose = 0 and status <> "完成" and is_deleted <> 1', session('role_id'))->count();
		$this->ajaxReturn($num,"",1);
	}
}