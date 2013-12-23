<?php 
    class UserModel extends Model{
	
		private  $_salt = "";
		
        protected $_validate = array(
			array('name', '', '帐号名称已经存在！', 0, 'unique', 1), 
		);
		
		protected $_auto = array(
			array('reg_time', 'time', 1, 'function'),
			array('reg_ip', 'get_client_ip', 1, 'function'),
			//array('last_login_time', 'time', 2, 'function'),
			array('salt','getSalt',1,'callback'),
			array('password','getPassword',1,'callback'),
		);
		
		
		protected function getSalt(){
			return $this->_salt = substr(md5(time()),0,4);			
		}
		protected function getPassword(){
			return md5(md5(trim($_POST["password"])) . $this->_salt);
		}
		
    }
		/*	$data['salt'] = substr(md5(time()), 0, 4);
			$data['password'] = md5(trim($_POST['password']).$data['salt']);
			*/