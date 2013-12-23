<?php 
	class UserRoleModel extends Model{
		 protected $_validate = array(
			array('name', 'require', '请填写岗位名称', 0, ''), 
			array('department_id', 'require', '请选择岗位部门', 0, ''), 

		);
	
	}