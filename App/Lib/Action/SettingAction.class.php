<?php
	class SettingAction extends Action{
		public function _initialize(){
			$action = array(
				'permission'=>array('clearcache'),
				'allow'=>array('close', 'getbusinessstatuslist', 'getleadsstatuslist', 'getindustrylist', 'getsourcelist')
			);
			B('Authenticate',$action);
		}
		
		public function index(){
			$this->redirect('setting/defaultInfo');
		}
		public function openDebug(){
			$file_path = APP_PATH.'/Conf/debug.php';
			$result = file_put_contents($file_path, "<?php \n\r define ('APP_DEBUG',true);");
			if($result){
				$this->ajaxReturn(1,'',1);
			}else{
				$this->ajaxReturn(1,'',2);
			}
		}
		public function closeDebug(){
			$file_path = APP_PATH.'/Conf/debug.php';
			$result = file_put_contents($file_path, "<?php \n\r define ('APP_DEBUG',false);");
			if($result){
				$this->ajaxReturn(1,'',1);
			}else{
				$this->ajaxReturn(1,'',2);
			}
		}
		public function clearCache(){
			if($this->clear_Cache()){
                $this->ajaxReturn(1,'',1);
			}else{
                $this->ajaxReturn(1,'',0);
            }
			
		}
        protected function clear_Cache(){
			if(file_exists(RUNTIME_FILE)){
				@unlink(RUNTIME_FILE);
			}
			$cachedir=RUNTIME_PATH."/Cache/";
			$cachefieldsdir=RUNTIME_PATH."/Data/_fields/";
			$cachetempdir=RUNTIME_PATH."/Temp/";
            
			$cd = opendir($cachedir);
            while (($file = readdir($cd)) !== false) {
                @unlink($cachedir.$file);
            }
            closedir($cd);
            
            $cfd = opendir($cachefieldsdir);
            while (($file = readdir($cfd)) !== false) {
                @unlink($cachefieldsdir.$file);
            }
            closedir($cfd);
            
            $ctd = opendir($cachetempdir);
            while (($file = readdir($ctd)) !== false) {
                @unlink($cachetempdir.$file);
            }
			closedir($ctd);
			return true;
		}

		public function smtp(){
			if ($this->isAjax()) {
				if($_POST['address']){
					if (ereg('^([a-zA-Z0-9]+[_|_|.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|_|.]?)*[a-zA-Z0-9]+.[a-zA-Z]{2,3}$',$_POST['address'])){
						if (ereg('^([a-zA-Z0-9]+[_|_|.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|_|.]?)*[a-zA-Z0-9]+.[a-zA-Z]{2,3}$',$_POST['test_email'])){
							$smtp = array('MAIL_ADDRESS'=>$_POST['address'],'MAIL_SMTP'=>$_POST['smtp'],'MAIL_LOGINNAME'=>$_POST['loginName'],'MAIL_PASSWORD'=>$_POST['password'],'MAIL_CHARSET'=>'UTF-8','MAIL_AUTH'=>true,'MAIL_HTML'=>true);
							C($smtp,'smtp');
							//C('','smtp');
							import('@.ORG.Mail');
							$content ='这是一封悟空CRM系统自动生成测试邮件，如果你成功收到，证明悟空smtp设置成功！请勿回复';
							if(SendMail($_POST['test_email'],'悟空CRM邮箱测试',$content,'悟空CRM管理员')){
								$message = '发送成功！';
							} else {
								$message = '发送失败，信息错误！';
							}
						} else {
							$message = '测试收件箱格式错误!';
						}
					} else {
						$message = '邮箱格式错误！';
					}
					$this->ajaxReturn("", $message, 1);
				}else{
					if($_POST['uid'] && $_POST['passwd'] && $_POST['phone']){
						$result = sendtestSMS(trim($_POST['uid']), trim($_POST['passwd']), $_POST['phone']);
						if(strstr(trim($_POST['uid']), 'BST') === false){
							$message = '账号名格式错误!';
						}elseif($result == 0 || $result == 1){
							$message = '发送成功,请先保存设置，短信如有延迟，请稍候确认！';
						}elseif($result == -1 ){
							$message = '账号未注册，请联系悟空CRM客服!';
						}elseif($result == -3 ){
							$message = '密码错误!';
						}else{
							$message = '发送失败，请确认短信接口信息!';
						}
					}else{
						$message = '发送失败，请确认短信接口信息!';
					}
					$this->ajaxReturn("", $message, 1);
				}
			} elseif($this->isPost()) {
				$edit = false;
				$m_config = M('Config');
				if($_POST['address']){
					if(is_email($_POST['address'])){
						$smtp = array('MAIL_ADDRESS'=>$_POST['address'],'MAIL_SMTP'=>$_POST['smtp'],'MAIL_LOGINNAME'=>$_POST['loginName'],'MAIL_PASSWORD'=>$_POST['password'],'MAIL_CHARSET'=>'UTF-8','MAIL_AUTH'=>true,'MAIL_HTML'=>true);
						$smtp['name'] = 'smtp';
						$smtp['value'] =serialize($smtp);
						if($m_config->where('name = "smtp"')->find()){
							if($m_config->where('name = "smtp"')->save($smtp)){
								F('smtp',$smtp);
								$edit = true;
							}
						} else {
							if($m_config->add($smtp)){
								F('smtp',$smtp);
								$edit = true;
							}else{
								alert('error','添加失败，请联系管理员！',U('setting/smtp'));
							}
						}
					}else{
						alert('error','邮箱格式错误！',U('setting/smtp'));
					}
					
				}
				
				if($_POST['uid']){
					if(strstr(trim($_POST['uid']), 'BST') === false)	$message = '账号名格式错误!';
					$sms = array('uid'=>trim($_POST['uid']),'passwd'=>trim($_POST['passwd']),'sign_name'=>trim($_POST['sign_name']),'sign_sysname'=>trim($_POST['sign_sysname']));
					$sms['name'] = 'sms';
					$sms['value'] =serialize($sms);
					
					if($m_config->where('name = "sms"')->find()){
						if($m_config->where('name = "sms"')->save($sms)){
							F('sms',$sms);
							$edit = true;
						} 
					} else {
						if($m_config->add($sms)){
							F('sms',$sms);
							$edit = true;
						}else{
							alert('error','添加失败，请联系管理员！',U('setting/smtp'));
						}
					}
				}
				
				if($edit){
					alert('success','设置成功并保存！',U('setting/smtp'));
				}else{
					alert('error','数据无变化',U('setting/smtp'));
				}
			} else {
				$smtp = M('Config')->where('name = "smtp"')->getField('value');
				$sms = M('Config')->where('name = "sms"')->getField('value');
				$this->smtp = unserialize($smtp);
				$this->sms = unserialize($sms);
				$this->alert = parseAlert();
				$this->display();			
			}
		}
		
		public function source(){
			$m_source = M('InfoSource');
			$this->sourceList = $m_source->order('order_id')->select();
			$this->alert=parseAlert();
			$this->display();
		}
		
		public function sourceAdd(){
			if ($this->isPost()) {
				$m_source = M('InfoSource');
				if($m_source->create()){
					if ($m_source->add()) {
						alert('success', '添加成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '该状态名已存在!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$this->alert=parseAlert();
				$this->display();
			}
		}
		
		public function sourceEdit(){
			$m_source = M('InfoSource');
			if ($this->isGet()) {
				$source_id = intval(trim($_GET['id']));
				$this->source = $m_source->where('source_id = %d', $source_id)->find();
				$this->display();
			} else {
				if ($m_source->create()) {
					if ($m_source->save()) {
						alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '数据无变化!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '修改失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			}
		}
		
		public function sourceDelete(){
			if ($_POST['source_id']) {
				$id_array = $_POST['source_id'];
				if (M('customer')->where('source_id in (%s)', implode(',', $id_array))->select() || M('leads')->where('source_id in (%s)', implode(',', $id_array))->select()) {
					alert('error', '删除失败,该状态已被引用请逐个删除!', $_SERVER['HTTP_REFERER']);
				} else {
					if (M('InfoIndustry')->where('source_id in (%s)', implode(',', $id_array))->delete()) {
						alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
					}
				}
			} elseif($_POST['old_id']) {
				$old_id = intval($_POST['old_id']);
				$new_id = intval($_POST['new_id']);
				if (M('InfoSource')->where('source_id = %d', $old_id)->delete()) {
					M('Business')->where('source_id = %d', $old_id)->setField('source_id', $new_id);
					M('Leads')->where('source_id = %d', $old_id)->setField('source_id', $new_id);
					alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$old_id = intval(trim($_GET['id']));
				$this->old_id = $old_id;
				$this->sourceList = M('InfoSource')->where('source_id <> %d', $old_id)->select();
				$this->display();
			}
		}
		
		public function sourceSort(){
			if ($this->isGet()) {
				$m_source = M('InfoSource');
				$a = 0;
				foreach (explode(',', $_GET['postion']) as $v) {
					$a++;
					$m_source->where('source_id = %d', $v)->setField('order_id',$a);
				}
				$this->ajaxReturn('1', '保存成功！', 1);
			} else {
				$this->ajaxReturn('0', '保存失败！', 1);
			}
		}
			
		public function industry(){
			$m_status = M('InfoIndustry');
			$this->industryList = $m_status->order('order_id')->select();
			$this->alert=parseAlert();
			$this->display();
		}
		
		public function industryAdd(){
			if ($this->isPost()) {
				$m_status = M('InfoIndustry');
				if($m_status->create()){
					if ($m_status->add()) {
						alert('success', '添加成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '该状态名已存在!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$this->alert=parseAlert();
				$this->display();
			}
		}
		
		public function industryEdit(){
			$m_industry = M('InfoIndustry');
			if ($this->isGet()) {
				$industry_id = intval(trim($_GET['id']));
				$this->industry = $m_industry->where('industry_id = %d', $industry_id)->find();
				$this->display();
			} else {
				if ($m_industry->create()) {
					if ($m_industry->save()) {
						alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '数据无变化!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '修改失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			}
		}
		
		public function industryDelete(){
			if ($_POST['industry_id']) {
				$id_array = $_POST['industry_id'];
				if (M('customer')->where('industry_id in (%s)', implode(',', $id_array))->select() || M('leads')->where('industry_id in (%s)', implode(',', $id_array))->select()) {
					alert('error', '删除失败,该状态已被引用请逐个删除!', $_SERVER['HTTP_REFERER']);
				} else {
					if (M('InfoIndustry')->where('industry_id in (%s)', implode(',', $id_array))->delete()) {
						alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
					}
				}
			} elseif($_POST['old_id']){
				$old_id = intval($_POST['old_id']);
				$new_id = intval($_POST['new_id']);
				if (M('InfoIndustry')->where('industry_id = %d', $old_id)->delete()) {
					M('Leads')->where('industry_id = %d', $old_id)->setField('industry_id', $new_id);
					M('Customer')->where('industry_id = %d', $old_id)->setField('industry_id', $new_id);
					alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$old_id = intval(trim($_GET['id']));
				$this->old_id = $old_id;
				$this->industryList = M('InfoIndustry')->where('industry_id <> %d', $old_id)->select();
				$this->display();
			}
		}
		
		public function industrySort(){
			if ($this->isGet()) {
				$m_industry = M('InfoIndustry');
				$a = 0;
				foreach (explode(',', $_GET['postion']) as $v) {
					$a++;
					$m_industry->where('industry_id = %d', $v)->setField('order_id',$a);
				}
				$this->ajaxReturn('1', '保存成功！', 1);
			} else {
				$this->ajaxReturn('0', '保存失败！', 1);
			}
		}
		
		public function businessStatus(){
			$m_status = M('BusinessStatus');
			$this->statusList = $m_status->order('order_id')->select();
			$this->alert=parseAlert();
			$this->display();
		}
		
		public function businessStatusAdd(){
			if ($this->isPost()) {
				$m_status = M('BusinessStatus');
				if($m_status->create()){
					if ($m_status->add()) {
						alert('success', '添加成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '该状态名已存在!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$this->alert=parseAlert();
				$this->display();
			}
		}
		
		public function businessStatusEdit(){
			$m_status = M('BusinessStatus');
			if ($this->isGet()) {
				$status_id = intval(trim($_GET['id']));
				$this->status = $m_status->where('status_id = %d', $status_id)->find();
				$this->display();
			} else {
				if ($m_status->create()) {
					if ($m_status->save()) {
						alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '数据无变化!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '修改失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			}
		}
		
		public function businessStatusDelete(){
			if ($_POST['status_id']) {
				$id_array = $_POST['status_id'];
				if (M('Business')->where('status_id in (%s)', implode(',', $id_array))->select() || M('RBusinessStatus')->where('status_id in (%s)', implode(',', $id_array))->select()) {
					alert('error', '删除失败,该状态已被引用请逐个删除!', $_SERVER['HTTP_REFERER']);
				} else {
					if (M('BusinessStatus')->where('status_id in (%s)', implode(',', $id_array))->delete()) {
						alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
					}
				}
			} elseif($_POST['old_id']){
				$old_id = intval($_POST['old_id']);
				$new_id = intval($_POST['new_id']);
				if (M('BusinessStatus')->where('status_id = %d', $old_id)->delete()) {
					M('Business')->where('status_id = %d', $old_id)->setField('status_id', $new_id);
					M('RBusinessStatus')->where('status_id = %d', $old_id)->setField('status_id', $new_id);
					alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$old_id = intval(trim($_GET['id']));
				$this->old_id = $old_id;
				$this->statusList = M('BusinessStatus')->where('status_id <> %d', $old_id)->select();
				$this->display();
			}
		}
		
		public function businessStatusSort(){
			if ($this->isGet()) {
				$status = M('BusinessStatus');
				$a = 0;
				foreach (explode(',', $_GET['postion']) as $v) {
					$a++;
					$status->where('status_id = %d', $v)->setField('order_id',$a);
				}
				$this->ajaxReturn('1', '保存成功！', 1);
			} else {
				$this->ajaxReturn('0', '保存失败！', 1);
			}
		}

		public function leadsStatus(){
			$m_status = M('leadsStatus');
			$this->statusList = $m_status->order('order_id')->select();
			$this->alert=parseAlert();
			$this->display();
		}
		
		public function leadsStatusAdd(){
			if ($this->isPost()) {
				$m_status = M('leadsStatus');
				if($m_status->create()){
					if ($m_status->add()) {
						alert('success', '添加成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '该状态名已存在!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$this->alert=parseAlert();
				$this->display();
			}
		}
		
		public function leadsStatusEdit(){
			$m_status = M('leadsStatus');
			if ($this->isGet()) {
				$status_id = intval(trim($_GET['id']));
				$this->status = $m_status->where('status_id = %d', $status_id)->find();
				$this->display();
			} else {
				if ($m_status->create()) {
					if ($m_status->save()) {
						alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '数据无变化!', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '修改失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			}
		}
		
		public function leadsStatusDelete(){
			if ($_POST['status_id']) {
				$id_array = $_POST['status_id'];
				if (M('leads')->where('status_id in (%s)', implode(',', $id_array))->select()) {
					alert('error', '删除失败,该状态已被引用请逐个删除!', $_SERVER['HTTP_REFERER']);
				} else {
					if (M('leadsStatus')->where('status_id in (%s)', implode(',', $id_array))->delete()) {
						alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
					}
				}
			} elseif($_POST['old_id']){
				$old_id = intval($_POST['old_id']);
				$new_id = intval($_POST['new_id']);
				if (M('leadsStatus')->where('status_id = %d', $old_id)->delete()) {
					M('leads')->where('status_id = %d', $old_id)->setField('status_id', $new_id);
					alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				$old_id = intval(trim($_GET['id']));
				$this->old_id = $old_id;
				$this->statusList = M('leadsStatus')->where('status_id <> %d', $old_id)->select();
				$this->display();
			}
		}
		
		public function leadsStatusSort(){
			if ($this->isGet()) {
				$status = M('leadsStatus');
				$a = 0;
				foreach (explode(',', $_GET['postion']) as $v) {
					$a++;
					$status->where('status_id = %d', $v)->setField('order_id',$a);
				}
				$this->ajaxReturn('1', '保存成功！', 1);
			} else {
				$this->ajaxReturn('0', '保存失败！', 1);
			}
		}
		
		public function statusflow() {
			$this->flowList = M('BusinessStatusFlow')->select();
			$this->alert=parseAlert();
			$this->display();
		}
		
		public function statusflowAdd() {
			if($this->isPost()){
				foreach($_POST['status'] as $value){
					$a = 0;
					foreach($_POST['status'] as $value2){
						if($value == $value2){
							$a++;
						}
						if($a>1){
							alert('error', '状态不能重复！', $_SERVER['HTTP_REFERER']);
						}
					}
				}
				$flow = D('BusinessStatusFlow');
				$flow->create();
				$flow->data = serialize($_POST['status']);
				if ($flow->add()) {
					alert('success', '添加成功', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '添加失败!', $_SERVER['HTTP_REFERER']);
				}
			}else{
				$status = M('BusinessStatus');
				$this->statusList = $status->select();
				$this->display(); 
			}
		}
			
		public function statusflowEdit(){
			if ($this->isGet()) {
				$flow = M('BusinessStatusFlow')->where("flow_id =" . $_GET['id'])->find();
				$this->flow = $flow;
				$this->data = unserialize($flow['data']);
				$this->statusList = M('BusinessStatus')->select();
				$this->display(); 
			} elseif($this->isPost()) {
				foreach($_POST['status'] as $value){
					$a = 0;
					foreach($_POST['status'] as $value2){
						if($value == $value2){
							$a++;
						}
						if($a>1){
							alert('error', '状态流不能重复', $_SERVER['HTTP_REFERER']);
						}
					}
				}
				$flow = M('BusinessStatusFlow');
				$data['name'] = $_POST['name'];
				$data['description'] = $_POST['description'];
				$data['data'] = serialize($_POST['status']);
				if($flow->where('flow_id = %d',$_POST['flow_id'])->save($data)){
					alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
				}else{
					alert('error', '修改失败!', $_SERVER['HTTP_REFERER']);
				}
			}
		}
		
		public function use_flow(){
			if($this->isGet()){
				$flow_id = intval(trim($_GET['flow_id']));
				M('BusinessStatusFlow')->where('in_user = 1')->setField('in_use', 0);
				if (M('BusinessStatusFlow')->where('flow_id = %d', $flow_id)->setField('in_use', 1)) {
					alert('success', '修改成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '修改失败!', $_SERVER['HTTP_REFERER']);
				}
				
			}
		}
		
		public function statusflowDelete(){
			if ($_POST['flow_id']) {
				$id_array = $_POST['flow_id'];
				if (M('BusinessStatusFlow')->where('flow_id in (%s)', implode(',', $id_array))->delete()) {
					alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败!', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '参数错误!', $_SERVER['HTTP_REFERER']);
			}
		}
		
		
		public function weixin(){
			if($_POST['submit']){
				$data = array();
				if (isset($_FILES['WEIXIN_IMAGE']['size']) && $_FILES['WEIXIN_IMAGE']['size'] > 0) {
					import('@.ORG.UploadFile');
					$upload = new UploadFile();
					$upload->maxSize = 20000000;
					$upload->allowExts  = array('jpg', 'gif', 'png', 'jpeg');
					$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
					if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
						alert('error',"附件上传目录不可写!",U('setting/weixin'));
					}
					$upload->savePath = $dirname;
					if(!$upload->upload()) {
						alert('error',$upload->getErrorMsg(),U('setting/weixin'));
					}else{
						$info =  $upload->getUploadFileInfo();
					}
					if(is_array($info[0]) && !empty($info[0])){
						$data['WEIXIN_IMAGE'] = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/' . $info[0]['savename'];;
					}else{
						alert('error','二维码图片保存失败',U('setting/weixin'));
					}
				}
				$data['WEIXIN_TOKEN'] = trim($_POST['WEIXIN_TOKEN']);
				if ($data['WEIXIN_TOKEN'] == "") {
					alert('error','Token不能为空',U('setting/weixin'));
				} 
				
				$m_config = M('Config');
				$weixin = $m_config->where('name = "weixin"')->find();
				if($weixin){
					$default = unserialize($weixin['value']);					
					if (!isset($data['WEIXIN_IMAGE']) || $data['WEIXIN_IMAGE'] == "") {
						$data['WEIXIN_IMAGE'] = $default['WEIXIN_IMAGE'];
					}				
					if($m_config->where('name = "weixin"')->save(array('value'=>serialize($data)))){
						alert('success','设置成功并保存！',U('setting/weixin'));
					} else {
						alert('error','数据无变化',U('setting/weixin'));
					}
				} else {					
					if($m_config->add(array('value'=>serialize($data), 'name'=>'weixin'))){
						alert('success','设置成功并保存！',U('setting/weixin'));
					}else{
						alert('error','设置失败，请联系管理员',U('setting/weixin'));
					}
				}
			}else{
				$weixin = M('Config')->where('name = "weixin"')->getField('value');
				$this->weixin = unserialize($weixin);
				$this->alert = parseAlert();
				$this->display();
			}
		}
		
		public function defaultinfo(){
			if($this->isGet()){
				$defaultinfo = M('Config')->where('name = "defaultinfo"')->getField('value');
				$this->defaultinfo = unserialize($defaultinfo);
				$this->alert = parseAlert();
				$this->display();
			}elseif($this->isPost()){
				$m_config = M('Config');
				if (isset($_FILES['logo']['size']) && $_FILES['logo']['size'] > 0) {
					import('@.ORG.UploadFile');
					$upload = new UploadFile();
					$upload->maxSize = 20000000;
					$upload->allowExts  = array('jpg', 'gif', 'png', 'jpeg');
					$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
					if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
						alert('error',"附件上传目录不可写!",U('setting/defaultinfo'));
					}
					$upload->savePath = $dirname;
					if(!$upload->upload()) {
						alert('error',$upload->getErrorMsg(),U('setting/defaultinfo'));
					}else{
						$info =  $upload->getUploadFileInfo();
					}
					if(is_array($info[0]) && !empty($info[0])){
						$data['logo'] = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/' . $info[0]['savename'];;
					}else{
						alert('error','Logo保存失败',U('setting/defaultinfo'));
					}
				}
				
				
				$data['name'] = trim($_POST['name']);
				if ($data['name'] == "") {
					alert('error','系统名称不能为空',U('setting/defaultinfo'));
				}
				$data['description'] = trim($_POST['description']);
				$data['state'] = trim($_POST['state']);
				$data['city'] = trim($_POST['city']);
				$data['allow_file_type'] = trim($_POST['allow_file_type']);
				$data['contract_alert_time'] = intval(trim($_POST['contract_alert_time']));
				$data['task_model'] = trim($_POST['task_model']);
				
				$m_config = M('Config');
				$defaultinfo = $m_config->where('name = "defaultinfo"')->find();
				if($defaultinfo){
					$default = unserialize($defaultinfo['value']);					
					if (!isset($data['logo']) || $data['logo'] == "") {
						$data['logo'] = $default['logo'];
					}				
					if($m_config->where('name = "defaultinfo"')->save(array('value'=>serialize($data)))){
						F('defaultinfo',$data);
						alert('success','设置成功并保存！',U('setting/defaultinfo'));
					} else {
						alert('error','数据无变化',U('setting/defaultinfo'));
					}
				} else {
					if($m_config->add(array('value'=>serialize($data), 'name'=>'defaultinfo'))){
						F('defaultinfo',$data);
						alert('success','设置成功并保存！',U('setting/defaultinfo'));
					}else{
						alert('error','设置失败，请联系管理员',U('setting/defaultinfo'));
					}
				}
			}
		}
		
		public function getBusinessStatusList(){
			$statusList = M('BusinessStatus')->order('order_id')->select();
			$this->ajaxReturn($statusList, '', 1);
		}
		
		public function getLeadsStatusList(){
			$statusList = M('LeadsStatus')->order('order_id')->select();
			$this->ajaxReturn($statusList, '', 1);
		}
		public function getSourceList(){
			$statusList = M('InfoSource')->order('order_id')->select();
			$this->ajaxReturn($statusList, '', 1);
		}
		public function getIndustryList(){
			$statusList = M('InfoIndustry')->order('order_id')->select();
			$this->ajaxReturn($statusList, '', 1);
		}
		
		public function fields(){
            $model = $this->_get('model','trim','customer');
			$fields = M('fields')->where(array('model'=>$model))->order('order_id ASC')->select();
            $this->assign('model',$model);
            $this->assign('fields',$fields);
			$this->alert=parseAlert();
			$this->display();
		}
		
		public function indexShow(){
			$field = M('fields');
			$field_id = $this->_request('field_id','intval',0);
            if($field_id == 0) alert('error','参数错误',$_SERVER['HTTP_REFERER']);
            $field_info = $field->where(array('field_id'=>$field_id))->find();
			if($field_info['in_index']) {
				if($field ->where('field_id = %d', $field_id)->setField('in_index', 0)){
					alert('success','修改成功', $_SERVER['HTTP_REFERER']);
				}else{
					alert('error','修改成功',$_SERVER['HTTP_REFERER']);
				}
			}else{
				if($field ->where('field_id = %d', $field_id)->setField('in_index', 1)){
					alert('success','修改成功',$_SERVER['HTTP_REFERER']);
				}else{
					alert('error','修改成功',$_SERVER['HTTP_REFERER']);
				}
			}
		}
		
        public function fieldAdd(){
            $field = M('fields');
            if($this->isPost()){
                $field_model = D('Field');
                $data['model']         = $this->_post('model'); //模块名称
                $data['field']         = $this->_post('field'); //字段名称
                $data['form_type']     = $this->_post('form_type'); //字段类型
                $data['default_value'] = $this->_post('default_value');  //默认值
                $data['max_length']    = $this->_post('max_length');
                $data['is_main']       = $this->_post('is_main');
                if($field->where(array('field'=>$data['field'],'model'=>array(array('eq',$data['model']),array('eq',''),'OR')))->find()){
                    alert('error','该字段名已存在！',$_SERVER['HTTP_REFERER']);
                }
                if($field_model->add($data) !== false){
                    $field->create();
                    if($this->_post('form_type') == 'box'){
						$setting = $this->_post('setting');
                        $field->setting = 'array(';
                        $field->setting .= "'type'=>'$setting[boxtype]','data'=>array(";
                        $i = 0;
                        $options = explode(chr(10),$setting['options']);
                        $s = array();
						foreach($options as $v){
							$v = trim(str_replace(chr(13),'',$v));
                            if($v != '' && !in_array($v ,$s)){
                                $i++;
                                $field->setting .= "$i=>'$v',";
                                $s[] = $v;
                            }
						}
                        
                        $field->setting = substr($field->setting,0,strlen($field->setting) -1 ) .'))';
                    }
                    $field->add();
                    $this->clear_Cache();
                    //die($field_model->_sql());
                    alert('success','添加自定义字段成功',$_SERVER['HTTP_REFERER']);
                }else{
                    if($error = $field_model->getError()){
                        alert('error',$error,$_SERVER['HTTP_REFERER']);
                    }else{
                        alert('error','添加自定义字段失败，请重新操作',$_SERVER['HTTP_REFERER']);
                    }
                }
            }else{
                $this->assign('model',$this->_get('model','trim','customer'));
                $this->alert = parseAlert();
				$this->display();
            }
        }
        public function fieldEdit(){
            $field = M('fields');
            $field_id = $this->_request('field_id','intval',0);
            if($field_id == 0) alert('error','参数错误',$_SERVER['HTTP_REFERER']);
            $field_info = $field->where(array('field_id'=>$field_id))->find();
            if($field_info['operating'] == 2) die('系统固定字段，禁止修改');
            if($this->isPost()){
                $field_model = D('Field');
                $data['model']         = $field_info['model']; //模块名称
                $data['field']         = $field_info['operating'] == 0 ? $this->_post('field') : $field_info['field']; //字段名称
                $data['field_old']     = $field_info['field']; //字段名称
				$data['form_type']     = $field_info['form_type']; //字段类型
                $data['default_value'] = $this->_post('default_value');  //默认值
                $data['max_length']    = $this->_post('max_length');
                $data['is_main']       = $field_info['is_main'];
				
				
				
                if($field->where(array('field'=>$data['field'],'model'=>array(array('eq',$data['model']),array('eq',''),'OR'),'field_id'=>array('neq',$field_id)))->find()){
                    alert('error','字段名重复',$_SERVER['HTTP_REFERER']);
                }
                if($field_model->save($data) !== false){
                    $field->create();
					if($field_info['form_type'] == 'box'){
                        eval('$field_info["setting"] = '.$field_info["setting"].';');
                        $boxtype = $field_info['setting']['type'];
						$setting = $this->_post('setting');
						$field->setting = 'array(';
						$field->setting .= "'type'=>'$boxtype','data'=>array(";
						$i = 0;
						$options = explode(chr(10),$setting['options']);
                        $s = array();
						foreach($options as $v){
							$v = trim(str_replace(chr(13),'',$v));
                            if($v != '' && !in_array($v ,$s)){
                                $i++;
                                $field->setting .= "$i=>'$v',";
                                $s[] = $v;
                            }
						}
						
						$field->setting = substr($field->setting,0,strlen($field->setting) -1 ) .'))';
					}
                    $field->save();
                    $this->clear_Cache();
                    alert('success','修改自定义字段成功', $_SERVER['HTTP_REFERER']);
                }else{
                    if($error = $field_model->getError()){
                        alert('error',$error,$_SERVER['HTTP_REFERER']);
                    }else{
                        alert('error','修改自定义字段失败，请重新操作',$_SERVER['HTTP_REFERER']);
                    }
                }
            }else{

                if($field_info['form_type'] == 'box'){
                    eval('$field_info["setting"] = '.$field_info["setting"].';');
					$field_info['form_type_name'] = '选项';
                    $field_info["setting"]['options'] = implode(chr(10),$field_info["setting"]['data']);
                }else if($field_info['form_type'] == 'editor'){
					$field_info['form_type_name'] = '编辑器';
                }else if($field_info['form_type'] == 'text'){
					$field_info['form_type_name'] = '单行文本';
                }else if($field_info['form_type'] == 'textarea'){
					$field_info['form_type_name'] = '多行文本';
                }else if($field_info['form_type'] == 'datetime'){
					$field_info['form_type_name'] = '日期';
                }else if($field_info['form_type'] == 'number'){
					$field_info['form_type_name'] = '数字';
                }else if($field_info['form_type'] == 'floatnumber'){
					$field_info['form_type_name'] = '小数';
                }else if($field_info['form_type'] == 'address'){
					$field_info['form_type_name'] = '地址';
                }else if($field_info['form_type'] == 'phone'){
					$field_info['form_type_name'] = '电话';
                }else if($field_info['form_type'] == 'mobile'){
					$field_info['form_type_name'] = '手机';
                }else if($field_info['form_type'] == 'email'){
					$field_info['form_type_name'] = '邮箱';
                }
                $this->assign('fields',$field_info);
                $this->assign('models',array('customer'=>'客户','business'=>'商机','contacts'=>'联系人'));
                $this->alert = parseAlert();
				$this->display();
            }
        }
        public function fieldDelete(){
            $field = M('fields');
            if($this->isPost()){
                $field_id = is_array($_POST['field_id']) ? implode(',', $_POST['field_id']) : '';
                if ('' == $field_id) {
                    alert('error', '您没有选择任何记录！', $_SERVER['HTTP_REFERER']);
                    die;
                } else {
					$where['field_id'] = array('in',$field_id);
					$where['operating'] = array('not in', array(3,0));
					
                    $field_info = $field->where($where)->select();
                    if($field_info){
                        alert('error', '您选择有系统固定字段，禁止删除，请重新操作！', $_SERVER['HTTP_REFERER']);
                    }else{
                        $field_infos = $field->where(array('field_id'=>array('in',$field_id)))->select();
                        foreach($field_infos as $field_info){
                            $field_model = D('Field');
                            $data['model']         = $field_info['model']; //模块名称
                            $data['field']         = $field_info['field']; //字段名称
                            $data['is_main']       = $field_info['is_main'];
                            $field_model->delete($data);
                            $field->where(array('field_id'=>$field_info['field_id']))->delete();
                        }
                        $this->clear_Cache();
                        alert('success','删除自定义字段成功',$_SERVER['HTTP_REFERER']);
                    }
                }
            }else{
                $field_id = $this->_get('field_id','intval',0);
                if($field_id == 0) die('参数错误');
                $field_info = $field->where(array('field_id'=>$field_id))->find();
                if($field_info['operating'] != 0) die('系统固定字段，禁止删除');
                $field_model = D('Field');
                $data['model']         = $field_info['model']; //模块名称
                $data['field']         = $field_info['field']; //字段名称
                $data['is_main']       = $field_info['is_main'];
                if($field_model->delete($data) !== false){
                    $field->where(array('field_id'=>$field_id))->delete();
                    $this->clear_Cache();
                    alert('success','删除自定义字段成功',$_SERVER['HTTP_REFERER']);
                }else{
                    alert('error','删除自定义字段失败，请重新操作',$_SERVER['HTTP_REFERER']);
                }
            }
            
        }
        public function fieldsort(){	
			if(isset($_GET['postion'])){
				$fields = M('fields');
				foreach(explode(',', $_GET['postion']) AS $k=>$v) {
					$data = array('field_id'=> $v, 'order_id'=>$k);
					$fields->save($data);
				}
				$this->ajaxReturn('1', '保存成功！', 1);
			} else{
				$this->ajaxReturn('0', '保存失败！', 1);
			}
		}
        public function boxField(){
            $field_list = M('Fields')->where(array('model'=>$this->_get('model'),'field'=>$this->_get('field')))->getField('setting');
            eval('$field_list = '.$field_list .';');
            $this->ajaxReturn($field_list['data'], $field_list['type'], 1);
        }
		
		public function sendSms(){
			if($this->isPost()){
				$phoneNum = trim($_POST['phoneNum']);
				$message = trim($_POST['smsContent']);
				if($_POST['settime']){
					$send_time = strtotime(trim($_POST['sendtime']));
					if($send_time > time()){
						$sendtime = date('YmdHis',$send_time);
					}
				}
				$current_sms_num = getSmsNum();
				if(!F('sms')) alert('success','请联系管理员确认短信接口配置',$_SERVER['HTTP_REFERER']);
				$phoneNum = str_replace(" ","",$phoneNum);
				$phone_array = explode(chr(10),$phoneNum);
				if(sizeof($phone_array) > 0){
					//if(sizeof($phone_array) > $current_sms_num) alert('error','短信余额不足，请联系管理员，及时充值!',$_SERVER['HTTP_REFERER']);
				}
				$fail_array = array();
				$success_array = array();
				if($phoneNum && $message){		
					if(strpos($message,'{$name}',0) === false){
						foreach($phone_array as $k=>$v){
							if($v){
								$phone = substr($v,0,11);
								if(is_phone($phone)){
									$success_array[] = $phone;
								}else{
									$fail_array[] = $v;
								}
							}
						}
						if(!empty($fail_array)){
							$fail_message = '部分号码格式不正确，导致发送失败;具体如下:'.implode(',', $fail_array);
						}
						//echo '发送成功!';die();
						$result = sendGroupSMS(implode(',', $success_array),$message,'sign_name', $sendtime);
						if($result == 0 || $result == 1){
							alert('success','发送成功,由于网络原因短信会略有延迟！'.$fail_message,$_SERVER['HTTP_REFERER']);
						}else{
							alert('error','错误代码:'.$result.'请联系管理员确认短信接口配置',$_SERVER['HTTP_REFERER']);
						}
					}else{
						foreach($phone_array as $k=>$v){
							$real_message = $message;
							$name = ''; 
							if($v){
								$no = str_replace(" ","",$v);
								$phone = substr($no,0,11);
								if(is_phone($phone)){
									if(strpos($v,',',0) === false){
										$info_array = explode('，', $v);
									}else{
										$info_array = explode(',', $v);
									}
									$real_message = str_replace('{$name}',$info_array[1],$real_message);
									$result = sendSMS($phone, $real_message, 'sign_name', $sendtime);
									if($result<0 && $k==0){
										alert('error','错误代码:'.$result.'请联系管理员确认短信接口配置',$_SERVER['HTTP_REFERER']); 
									}
								}else{
									$fail_array[] = $v;
								}
							}
						}
						
						if(!empty($fail_array)){
							$fail_message = '部分号码格式不正确，导致发送失败;具体如下:'.implode(',', $fail_array);
						}
						alert('success','发送成功,由于网络原因短信会略有延迟！'.$fail_message,U('setting/sendsms'));
						
					}
				}else{
					alert('error','信息不完整，请确认短信内容和收件人手机号!',$_SERVER['HTTP_REFERER']);
				}
			}else{
				$current_sms_num = getSmsNum();
				
				$model = trim($_GET['model']);
				if($model == 'customer'){
					$customer_ids = trim($_GET['customer_ids']);
					if($customer_ids){
						$contacts_ids = M('RContactsCustomer')->where('customer_id in (%s)', $customer_ids)->getField('contacts_id', true);
						$contacts_ids = implode(',', $contacts_ids);
						$contacts = D('ContactsView')->where('contacts.contacts_id in (%s)', $contacts_ids)->select();
						$this->contacts = $contacts;
					}else{
						alert('error','请选择要发送短信的目标客户!',$_SERVER['HTTP_REFERER']);
					}
				}elseif($model == 'contacts'){
					$contacts_ids = trim($_GET['contacts_ids']);
					if(!$contacts_ids) alert('error','请选择要发送短信的目标联系人!',$_SERVER['HTTP_REFERER']);
					$contacts = D('ContactsView')->where('contacts.contacts_id in (%s)', $contacts_ids)->select();
					$this->contacts = $contacts;
				}elseif($model == 'leads'){
					$d_v_leads = D('LeadsView');
					$leads_ids = trim($_GET['leads_ids']);
					$where['leads_id'] = array('in',$leads_ids);
					$customer_list = $d_v_leads->where($where)->select();
					$contacts = array();
					foreach ($customer_list as $k => $v) {
						$contacts[] = array('name'=>$v['contacts_name'], 'customer_name'=>$v['name'], 'telephone'=>trim($v['mobile']));
					}
					$this->contacts = $contacts;
				}
				$this->templateList = M('SmsTemplate')->order('order_id')->select();
				$this->alert = parseAlert();
				$this->current_sms_num = $current_sms_num;
				$this->display();
			}
		}
		public function sendemail(){
			if($this->isPost()){
				C(F('smtp'),'smtp');
				import('@.ORG.Mail');
				$emails = trim($_POST['emails']);
				$title = trim($_POST['title']);
				$content = trim($_POST['content']);
				$url = $this->_server('HTTP_HOST');
				preg_match_all('/<a(.*?)href="(\/Uploads.+?)">(.*?)<\/a>/i',$content,$str_array);
				foreach($str_array as $v){
					$content = str_replace($str_array[0],'',$content);
				}
				if(strpos($content,'/Uploads')!==false){
					$content = str_replace('/Uploads','http://'.$url.'/Uploads',$content);
				}
//				preg_match_all('/src="(.+?)"/i',$content,$img_array);
				if(!F('smtp')) alert('error','smtp未设置，请联系管理员！',$_SERVER['HTTP_REFERER']);
				$fail_array = array();
				$success_array = array();
				$emails = str_replace(" ","",$emails);
				$emails_array = explode(chr(10),$emails);
				if($emails && $content && $title){
					foreach($emails_array as $k=>$v){
						$email='';
						$str_content='';
						$email_array = array();
						if($v){
							if(strpos($v,',') !== false || strpos($v,'，')!==false){
								$email_array = strpos($v,',') ? explode(',',$v) : explode('，',$v);
								$email = trim($email_array[0]);
								$str_content = str_replace('{name}',$email_array[1],$content);
							}else{
								$email = trim($v);
								$str_content = $content;
							}
							$str_content =(strpos($content,'{name}') !== false) ? str_replace('{name}',$email_array[1],$content) :$content;
							if(is_email($email)){
								$old_array[$email] = $v;
								$success_array[]=array('email'=>$email,'content'=>$str_content);
							}else{
								$fail_array[] = $v;
							}
						}
					}
					if(!empty($fail_array)){
						$fail_message = '邮箱格式不正确，具体如下:'.implode(',', $fail_array);
					}
					$i=0;
					foreach($success_array as $value){
						$result = bsendemail($value['email'],$title,$value['content'],$str_array[3],true);
						if($result){
							$i++;
						}else{
							$fail_result .= '未知原因未送达,请检查邮箱是否存在:"'.$old_array[$value['email']].'"<br>';
						}
					}
					if($i>0)
					alert('success','发送成功,由于网络原因会略有延迟！'.$fail_message.'<br>'.$fail_result,$_SERVER['HTTP_REFERER']);
					else
					alert('error','发送失败,请联系管理员'.$fail_message.'<br>'.$fail_result,$_SERVER['HTTP_REFERER']);
				}else{
					alert('error','输入信息不完整！',$_SERVER['HTTP_REFERER']);
				}
			}else{
				$model = trim($_GET['model']);
				if($model == 'customer'){
					$customer_ids = trim($_GET['customer_ids']);
					if($customer_ids){
						if($customer_ids == 'all'){
							$contacts = D('ContactsView')->select();
						}else{
							$contacts_ids = M('RContactsCustomer')->where('customer_id in (%s)', $customer_ids)->getField('contacts_id', true);
							$contacts_ids = implode(',', $contacts_ids);
							$contacts = D('ContactsView')->where('contacts.contacts_id in (%s)', $contacts_ids)->select();
						}
						$this->contacts = $contacts;
					}else{
						alert('error','请选择要发送邮件的目标客户!',$_SERVER['HTTP_REFERER']);
					}
				}elseif($model == 'contacts'){
					$contacts_ids = trim($_GET['contacts_ids']);
					if(!$contacts_ids) alert('error','请选择要发送邮件的目标联系人!',$_SERVER['HTTP_REFERER']);
					$contacts = D('ContactsView')->where('contacts.contacts_id in (%s)', $contacts_ids)->select();
					$this->contacts = $contacts;
				}elseif($model == 'leads'){
					$d_v_leads = D('LeadsView');
					$leads_ids = trim($_GET['leads_ids']);
					if('all' != $leads_ids){$where['leads_id'] = array('in',$leads_ids);}
					$customer_list = $d_v_leads->where($where)->select();
					$contacts = array();
					foreach ($customer_list as $k => $v) {
						$contacts[] = array('name'=>$v['contacts_name'], 'customer_name'=>$v['name'], 'email'=>trim($v['email']));
					}
					$this->contacts = $contacts;
				}
				$this->templateList = M('EmailTemplate')->order('order_id')->select();
				$this->alert = parseAlert();
				$this->display();
			}
		}
	}