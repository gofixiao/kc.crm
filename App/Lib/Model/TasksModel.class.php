<?php 
    class TasksModel extends Model{
	
        protected $_validate = array(
			array('subject', '', '该任务主题已经存在！', 0, 'unique', 1), 
		);
		
		protected $_auto = array(
			array('create_date', 'time', 1, 'function'),
		);
		
		
    }