<?php 
	class ControlModuleModel extends Model{
		 protected $_validate = array(
			array('name', '', '操作已经存在！', 0, 'unique', 1), 
			array('name', 'require', '请填写操作名', 0, ''), 
		);
	}