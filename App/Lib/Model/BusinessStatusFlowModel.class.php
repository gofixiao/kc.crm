<?php 
	class BusinessStatusFlowModel extends Model{
		protected $_validate = array(
			array('name','require','请填写状态流名称',),
		);
		
	}