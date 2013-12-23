<?PHP 
class AnnouncementAction extends Action{
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('changestatus')
		);
		B('Authenticate', $action);
	}

	public function index(){
		$m_announcement = M('Announcement'); // 实例化User对象
		import('@.ORG.Page');// 导入分页类
		$where = array();
		$params = array();
		if ($_REQUEST["field"]) {
			$field = trim($_REQUEST['field']) == 'all' ? 'title|content' : $_REQUEST['field'];
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
			if	('create_time' == $field || 'update_time' == $field) $search = is_numeric($search)?$search:strtotime($search);
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
			$params = array('field='.$field, 'condition='.$condition, 'search='.trim($_REQUEST["search"]));
		}
		$p = isset($_GET['p'])?$_GET['p']:1;
		$count = $m_announcement->count();// 查询满足要求的总记录数
		$list = $m_announcement->where($where)->order('create_time desc')->Page($p.',10')->select();
		$Page = new Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
		$Page->parameter = implode('&', $params);
		$userRole = M('userRole');
		foreach($list as $k => $v){
			$list[$k]['owner'] = D('RoleView')->where('role.role_id = %d', $v['role_id'])->find();
		}
		
		$this->assign('list',$list);// 赋值数据集
		$this->assign('page',$Page->show());// 赋值分页输出
		$this->alert=parseAlert();
		$this->display(); // 输出模板
	}
	public function add(){
		if($_POST['submit']){
			$title = trim($_POST['title']);
			if ($title == '' || $title == null) {
				alert('error','标题不能为空!',$_SERVER['HTTP_REFERER']);
			}
			$d_announcement = M('Announcement');
			if($d_announcement->create()){
				$d_announcement->role_id = session('role_id');
				$d_announcement->department = '('.implode('),(', $_POST['announce_department']).')';
				$d_announcement->create_time = time();
				$d_announcement->update_time = time();
				$d_announcement->add();
				if($_POST['submit'] == "保存") {
					alert('success', '公告添加成功！', U('announcement/index'));
				} else {
					alert('success', '添加成功！', U('announcement/add'));
				}
			}else{
				exit($d_announcement->getError());
			}

		}else{
			$m_department = M('RoleDepartment');
			$department_list = $m_department->select();	
			$this->assign('department_list', getSubDepartment(0,$department_list,'', 1));
			$this->alert = parseAlert();
			$this->display();
		}
	}
	public function view(){
		if($_GET['id']){
			$m_announcement = M('Announcement');
			$m_announcement->where('Announcement_id=%d',$_GET['id'])->setInc('hits');
			$announcement = $m_announcement->where('Announcement_id = %d ', $_GET['id'])->find();
			$announcement['owner'] = D('RoleView')->where('role.role_id = %d', $announcement['role_id'])->find();
			$m_userRole = M('userRole');
			$announcement['username']  = $m_userRole->where('role_id = %d',$announcement['role_id'])->getField('name');
			
			$m_department = M('RoleDepartment');
			$alldepartment_list = $m_department->select();	
			$department_list = getSubDepartment(0,$alldepartment_list,'', 1);
			$department_id_array = explode(',', $announcement['department']);
			foreach($department_list as $k=>$v){
				if(in_array('('.$v['department_id'].')', $department_id_array)) $department_list[$k]['checked'] = 'checked';
			}
			
			$pre = $m_announcement->where('create_time < %d', $announcement['create_time'])->order('create_time desc')->limit(1)->find();
			if($pre) $this->pre_href = U('announcement/view', 'id='.$pre['announcement_id']);
			$next = $m_announcement->where('create_time > %d', $announcement['create_time'])->limit(1)->find();
			if($next) $this->next_href = U('announcement/view', 'id='.$next['announcement_id']);
			
			$this->department_list = $department_list;
			$this->announcement = $announcement;
			$this->alert = parseAlert();
			$this->display();
		}else{
			$this->error("参数错误！");
		}
	}
	
	public function changeStatus(){
		$m_announcement = M('Announcement');
		$announcement_id = intval($_GET['id']);
		
		if ($announcement_id) {
			$announcement = $m_announcement->where('announcement_id = %d', $announcement_id)->find();
			if ($announcement['status'] == 1) {
				$m_announcement->where('announcement_id = %d', $announcement_id)->setField('status', 2);
				alert('success','修改成功，已停用!',$_SERVER['HTTP_REFERER']);
			} elseif($announcement['status'] == 2) {
				$m_announcement->where('announcement_id = %d', $announcement_id)->setField('status', 1);
				alert('success','修改成功，已发布!',$_SERVER['HTTP_REFERER']);
			} else {
				alert('success','系统错误请联系管理员!',$_SERVER['HTTP_REFERER']);
			}
		}else{
			alert('error','参数错误!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function edit(){
		if($this->isPost()){
			$title = trim($_POST['title']);
			if ($title == '' || $title == null) {
				alert('error','姓名不能为空!',$_SERVER['HTTP_REFERER']);
			}
			$m_announcement = M('Announcement');
			if($m_announcement->create()){
				$m_announcement->department = '('.implode('),(', $_POST['announce_department']).')';
				$m_announcement->update_time = time();
				if($m_announcement->save()){
					if($_POST['submit'] == "保存") {
						alert('success', '公告保存成功！', U('announcement/index'));
					} else {
						alert('success', '保存成功！请继续输入！', U('announcement/add'));
					}
				} else {
					alert('error', '修改失败,数据无变化!',U('announcement/index'));
				}
			}else{
				alert('error', '修改失败，请联系管理员!',U('announcement/index'));
			}
		}elseif($_GET['id']){
			$m_announcement = M('Announcement');
			$m_department = M('RoleDepartment');
			$department_list = getSubDepartment(0,$m_department->order('department_id')->select(),'', 1);
			$announcement = $m_announcement->where('announcement_id = %d',$_GET['id'])->find();
			$department_id_array = explode(',', $announcement['department']);

			foreach($department_list as $k=>$v){
				if(in_array('('.$v['department_id'].')', $department_id_array)) $department_list[$k]['checked'] = 'checked';
			}
			$this->assign('department_list', $department_list);
			$this ->announcement = $announcement;
			$this->display();
		}else{
			$this->error("参数错误！");
		}
	}
	public function delete(){
		$m_announcement = M('Announcement');
		$announcement_idarray = $_POST['announcement_id'];
		if (is_array($announcement_idarray)) {
			if (!session('?admin')) {
				foreach ($announcement_idarray as $v) {
					if (!$m_announcement->where('Announcement_id = %d and role_id = %d', $v, session('role_id'))->find()){
						alert('error', '您没有全部的权限，只有作者或管理员可以删除!',U('Announcement/index'));
					}
				}
			}
			if ($m_announcement->where('Announcement_id in ("%s")', join(',', $announcement_idarray))->delete()) {
				alert('success', '删除成功!',U('Announcement/index'));
			} else {
				$this->error('删除失败，联系管理员！');
			}
		} elseif($_GET['id']) {
			if (!session('?admin')) {
				if (!$m_announcement->where('Announcement_id = %d and role_id = %d', $_GET['id'], session('role_id'))->find()){
					alert('error', '您没有全部的权限，只有作者或管理员可以删除!',U('Announcement/index'));
				}
			}
			
			if($m_announcement->where('Announcement_id = %d', $_GET['id'])->delete()){
				alert('success', '删除成功!',U('Announcement/index'));
			}else{
				$this->error('删除失败，联系管理员！');
			}
		} else {
			alert('error', '请选择要删除的公告!',$_SERVER['HTTP_REFERER']);
		}
	}
	
}
