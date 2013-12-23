<?php 
	class UserDepartmentModel extends Model{
		
		 protected $_validate = array(
			array('name', '', '部门已经存在！', 0, 'unique', 1), 
			array('name', 'require', '请填写部门名', 0, ''), 
			array('parent_id', 'require', '请选择父级部门', 0, ''), 
		);
	}