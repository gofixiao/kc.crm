<?php 
	class FinancialModel extends Model{
		 protected $_validate = array(
			array('name', '', '账务名称已经存在！', 0, 'unique', 1), 
			array('password', 'require', '请填写密码', 0, ''), 
		);
		
		protected $_auto = array(
			array('create_time', 'time', 1, 'function'),
		);
		
	}