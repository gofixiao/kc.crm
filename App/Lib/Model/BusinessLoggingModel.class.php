<?php 
    class BusinessLoggingModel extends Model{
		
        protected $_validate = array(
			array('title', 'require', '请输入标题', 0, ''), 		
		);
		protected $_auto = array(
			array('create_time','time',1,'function'),
		);
	}
		
