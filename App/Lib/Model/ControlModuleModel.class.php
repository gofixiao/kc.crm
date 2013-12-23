<?php 
	class ControlModuleModel extends Model{
		 protected $_validate = array(
			array('name', '', '模块已经存在！', 0, 'unique', 1), 
			array('name', 'require', '请填写模块名', 0, ''), 
		);
	}