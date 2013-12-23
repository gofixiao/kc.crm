<?php
	class SmsAction extends Action{
		public function _initialize(){
			$action = array(
				'permission'=>array(''),
				'allow'=>array('')
			);
			B('Authenticate',$action);
		}
		public function index(){
			$templateList = M('SmsTemplate')->order('order_id')->select();
			$this->templateList = $templateList;
			$this->alert=parseAlert();
			$this->display();
		}
		
		public function add(){
			if($this->isPost()){
				$m_template = M('SmsTemplate');
				if(!$_POST['subject']) alert('error', '模板主题不能为空!', $_SERVER['HTTP_REFERER']);
				if(!$_POST['content']) alert('error', '模板内容不能为空!', $_SERVER['HTTP_REFERER']);				
				if($m_template->create()){
					if($m_template->add()){
						alert('success', '添加成功!', $_SERVER['HTTP_REFERER']);
					}else{
						alert('error', '添加失败!', $_SERVER['HTTP_REFERER']);
					}
				}else{
					alert('error', '添加失败!', $_SERVER['HTTP_REFERER']);
				}
			}else{
				$this->display();
			}
		}
		
		public function edit(){
			$m_template = M('SmsTemplate');
			if($this->isPost()){
				
				if(!$_POST['subject']) alert('error', '模板主题不能为空!', $_SERVER['HTTP_REFERER']);
				if(!$_POST['content']) alert('error', '模板内容不能为空!', $_SERVER['HTTP_REFERER']);				
				if($m_template->create()){
					if($m_template->save()){
						alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
					}else{
						alert('error', '修改失败!', $_SERVER['HTTP_REFERER']);
					}
				}else{
					alert('error', '修改失败!', $_SERVER['HTTP_REFERER']);
				}
			}else{
				if($_GET['id']){
					$this->template = $m_template->where('template_id = %d', intval($_GET['id']))->find();
					$this->display();
				}else{
					alert('error', '参数错误!', $_SERVER['HTTP_REFERER']);
				}
				
			}
		}
		
		public function delete(){
			if($this->isPost()){
				if(!empty($_POST['template_id'])){
					$m_template = M('SmsTemplate');
					$template_ids = $_POST['template_id'];
					if($m_template->where('template_id in (%s)', implode(',', $template_ids))->delete()){
						alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
					}else{
						alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
					}
				}else{
					alert('error', '参数错误!', $_SERVER['HTTP_REFERER']);
				}
			}else{
				$this->display();
			}
		}
		
		public function orderSort(){
			if ($this->isGet()) {
				$m_template = M('SmsTemplate');
				$a = 0;
				foreach (explode(',', $_GET['postion']) as $v) {
					$a++;
					$m_template->where('template_id = %d', $v)->setField('order_id',$a);
				}
				$this->ajaxReturn('1', '保存成功！', 1);
			} else {
				$this->ajaxReturn('0', '保存失败！', 1);
			}
		}
	}