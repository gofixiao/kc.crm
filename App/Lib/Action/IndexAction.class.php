<?php 
// 
class IndexAction extends Action {
    
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('index','widget_edit','widget_delete','widget_add')
		);
		B('Authenticate', $action);
	}
	
	public function index(){
		$user = M('User');
		$m_announcement = M('announcement');
		$dashboard = $user->where('user_id = %d', session('user_id'))->getField('dashboard');
		$widget = unserialize($dashboard);		
		$this->widget = $widget;
		if (!F('smtp')) {
			alert('info', '<font style="color:red;">SMTP信息未配置 (无法使用密码找回功能)</font>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . U('setting/smtp') .'">点此设置</a>');
		}
		if (!F('defaultinfo')) {
			alert('info', '<font style="color:red;">系统默认信息未配置</font>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . U('setting/defaultinfo') .'">点此设置</a>');
		}
		$where['department'] = array('like', '%('.session('department_id').')%');
		$where['status'] = array('eq', 1);
		$this->announcement_list = $m_announcement->where($where)->select();
		$this->alert = parseAlert();
		$this->display();
	}
	
	public function widget_edit(){
		$user = M('User');
		$dashboard = $user->where('user_id = %d', session('user_id'))->getField('dashboard');
		$widgets = unserialize($dashboard);
		if(isset($_GET['id']) && $_GET['id']!=''){
			$this->edit_demo = $widgets[$_GET['id']];
			$this->display();
		} elseif(isset($_POST['widget_id']) && $_POST['widget_id']!='') {
			$title = $_POST['title']!='' && isset($_POST['title']) ? $_POST['title'] : '未定义组件';	
			$widgets[$_POST['widget_id']]['title'] = $title;
			$widgets[$_POST['widget_id']]['widget'] = $_POST['widget'];
			$widgets[$_POST['widget_id']]['style'] = $_POST['style'];
			$widgets[$_POST['widget_id']]['limit'] = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
			$newdashboard['dashboard'] = serialize($widgets);
			if($user->where('user_id = %d', session('user_id'))->save($newdashboard)){
				alert('success', '修改' .  $_POST['widget'] . '组件信息成功', U('index/index'));
			}else{
				alert('error', '修改' .  $_POST['widget'] . '组件信息无变化!', U('index/index'));
			}
		}
	}
	
	public function widget_delete(){
		if(isset($_GET['id']) && $_GET['id']!=''){
			$user = M('User');
			$dashboard = $user->where('user_id = %d', session('user_id'))->getField('dashboard');
			$widget = unserialize($dashboard);
			unset($widget[$_GET['id']]);
			foreach($widget as $key=>$value){
				$widget[$key]['id'] = $key;
			}
			$newdashboard['dashboard'] = serialize($widget);
			if($user->where('user_id = %d', session('user_id'))->save($newdashboard)){
				alert('success', '删除组件成功！', U('index/index'));
			}else{
				alert('error', '删除组件失败！',$_SERVER['HTTP_REFERER']);
			}
		}
	}
	
	//serialize  unserialize
	public function widget_add(){
		if($_POST['submit']){
			if($_POST['widget']){
				$user = M('User');
				$title = $_POST['title']!='' && isset($_POST['title']) ? $_POST['title'] : '未命名组件';
				$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;				
				$dashboard = $user->where('user_id = %d', session('user_id'))->getField('dashboard');
				$widget = unserialize($dashboard);
				if(!is_array($widget)){
					$widget = array();
				}
				array_unshift($widget, array('widget'=>$_POST['widget'], 'style'=>$_POST['style'], 'title'=>$title, 'limit'=>$limit));
				foreach($widget as $key=>$value){
					$widget[$key]['id'] = $key;
				}
				$newdashboard['dashboard'] = serialize($widget);
				if($user->where('user_id = %d', session('user_id'))->save($newdashboard)){
					alert('success', '添加组件成功', $_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '添加组件失败，请填写组件名!', $_SERVER['HTTP_REFERER']);
			}
		}else{
			$this->alert = parseAlert();
			$this->display();
		}
	}
}