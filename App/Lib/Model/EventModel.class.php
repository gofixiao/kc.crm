<?php 
    class EventModel extends Model{
	
        protected $_validate = array(
			array('subject', '', '该活动主题已经存在！', 0, 'unique', 1), 
		);
		
		protected $_auto = array(
			array('create_date', 'time', 1, 'function'),
		);
		
		
    }