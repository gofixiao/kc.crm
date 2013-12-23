<?php
class LogAction extends Action{
	public function _initialize(){
		$action = array(
			'permission'=>array('wxadd'),
			'allow'=>array('add', 'view', 'delete', 'edit', 'index', 'mylog_view', 'mylog_edit', 'log_delete', 'mylog_add')
		);
		B('Authenticate', $action);
	}
	
	public function add(){
		if($_POST['submit']){
			$r = $_POST['r'];
			$module = $_POST['module'];
			$model_id = $_POST['id'];
			$m_r = M($r);
			$m_log = M('Log');
			$m_log->create();
			$m_log->category_id = 1;
			$m_log->create_date = time();
			$m_log->update_date = time();
			if($log_id = $m_log->add()){
				$m_id = $module . '_id';
				$data['log_id'] = $log_id;
				$data[$m_id] = $model_id;
				if($m_r -> add($data)){
					alert('success','添加日志成功！',$_SERVER['HTTP_REFERER']);
				}else{
					alert('error', '添加日志失败！',$_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '添加日志失败！',$_SERVER['HTTP_REFERER']);
			}
		} elseif ($_GET['r'] && $_GET['module'] && $_GET['id']) {
			$this -> r = $_GET['r'];
			$this -> module = $_GET['module'];
			$this -> model_id = $_GET['id'];
			$this->display();
				
		} else {
			alert('error', '参数错误！',$_SERVER['HTTP_REFERER']);
		}
	}
	//微信跳转页面
	public function wxadd(){
		if($_POST['subject']){
			$log = M('Log');
			$log->create();
			$log->create_date = time();
			$log->update_date = time();
			
			$log->role_id = $_GET['id'];
			if($log->add()){
				$this->success('日志添加成功!');
			}else{
				$this->error('日志添加失败!');
			}
		}else{
			$this->display();
		}
	}
	public function view(){
		if($_GET['id']){
			$log_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
			$m_log = M('Log');
			$log = $m_log->where('log_id = %d', $log_id)->find();
			$file_ids = M('rFileLog')->where('log_id = %d', $log_id)->getField('file_id', true);
			$log['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$file_count = 0;
			foreach ($log['file'] as $key=>$value) {
				$log['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$file_count ++;
			}
			$log['file_count'] = $file_count;
			$log['creator'] = getUserByRoleId($log['role_id']);
			
			$this->log =  $log;
			$this->alert = parseAlert();
			$this->display();
		}else{
			alert('error', '参数错误！',$_SERVER['HTTP_REFERER']);
		}
	}

	public function delete(){
		$log_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if (0 == $log_id){
			alert('error','参数错误',$_SERVER['HTTP_REFERER']);
		} else {
			if (isset($_GET['r']) && isset($_GET['id'])) {
				$m_r = M($_GET['r']);
				$m_log = M('log');
				
				if ($m_r->where('log_id = %d',$_GET['id'])->delete()) {
					if ($m_log->where('log_id = %d',$_GET['id'])->delete()) {
						alert('success','删除日志成功！',$_SERVER['HTTP_REFERER']);
					} else {
						alert('success','删除失败！请联系管理员！',$_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('success','删除失败！请联系管理员！',$_SERVER['HTTP_REFERER']);
				}
			} elseif (empty($_GET['r']) && isset($_GET['id'])){
				$m_log = M('Log');
				if ($m_log->where('log_id = %d',$_GET['id'])->delete()){
					alert('success','删除日志成功！',U('log/index'));
				} else {
					alert('success','删除失败！请联系管理员！',U('log/index'));
				}
			}
		}
	}
	
	
		public function index(){
		$m_log = M('Log');
		$m_comment = M('Comment');
		$by = isset($_GET['by']) ? trim($_GET['by']) : '';
		$where = array();
		$params = array();
		$order = "";
		$below_ids = getSubRoleId(false);
		$all_ids = getSubRoleId();
		$module = isset($_GET['module']) ? trim($_GET['module']) : '';
		switch ($by) {
			case 'today' : $where['create_date'] =  array('gt',strtotime(date('Y-m-d', time()))); break;
			case 'week' : $where['create_date'] =  array('gt',(strtotime(date('Y-m-d', time())) - (date('N', time()) - 1) * 86400)); break;
			case 'month' : $where['create_date'] = array('gt',strtotime(date('Y-m-01', time()))); break;
			case 'add' : $order = 'create_date desc';  break;
			case 'update' : $order = 'update_date desc';  break;
			case 'sub' : $where['role_id'] = array('in',implode(',', $below_ids)); break;
			case 'me' : $where['role_id'] = session('role_id'); break;
			default :  $where['role_id'] = array('in',implode(',', $all_ids)); break;
		}
		if ($_GET['r'] & $_GET['module']){
			$m_r = M($_GET['r']);
			$log_ids = $m_r->getField('log_id', true);
			$where['log_id'] = array('in', implode(',', $log_ids));
		}
		if (!isset($where['role_id'])) {
			$where['role_id'] = array('in',implode(',', getSubRoleId())); 
		}
		if(intval($_GET['type'])){
			$where['category_id'] = intval($_GET['type']);
		}		
		if ($_REQUEST["field"]) {
			$field = trim($_REQUEST['field']);
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);

			$condition = empty($_REQUEST['condition']) ? 'eq' : trim($_REQUEST['condition']);
			if	('create_date' == $field || 'update_date' == $field) {
				$search = strtotime($search);
			} elseif ('role_id' == $field) {
				$condtion = "is";
			}
			$params = array('field='.$_REQUEST['field'], 'condition='.$condition, 'search='.trim($_REQUEST["search"]));
			$field = trim($_REQUEST['field']) == 'all' ? 'subject|content' : $_REQUEST['field'];
			switch ($_REQUEST['condition']) {
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
		}
		
		if ($order) {
			$list = $m_log->where($where)->order($order)->limit(15)->select();
		} else {

			$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
			$list = $m_log->where($where)->page($p.',10')->order('create_date desc')->select();
			$count = $m_log->where($where)->count();
			import("@.ORG.Page");
			$Page = new Page($count,10);
			if (!empty($_REQUEST['by'])){
				$params['by'] = 'by=' . trim($_REQUEST['by']);
				
			}
			if (!empty($_REQUEST['r']) && !empty($_REQUEST['module'])) {
				$params['r'] = 'r=' . trim($_REQUEST['r']);
				$params['module'] = 'module=' . trim($_REQUEST['module']);
			}
			if (!empty($_REQUEST['type'])) {
				$params['type'] = 'type=' . trim($_REQUEST['type']);
			}
			$Page->parameter = implode('&', $params);
			$show = $Page->show();		
			$this->assign('page',$show);
		}
		foreach($list as $key=>$value){
			$list[$key]['creator'] = getUserByRoleId($value['role_id']);
			if($m_comment->where("module='log' and module_id=%d", $value['log_id'])->select()){
				$list[$key]['is_comment'] = 1;
			}
		}
		$this->category_list = M('LogCategory')->order('order_id')->select();
		//获取下级和自己的岗位列表,搜索用
		$d_role_view = D('RoleView');
		$this->role_list = $d_role_view->where('role.role_id in (%s)', implode(',', $below_ids))->select();
		//沟通日志  (日志类型中 type = 1 为沟通日志)
		if(intval($_GET['type']) == 1){
			$m_customer = M('Customer');
			$m_r_customer_log = M('RCustomerLog');
			$m_contacts = M('Contacts');
			$m_r_contacts_log = M('RContactsLog');
			$m_business = M('Business');
			$m_r_business_log = M('RBusinessLog');
			$m_task = M('Task');
			$m_r_task_log = M('RLogTask');
			$m_event = M('Event');
			$m_r_event_log = M('REventLog');
			foreach($list as $k=>$v){
				$r_customer_log = $m_r_customer_log->where('log_id = %d',$v)->find();
				if(!empty($r_customer_log)){
					$customer = $m_customer->where('customer_id = %d',$r_customer_log['customer_id'])->find();
					$list[$k]['customer_id'] = $customer['customer_id'];
					$list[$k]['customer_name'] = $customer['name'];
				}
				$r_contacts_log = $m_r_contacts_log->where('log_id = %d',$v)->find();
				if(!empty($r_contacts_log)){
					$contacts = $m_contacts->where('contacts_id = %d',$r_contacts_log['contacts_id'])->find();
					$list[$k]['contacts_id'] = $contacts['contacts_id'];
					$list[$k]['contacts_name'] = $contacts['name'];
				}
				$r_business_log = $m_r_business_log->where('log_id = %d',$v)->find();
				if(!empty($r_business_log)){
					$business = $m_business->where('business_id = %d',$r_business_log['business_id'])->find();
					$list[$k]['business_id'] = $business['business_id'];
					$list[$k]['business_name'] = $business['name'];
				}
				$r_task_log = $m_r_task_log->where('log_id = %d',$v)->find();
				if(!empty($r_task_log)){
					$task = $m_task->where('task_id = %d',$r_task_log['task_id'])->find();
					$list[$k]['task_id'] = $task['task_id'];
					$list[$k]['task_name'] = $task['subject'];
				}
				$r_event_log = $m_r_event_log->where('log_id = %d',$v)->find();
				if(!empty($r_event_log)){
					$event = $m_event->where('event_id = %d',$r_event_log['event_id'])->find();
					$list[$k]['event_id'] = $event['event_id'];
					$list[$k]['event_name'] = $event['subject'];
				}
			}
		}
		$this->assign('list',$list);
		$this->alert = parseAlert();
		$this->display();
	}
	
	public function mylog_view(){
		if($_GET['id']){
			if (in_array($log['role_id'], getSubRoleId())) alert('error', '对不起，您没有权限!', $_SERVER['HTTP_REFERER']);
			$log_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
			$m_log = M('Log');
			$m_customer = M('Customer');
			$m_r_customer_log = M('RCustomerLog');
			$m_contacts = M('Contacts');
			$m_r_contacts_log = M('RContactsLog');
			$m_business = M('Business');
			$m_r_business_log = M('RBusinessLog');
			$m_task = M('Task');
			$m_r_task_log = M('RLogTask');
			$m_event = M('Event');
			$m_r_event_log = M('REventLog');
			$log = $m_log->where('log_id = %d', $log_id)->find();
			$file_ids = M('rFileLog')->where('log_id = %d', $log_id)->getField('file_id', true);
			$log['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$log['creator'] = getUserByRoleId($log['role_id']);
			//日志所关联的模块
			$r_customer_log = $m_r_customer_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_customer_log)){
				$customer = $m_customer->where('customer_id = %d',$r_customer_log['customer_id'])->find();
				$log['customer_id'] = $customer['customer_id'];
				$log['customer_name'] = $customer['name'];
			}
			$r_contacts_log = $m_r_contacts_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_contacts_log)){
				$contacts = $m_contacts->where('contacts_id = %d',$r_contacts_log['contacts_id'])->find();
				$log['contacts_id'] = $contacts['contacts_id'];
				$log['contacts_name'] = $contacts['name'];
			}
			$r_business_log = $m_r_business_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_business_log)){
				$business = $m_business->where('business_id = %d',$r_business_log['business_id'])->find();
				$log['business_id'] = $business['business_id'];
				$log['business_name'] = $business['name'];
			}
			$r_task_log = $m_r_task_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_task_log)){
				$task = $m_task->where('task_id = %d',$r_task_log['task_id'])->find();
				$log['task_id'] = $task['task_id'];
				$log['task_name'] = $task['subject'];
			}
			$r_event_log = $m_r_event_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_event_log)){
				$event = $m_event->where('event_id = %d',$r_event_log['event_id'])->find();
				$log['event_id'] = $event['event_id'];
				$log['event_name'] = $event['subject'];
			}

			if (in_array($log['role_id'], getSubRoleId(false))) {
				if(!($log['comment_role_id'] > 0)){
					$this->comment_role_id = session('role_id');
				}
			}
			$pre = M('Log')->where('role_id = %d and category_id <> 1 and create_date < %d', $log['role_id'], $log['create_date'])->order('create_date desc')->limit(1)->find();
			if($pre) $this->pre_href = U('log/mylog_view', 'id='.$pre['log_id']);
			$next = M('Log')->where('role_id = %d and category_id <> 1 and create_date > %d', $log['role_id'], $log['create_date'])->limit(1)->find();
			if($next) $this->next_href = U('log/mylog_view', 'id='.$next['log_id']);
			$this->log =  $log;
			$this->comment_list = D('CommentView')->where('module = "log" and module_id = %d', $log['log_id'])->order('comment.create_time desc')->select();
			$this->alert = parseAlert();
			$this->display();
		}else{
			alert('error', '参数错误!', $_SERVER['HTTP_REFERER']);
		}
	}
	
	public function mylog_edit(){
		if($_GET['id']){
			$log_id = $_GET['id'];
			$m_log = M('Log');
			$log = $m_log->where('log_id = %d', $_GET['id'])->find();
			//日志所关联的模块
			$m_customer = M('Customer');
			$m_r_customer_log = M('RCustomerLog');
			$m_contacts = M('Contacts');
			$m_r_contacts_log = M('RContactsLog');
			$m_business = M('Business');
			$m_r_business_log = M('RBusinessLog');
			$m_task = M('Task');
			$m_r_task_log = M('RLogTask');
			$m_event = M('Event');
			$m_r_event_log = M('REventLog');

			
			$r_customer_log = $m_r_customer_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_customer_log)){
				$customer = $m_customer->where('customer_id = %d',$r_customer_log['customer_id'])->find();
				$log['customer_id'] = $customer['customer_id'];
				$log['customer_name'] = $customer['name'];
			}
			$r_contacts_log = $m_r_contacts_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_contacts_log)){
				$contacts = $m_contacts->where('contacts_id = %d',$r_contacts_log['contacts_id'])->find();
				$log['contacts_id'] = $contacts['contacts_id'];
				$log['contacts_name'] = $contacts['name'];
			}
			$r_business_log = $m_r_business_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_business_log)){
				$business = $m_business->where('business_id = %d',$r_business_log['business_id'])->find();
				$log['business_id'] = $business['business_id'];
				$log['business_name'] = $business['name'];
			}
			$r_task_log = $m_r_task_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_task_log)){
				$task = $m_task->where('task_id = %d',$r_task_log['task_id'])->find();
				$log['task_id'] = $task['task_id'];
				$log['task_name'] = $task['subject'];
			}
			$r_event_log = $m_r_event_log->where('log_id = %d',$log_id)->find();
			if(!empty($r_event_log)){
				$event = $m_event->where('event_id = %d',$r_event_log['event_id'])->find();
				$log['event_id'] = $event['event_id'];
				$log['event_name'] = $event['subject'];
			}

			if (in_array($log['role_id'], getSubRoleId(false))) {
				if(!($log['comment_role_id'] > 0)){
					$this->comment_role_id = session('role_id');
				}
			}
			$this->log =  $log;
			$this->alert = parseAlert();
			$this->display();
		} elseif ($_POST['submit']){
			$log = M('Log');
			$log -> create();
			if($log->save()){
				alert('success', '日志修改成功!', U('log/mylog_view','id='.$_POST['log_id']));
			}else{
				alert('error', '日志修改失败!', $_SERVER['HTTP_REFERER']);
			}
		}
	}
	public function edit(){
		if($_GET['id']){
			$log = M('Log');
			$this->log =  $log->where('log_id = %d', $_GET['id'])->find();
			$this->alert = parseAlert();
			$this->display();
		} elseif ($_POST['submit']){
			$log = M('Log');
			$log -> create();
			if($log->save()){
				alert('success', '日志修改成功!', $_SERVER['HTTP_REFERER']);
			}else{
				alert('error', '日志修改失败!', $_SERVER['HTTP_REFERER']);
			}
		}
	}
	
	public function mylog_add(){
		if($this->isPost()){
			if(!trim($_POST['subject'])) alert('error','日志标题不能为空!',U('log/index'));
			if(!trim($_POST['content'])) alert('error','日志内容不能为空!',U('log/index'));
			$log = M('Log');
			$log->create();
			$log->create_date = time();
			$log->update_date = time();
			$log->role_id = session('role_id');
			if($log->add()){
				if($_POST['submit'] == '保存'){
					alert('success','日志添加成功!',U('log/index'));
				}else{
					alert('success','日志添加成功!',U('log/mylog_add'));
				}
			}else{
				alert('error','日志添加失败!',U('log/index'));
			}
		}else{
			$this->current_time = time();
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function log_delete () {
		$model = array("rLeadsLog","rBusinessLog","rLogProduct","rCustomerLog","rContactsLog","rLogTask","rEventLog","rFinanceLog");
		if ($_GET['id']){
			$i = 0;
			$log_id = intval($_GET['id']);
			foreach ($model as $v){
				if(M($v)->where('log_id = %d',$log_id)->delete()) $i++;
			}
			if($i == 1){
				$message = M('log')->where('log_id = %d',$log_id)->delete() ? '成功删除了相关记录' : '删除失败';
				alert('success' , $message , U('log/index'));
			} elseif (M('log')->where('log_id = %d',$log_id)->delete()){
				alert('success', '删除成功！',  $_SERVER['HTTP_REFERER']);
			}
		} elseif (is_array($_POST['log_id'])) {
			$i = 0;
			foreach ($_POST['log_id'] as $v) {
				foreach ($model as $vv){
					if(M($vv)->where('log_id = %d',$v)->delete()){
						$i++;
					}
				}
			}
			if($i >= 1){
				$log_ids = implode(',', $_POST['log_id']);
				if(M('log')->where('log_id in (%s)', $log_ids)->delete()){
					alert('success', '成功删除了相关记录',  $_SERVER['HTTP_REFERER']);
				} else {
					alert('success', '删除失败',  $_SERVER['HTTP_REFERER']);
				}
			}else {
				$log_ids = implode(',', $_POST['log_id']);
				if(M('log')->where('log_id in (%s)', $log_ids)->delete()){
					alert('success', '成功删除了相关记录',  $_SERVER['HTTP_REFERER']);
				} else {
					alert('success', '删除失败',  $_SERVER['HTTP_REFERER']);
				}
			}
		}
		
	}

	public function category(){
		$m_category = M('LogCategory');
		$this->category_list = $m_category->order('order_id')->select();
		$this->alert=parseAlert();
		$this->display();
	}
	
	public function categoryAdd(){
		if ($this->isPost()) {
			$m_category = M('LogCategory');
			if($m_category->create()){
				if ($m_category->add()) {
					alert('success', '添加成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
			}
		} else {
			$this->alert=parseAlert();
			$this->display();
		}
	}
	
	public function categoryEdit(){
		$m_category = M('LogCategory');
		if ($this->isGet()) {
			$category_id = intval(trim($_GET['id']));
			$this->log_category = $m_category->where('category_id = %d', $category_id)->find();
			$this->display();
		} else {
			if ($m_category->create()) {
				if ($m_category->save()) {
					alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '数据无变化!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '修改失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
			}
		}
	}
	
	public function categoryDelete(){
		if ($_POST['category_id']) {
			$id_array = $_POST['category_id'];
			if (M('Log')->where('category_id <> 1 and category_id in (%s)', implode(',', $id_array))->select()) {
				alert('error', '删除失败,部分类别已添加日志请逐个删除!', $_SERVER['HTTP_REFERER']);
			} else {
				if (M('LogCategory')->where('category_id in (%s)', implode(',', $id_array))->delete()) {
					alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
				}
			}
		} elseif($_POST['old_id']){
			$old_id = intval($_POST['old_id']);
			$new_id = intval($_POST['new_id']);
			if($old_id && $new_id){
				if (M('LogCategory')->where('category_id <> 1 category_id = %d', $old_id)->delete()) {
					M('Log')->where('category_id = %d', $old_id)->setField('category_id', $new_id);
					M('LogCategory')->where('category_id = %d', $old_id)->setField('category_id', $new_id);
					alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '“模块日志”为系统自带特殊字段不能删除!', $_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '删除失败,参数错误!', $_SERVER['HTTP_REFERER']);
			}
		} else {
			$old_id = intval(trim($_GET['id']));
			$this->old_id = $old_id;
			$this->statusList = M('LogCategory')->where('category_id <> %d', $old_id)->select();
			$this->display();
		}
	}
	
	public function categorySort(){
		if ($this->isGet()) {
			$status = M('LogCategory');
			$a = 0;
			foreach (explode(',', $_GET['postion']) as $v) {
				$a++;
				$status->where('category_id = %d', $v)->setField('order_id',$a);
			}
			$this->ajaxReturn('1', '保存成功！', 1);
		} else {
			$this->ajaxReturn('0', '保存失败！', 1);
		}
	}
	
}
