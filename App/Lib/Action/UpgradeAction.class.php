<?php 
class UpgradeAction extends Action{
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array()
		);
		B('Authenticate', $action);
	}
	
	private $upgrade_site = "http://upgrade.5kcrm.com/";
	
	public function index(){	
		$params = array('version'=>C('VERSION'), 'release'=>C('RELEASE'), 'app'=>U('upgrade/index','','','',true));
		$info = sendRequest($this->upgrade_site . 'index.php?m=index&a=checkVersion', $params);
		if ($info){
			$this->ajaxReturn($info);
		} else {
			$this->ajaxReturn(0, '检查新版本出错', 0);
		}
	}



}