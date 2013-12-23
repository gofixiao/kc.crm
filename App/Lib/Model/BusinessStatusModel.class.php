<?php 
	Class BusinessStatusModel extends Model{
		protected $_validate = array(
			array('name', '', '商机状态已经存在！', 0, 'unique', 1),
			array('name', 'require', '请填写状态名', 0, ''), 
		);
	}