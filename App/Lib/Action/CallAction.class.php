<?PHP 
class CallAction extends Action{
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array()
		);
		B('Authenticate', $action);
	}
	public function index(){
		$this->display();
	}
}
