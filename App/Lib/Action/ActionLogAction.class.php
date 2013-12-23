<?php
class ActionLogAction extends Action{
//	public function _initialize(){
//		$action = array(
//			'permission'=>array('wxadd'),
//			'allow'=>array('view', 'delete', 'edit', 'index', 'mylog_view', 'mylog_edit', 'log_delete', 'mylog_add')
//		);
//		B('Authenticate', $action);
//	}
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
		$m_log = M('ActionLog');
		$by = isset($_GET['by']) ? trim($_GET['by']) : '';
		$where = array();
		$params = array();
		$order = "";
		$all_ids = getSubRoleId();
		switch ($by) {
			case 'today' : $where['create_time'] =  array('gt',strtotime(date('Y-m-d', time()))); break;
			case 'week' : $where['create_time'] =  array('gt',(strtotime(date('Y-m-d', time())) - (date('N', time()) - 1) * 86400)); break;
			case 'month' : $where['create_time'] = array('gt',strtotime(date('Y-m-01', time()))); break;
			case 'me' : $where['role_id'] = session('role_id'); break;
			case 'add' : $order = 'create_time desc';  break;
		}
		if (!isset($where['role_id'])) {
			$where['role_id'] = array('in',implode(',', getSubRoleId())); 
		}

		if(trim($_GET['module'])){
			$where['module_name'] = trim($_GET['module']);
		}
		if(trim($_GET['act'])){
			$where['action_name'] = trim($_GET['act']);
		}
		if ($_REQUEST["field"]) {
			$field = trim($_REQUEST['field']) == 'all' ? 'subject|content' : $_REQUEST['field'];
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'eq' : trim($_REQUEST['condition']);
			if	('create_time' == $field) {
				$search = strtotime($search);
			}
			$params = array('field='.$_REQUEST['field'], 'condition='.$condition, 'search='.trim($_REQUEST["search"]));
			
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
			$list = $m_log->where($where)->page($p.',10')->order('create_time desc')->select();
			$count = $m_log->where($where)->count();

			import("@.ORG.Page");
			$Page = new Page($count,10);
			if (!empty($_REQUEST['by'])){
				$params['by'] = 'by=' . trim($_REQUEST['by']);
			}
			if (!empty($_REQUEST['module'])) {
				$params['module'] = 'module_name=' . trim($_REQUEST['module']);
			}
			if (!empty($_REQUEST['act'])) {
				$params['act'] = 'action_name=' . trim($_REQUEST['act']);
			}
			$Page->parameter = implode('&', $params);
			$show = $Page->show();		
			$this->assign('page',$show);
		}
		foreach($list as $k => $v){
			$list[$k]['creator'] = getUserByRoleId($v['role_id']);
		}
		$d_role_view = D('RoleView');
		$this->role_list = $d_role_view->where('role.role_id in (%s)', implode(',', $below_ids))->select();
		$this->assign('list',$list);
		$this->alert = parseAlert();
		$this->display();
	}
	public function mylog_edit(){
		if($_GET['id']){
			$log = M('Log');
			$this->log =  $log->where('log_id = %d', $_GET['id'])->find();
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
	
}
