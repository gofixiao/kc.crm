<?php 
class CustomerAction extends Action {

	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('getcustomerlist','analytics', 'validate','check', 'fenpei', 'revert','changecontent')
		);
		B('Authenticate', $action);
	}

	/*无法验证中文
	public function checkName(){
		$customer = M('customer');
		if ($customer->where('name = "' . $_GET['name'] . '"' )->find()){
			$this->ajaxReturn(1, "用户名不可以使用啊！",  1);
		}else{
			$this->ajaxReturn(0, "用户名可以使用！", 0);
		}
	}
	*/
	
	public function check(){
		import("@.ORG.SplitWord");
		$sp = new SplitWord();
		$m_customer = M('customer');
		$useless_words = array('公司','有限','的','有限公司');
		if ($this->isAjax()) {
			$split_result = $sp->SplitRMM($_POST['name']);
			if(!is_utf8($split_result)) $split_result = iconv("GB2312//IGNORE", "UTF-8", $split_result) ;
			$result_array = explode(' ',trim($split_result));
			foreach($result_array as $k=>$v){
				if(in_array($v,$useless_words)) unset($result_array[$k]);
			}
			$name_list = $m_customer->getField('name', true);
			$seach_array = array();
			foreach($name_list as $k=>$v){
				$search = 0;
				foreach($result_array as $k2=>$v2){
					if(strpos($v, $v2) > -1){
						$v = str_replace("$v2","<span style='color:red;'>$v2</span>", $v, $count);
						$search += $count;
					}
				}
				if($search > 0) $customer_search_array[$k] = array('value'=>$v,'search'=>$search);
			}
			$seach_sort_result = array_sort($seach_array,'search','desc');
			if(empty($seach_sort_result)){
				$this->ajaxReturn(0,"可以添加！",0);
			}else{
				$this->ajaxReturn($seach_sort_result,"已创建相近客户！",1);
			}
		}
	}
	
	public function validate() {
		if($this->isAjax()){
            if(!$this->_request('clientid','trim') || !$this->_request($this->_request('clientid','trim'),'trim')) $this->ajaxReturn("","",3);
            $field = M('Fields')->where('model = "customer" and field = "'.$this->_request('clientid','trim').'"')->find();
            $m_customer = $field['is_main'] ? D('Customer') : D('CustomerData');
            $where[$this->_request('clientid','trim')] = array('eq',$this->_request($this->_request('clientid','trim'),'trim'));
            if($this->_request('id','intval',0)){
                $where[$m_customer->getpk()] = array('neq',$this->_request('id','intval',0));
            }
			if($this->_request('clientid','trim')) {
				if ($m_customer->where($where)->find()) {
					$this->ajaxReturn("","",1);
				} else {
					$this->ajaxReturn("","",0);
				}
			}else{
				$this->ajaxReturn("","",0);
			}
		}
	}
	
	public function receive(){
		$m_customer = M('Customer');
		$m_config = M('Config');
		if($_POST['hidden_owner_id']){
			$owner_role_id = $_POST['hidden_owner_id'];
		}else{
			$owner_role_id = session('role_id');
		}
		$data['owner_role_id'] = $owner_role_id;
		$data['update_time'] = time();
		//是否是分配需要提醒
		$need_alert = false;
		//单个领取
		if($this->isGET()){
			$customer_id = isset($_GET['customer_id']) ? intval(trim($_GET['customer_id'])) : 0;
			if($m_customer->where('customer_id = %d', $customer_id)->save($data)){
				alert('success', '领取成功！', $_SERVER['HTTP_REFERER']);
			}else{
				alert('error', '领取失败！', $_SERVER['HTTP_REFERER']);
			}
		}else{
			$customer_ids = $_POST['customer_id'];
			//是否批量操作 否的话是单个分配
			if(!$_POST['customer_id']){alert('error', '您没有选择任何客户!', $_SERVER['HTTP_REFERER']);}
			if(is_array($customer_ids)){
				$customer_name = array();
				$where['update_time'] = array('lt',(time()-86400));
				$where['customer_id'] = array('in',implode(',',$customer_ids));
				$where['owner_role_id'] = array('gt',0);
				$updated_owner = $m_customer->where($where)->save($data);
				if($updated_owner) $customer_name = $m_customer->where($where)->getField('name', true);
				
				unset($where['update_time']);
				$where['owner_role_id'] = array('eq',0);
				$updated_time = $m_customer->where($where)->save($data);
				if(empty($customer_name)) $customer_name = $m_customer->where($where)->getField('name', true);
				//是否操作成功
				if($updated_owner || $updated_time){
					//是分配还是领取
					if($_POST['owner_id']){
						$title="您有新的客户-新负责客户提醒!";
						$content=session('name')."将客户资源:".implode(',', $customer_name).'  分配给了你负责!请注意跟进!';
						$need_alert = true;
					}else{
						alert('success', '批量领取成功！', $_SERVER['HTTP_REFERER']);
					}
				}else{

					if($_POST['owner_id']){
						alert('error', '批量分配失败！', $_SERVER['HTTP_REFERER']);
					}else{
						alert('error', '批量领取失败！', $_SERVER['HTTP_REFERER']);
					}
				}
			}else{
				$where['update_time'] = array('lt',(time()-86400));
				$where['customer_id'] = intval($customer_ids);
				$where['owner_role_id'] = array('gt',0);
				$updated_owner = $m_customer->where($where)->save($data);
				
				unset($where['update_time']);
				$where['owner_role_id'] = array('eq',0);
				$updated_time = $m_customer->where($where)->save($data);
			
				if($updated_owner || $updated_time){
					$customer = $m_customer->where('customer_id = %d', intval($customer_ids))->find();
					$title="您有新的客户-新负责客户提醒!";
					$content=session('name')."将客户资源:".$customer['name'].'  分配给了你负责!请注意跟进!';
					$need_alert = true;
				}else{
					alert('error', '分配失败！', $_SERVER['HTTP_REFERER']);
				}
			}
			//分配需要提醒
			if($need_alert){
			echo 1;
				if(intval($_POST['message_alert']) == 1) {
					sendMessage($owner_role_id,$content,1);
				}
				if(intval($_POST['email_alert']) == 1){
					$email_result = sysSendEmail($owner_role_id,$title,$content);
					if(!$email_result) alert('error', '邮件通知失败，对方未设置有效邮箱！',$_SERVER['HTTP_REFERER']);
				}
				if(intval($_POST['sms_alert']) == 1){
					$sms_result = sysSendSms($owner_role_id,$content);
					if(100 == $sms_result){
						alert('error', '短信通知发送失败，对方未设置有效手机号！',$_SERVER['HTTP_REFERER']);
					}elseif($sms_result < 0){
						alert('error','短信通知发送失败，错误代码:'.$sms_result.'请联系管理员确认短信接口配置',$_SERVER['HTTP_REFERER']);
					}
				}
				alert('success', '分配成功！', $_SERVER['HTTP_REFERER']);
			}
			
		}
	}
	
	public function fenpei(){
		$customer_id = intval($_GET['customer_id']);
		 if ($this->isGET()) {
			if($_GET['by'] == 'put'){
				if($customer_id){
					if(M('customer')->where('customer_id = %d', $customer_id)->setField('owner_role_id',0)){
						alert('success', '放入客户池成功!', U('customer/index'));
					}else{
						alert('error', '放入客户池失败！', $_SERVER['HTTP_REFERER']);
					}
					
				}else{
					alert('error', '参数错误！', $_SERVER['HTTP_REFERER']);
				}
			}else{	
				$this->customer_id = $customer_id;
				$this->display();
			}
		}
	}

	
	
	
	public function changeContent(){
		if($this->isAjax()){
			$m_customer = M('Customer');
			$below_ids = getSubRoleId(false);
			$where = array();
			$params = array();
			$where['is_deleted'] = array('neq',1);
			$where['owner_role_id'] = array('in',implode(',', getSubRoleId(true))); 
			
			if ($_REQUEST["field"]) {
				if (trim($_REQUEST['field']) == "all") {
					$field = is_numeric(trim($_REQUEST['search'])) ? 'name|origin|address|email|telephone|website|account_type|industry|annual_revenue|sic_code|ticker_symbol|ownership|rating|description' : 'name|origin|address|email|telephone|website|account_type|industry|annual_revenue|sic_code|ticker_symbol|ownership|rating|description|create_time|update_time';
				} else {
					$field = trim($_REQUEST['field']);
				}
				$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
				$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
				
				if ('create_time' == $field || 'update_time' == $field) {
					$search = is_numeric($search)?$search:strtotime($search);
				}
				switch ($condition) {
					case "is" : $where[$field] = array('eq',$search);break;
					case "isnot" :  $where[$field] = array('neq',$search);break;
					case "contains" :  $where[$field] = array('like','%'.$search.'%');break;
					case "not_contain" :  $where[$field] = array('notlike','%'.$search.'%');break;
					case "start_with" :  $where[$field] = array('like',$search.'%');break;
					case "end_with" :  $where[$field] = array('like','%'.$search);break;
					case "is_empty" :  $where[$field] = array('eq','');break;
					case "is_not_empty" :  $where[$field] = array('neq','');break;
					case "gt" :  $where[$field] = array('gt',$search);break;
					case "egt" :  $where[$field] = array('egt',$search);break;
					case "lt" :  $where[$field] = array('lt',$search);break;
					case "elt" :  $where[$field] = array('elt',$search);break;
					case "eq" : $where[$field] = array('eq',$search);break;
					case "neq" : $where[$field] = array('neq',$search);break;
					case "between" : $where[$field] = array('between',array($search-1,$search+86400));break;
					case "nbetween" : $where[$field] = array('not between',array($search,$search+86399));break;
					case "tgt" :  $where[$field] = array('gt',$search+86400);break;
					default : $where[$field] = array('eq',$search);
				}
				$params = array('field='.trim($_REQUEST['field']), 'condition='.$condition, 'search='.$_REQUEST["search"]);
			}
			$p = !$_REQUEST['p']||$_REQUEST['p']<=0 ? 1 : intval($_REQUEST['p']);
			$list = $m_customer->where($where)->order('create_time desc')->page($p.',10')->select();
			$count = $m_customer->where($where)->count();
			$data['list'] = $list;
			$data['p'] = $p;
			$data['count'] = $count;
			$data['total'] = $count%10 > 0 ? ceil($count/10) : $count/10;
			$this->ajaxReturn($data,"",1);
		}
	}
	
	public function add(){
		if($this->isPost()){		
			$m_customer = D('Customer');
			$m_customer_data = D('CustomerData');
			$field_list = M('Fields')->where('model = "customer"')->order('order_id')->select();
			foreach ($field_list as $v){
				switch($v['form_type']) {
					case 'address':
						$a = array_filter($_POST[$v['field']]);
						$_POST[$v['field']] = !empty($a) ? implode(chr(10),$a) : '';
					break;
					case 'datetime':
						$_POST[$v['field']] = strtotime($_POST[$v['field']]);
					break;
					case 'box':
						eval('$field_type = '.$v['setting'].';');
						if($field_type['type'] == 'checkbox'){
							$b = array_filter($_POST[$v['field']]);
							$_POST[$v['field']] = !empty($b) ? implode(chr(10),$b) : '';
						}
					break;
				}
			}
			if($m_customer->create() && $m_customer_data->create()!==false){
				if($_POST['con_name']){
					$contacts = array();
					if($_POST['con_name']) $contacts['name'] = $_POST['con_name'];
					if($_POST['owner_role_id']) $contacts['owner_role_id'] = $_POST['owner_role_id'];
					if($_POST['con_sex']) $contacts['sex'] = $_POST['con_sex'];
					if($_POST['con_email']) $contacts['email'] = $_POST['con_email'];
					if($_POST['con_post']) $contacts['post'] = $_POST['con_post'];
					if($_POST['con_qq']) $contacts['qq'] = $_POST['con_qq'];
					if($_POST['con_telephone']) $contacts['telephone'] = $_POST['con_telephone'];
					if($_POST['con_description']) $contacts['description'] = $_POST['con_description'];
					if(!empty($contacts)){
						$contacts['creator_role_id'] = session('role_id');
						$contacts['create_time'] = time();
						$contacts['update_time'] = time();
						if(!$contacts_id = M('Contacts')->add($contacts)){
							alert('error', '添加首要联系人失败！', U('customer/add'));
						}
					}
				}
			
                $m_customer->create_time = time();
                $m_customer->update_time = time();
                if($contacts_id) $m_customer->contacts_id = $contacts_id;
                $m_customer->creator_role_id = session('role_id');
                if(!$customer_id = $m_customer->add()){
                    alert('error', '添加客户失败,请联系管理员！', U('customer/add'));
                }
                $m_customer_data->customer_id = $customer_id;
                $m_customer_data->add();
				
				if ($_POST['leads_id']) {
					$leads_id = intval($_POST['leads_id']);
					$r_module = array(
						array('key'=>'log_id','r1'=>'RCustomerLog','r2'=>'RLeadsLog'), 
						array('key'=>'file_id','r1'=>'RCustomerFile','r2'=>'RFileLeads'),
						array('key'=>'event_id','r1'=>'RCustomerEvent','r2'=>'REventLeads'),
						array('key'=>'task_id','r1'=>'RCustomerTask','r2'=>'RLeadsTask')
					);
					
					foreach ($r_module as $key=>$value) {
						$key_id_array = M($value['r2'])->where('leads_id = %d', $leads_id)->getField($value['key'],true);
						$r1 = M($value['r1']);
						$data['customer_id'] = $customer_id;
						foreach($key_id_array as $k=>$v){
							$data[$value['key']] = $v;
							$r1->add($data);
						}
					}
					$leads_data['is_transformed'] = 1;
					$leads_data['update_time'] = time();
					$leads_data['customer_id'] = $customer_id;
					$leads_data['contacts_id'] = $contacts_id;
					$leads_data['transform_role_id'] = session('role_id');
					M('Leads')->where('leads_id = %d', $leads_id)->save($leads_data);
				}
				
                //记录操作记录
                actionLog($customer_id);
                if ($contacts_id & $customer_id) {
                    $rcc['contacts_id'] = $contacts_id;
                    $rcc['customer_id'] = $customer_id;
                    M('RContactsCustomer')->add($rcc);
                }
                if(intval($_POST['create_business1']) == 1 || intval($_POST['create_business2']) == 1){
                    alert('success', '添加客户成功！', U('business/add','customer_id='.$customer_id));
                }else{
                    if($_POST['submit'] == "保存") {
                        alert('success', '添加客户成功！', U('customer/index'));
                    } else {
                        alert('success', '添加客户成功！', U('customer/add'));
                    }
                }
			}else{
                alert('error', $m_customer->getError().$m_customer_data->getError());
				
				$this->alert = parseAlert();				
				$this->error();
            }
			
		}else{
			if(intval($_GET['leads_id'])){
				$leads = D('LeadsView')->where('leads.leads_id = %d', intval($_GET['leads_id']))->find();
				$this->leads = $leads;
			}
			$field_list = M('Fields')->where('model = "customer"')->order('order_id')->select();
			foreach($field_list as $k=>$v){
				if(trim($v['input_tips'])){
					$input_tips = ' &nbsp; <span style="color:#005580;">(注: '.$v['input_tips'].')</span>';
				}else{
					$input_tips = '';
				}
				switch ($v['form_type']) {
					case 'textarea' :
						$default_value = $leads[$v['field']] ? $leads[$v['field']] : $v['default_value'];
						$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><textarea  rows="6" class="span6" id="'.$v['field'].'" name="'.$v['field'].'" >'.$default_value.'</textarea> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
						break;
					case 'box' :
						$setting_str = '$setting='.$v['setting'].';';
						eval($setting_str);
						$field_list[$k]['setting'] = $setting;
						if ($setting['type'] == 'select') {
							$str = '';
							$str .= "<option value=''>--请选择--</option>";
							foreach ($setting['data'] as $v2) {
								$str .= "<option value='$v2'>$v2</option>";
							}
							$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><select id="'.$v['field'].'" name="'.$v['field'].'">'.$str.'</select> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
							break;
						} elseif ($setting['type'] == 'radio') {
							$str = '';
                            $i = '';
							foreach ($setting['data'] as $v2) {
								$str .= " &nbsp; <input type='radio' name='".$v['field']."' id='".$v['field'].$i."' value='$v2'/>&nbsp; $v2";
                                $i++;
							}
							$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td>'.$str.'   <span id="'.$v['field'].'Tip" style="color:red;"></span>&nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td> </tr>';
							break;
						} elseif ($setting['type'] == 'checkbox') {
							$str = '';
                            $i = '';
							foreach ($setting['data'] as $v2) {
								$str .= " &nbsp; <input type='checkbox' name='".$v['field']."' id='".$v['field'].$i."' value='$v2'/>&nbsp; $v2";
                                $i++;
							}
							$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td>'.$str.'  <span id="'.$v['field'].'Tip" style="color:red;"></span>&nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
							break;
						}
						break;
					case 'editor' :
						$upload_url = U('file/editor');
						$field_list[$k]['html'] = '<script type="text/javascript">
						var editor;
						KindEditor.ready(function(K) {
							editor = K.create(\'textarea[name="'.$v['field'].'"]\', {
								uploadJson:"'.$upload_url.'",
								allowFileManager : true,
								loadStyleMode : false
							});
						});
						</script>
						<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><textarea name="'.$v['field'].'" id="'.$v['field'].'" style="width: 800px; height: 350px;"></textarea> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
						break;
					case 'datetime' :
						$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><input  onFocus="WdatePicker({dateFmt:\'yyyy-MM-dd\'})" name="'.$v['field'].'" class="span2" id="'.$v['field'].'" type="text" value="'.pregtime($v['default_value']).'"/> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
						break;
					case 'number' :
						$default_value = $leads[$v['field']] ? $leads[$v['field']] : $v['default_value'];
						$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="text" id="'.$v['field'].'" name="'.$v['field'].'" value="'.$default_value.'"/> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
						break;
					case 'floatnumber' :
						$default_value = $leads[$v['field']] ? $leads[$v['field']] : $v['default_value'];
						$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="text" id="'.$v['field'].'" name="'.$v['field'].'" value="'.$default_value.'"/> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
						break;
					case 'address':
						$defaultinfo = unserialize(M('Config')->where('name = "defaultinfo"')->getField('value'));
						$field_list[$k]['html'] = '<script type="text/javascript">
						$(function(){
							new PCAS("'.$v['field'].'[\'state\']","'.$v['field'].'[\'city\']","'.$defaultinfo['state'].'","'.$defaultinfo['city'].'");
						});
						</script><tr>
							<td class="tdleft">'.$v['name'].':</td>
							<td><select name="'.$v['field'].'[\'state\']" class="input-medium"></select> 
							<select name="'.$v['field'].'[\'city\']" class="input-medium"></select>
							<input type="text" name="'.$v['field'].'[\'street\']" placeholder="街道信息" class="input-large"></td>						
						</tr>';
						break;
                    default: 
						$default_value = $leads[$v['field']] ? $leads[$v['field']] : $v['default_value'];
						if ($v['field'] == 'create_time' || $v['field'] == 'update_time') {
							break;
						} elseif ($v['field'] == 'name') {
							$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="text" id="name" name="'.$v['field'].'" maxlength="'.$v['maxlength'].'" value="'.$default_value.'"/> &nbsp; '.$input_tips.'</td></tr>';
						} else {
                            $field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="text" id="'.$v['field'].'"  name="'.$v['field'].'" value="'.$default_value.'"/> &nbsp; <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
						}
						break;
				}
			}
            $alert = parseAlert();
            $this->alert = $alert;
            $this->field_list = $field_list;
            $this->display();
		}
	}
	
	public function delete(){
		$m_customer = M('Customer');
		if ($this->isPost()) {
			$customer_ids = is_array($_POST['customer_id']) ? implode(',', $_POST['customer_id']) : '';
			if ('' == $customer_ids) {
				alert('error', '您没有选择任何内容！', U('customer/index'));
			} else {
				$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
				if($m_customer->where('customer_id in (%s)', $customer_ids)->setField($data)){	
                    //记录操作记录
                    foreach($_POST['customer_id'] as $customer_id){
                        actionLog($customer_id);
                    }
					alert('success', '删除成功!',U('customer/index','content='.$_GET['content']));
				} else {
					alert('error', '删除失败，联系管理员！', U('customer/index','content='.$_GET['content']));
				}
			}
		} elseif($_GET['id']) {
			$customer = $m_customer->where('customer_id = %d', $_GET['id'])->find();
			if (is_array($customer)) {
				if($customer['owner_role_id'] == session('role_id') || session('?admin')){
					$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
					if($m_customer->where('customer_id = %d', $_GET['id'])->setField($data)){
                        actionLog($_GET['id']);
						alert('success', '删除成功!', $_SERVER['HTTP_REFERER']);
					}else{
						alert('error', '删除失败，请联系管理员！', $_SERVER['HTTP_REFERER']);
					}	
				} else {
					alert('error', '您没有权限！', $_SERVER['HTTP_REFERER']);
				}
					
			} else {
				alert('error', '记录不存在！', U('customer/index'));
			}			
		} else {
			alert('error', '请选择要删除的线索!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function completeDelete() {
		$m_customer = M('Customer');
		$r_module = array('Log'=>'RCustomerLog', 'File'=>'RCustomerFile', 'Event'=>'RCustomerEvent', 'Task'=>'RCustomerTask', 'RContactsCustomer');
		if (!session('?admin')) {
			alert('error', '您没有权限进行彻底删除操作！', $_SERVER['HTTP_REFERER']);
		}
		if ($this->isPost()) {
			$customer_ids = is_array($_POST['customer_id']) ? implode(',', $_POST['customer_id']) : '';
			if ('' == $customer_ids) {
				alert('error', '您没有选择任何内容！', U('leads/index'));
			} else {
				if (!session('?admin')) {
					foreach($_POST['customer_id'] as $key => $value){
						if(!$m_customer->where('owner_role_id = %d and customer_id = %d', session('role_id'), $value) -> find()){
							alert('error', '您没有全部权限进行操作！', $_SERVER['HTTP_REFERER']);
						}else{
							actionLog($value);
						}
					}
				}
				if($m_customer->where('customer_id in (%s)', $customer_ids)->delete()){	
                    M('CustomerDate')->where('customer_id in (%s)', $customer_ids)->delete();
					foreach ($_POST['customer_id'] as $value) {
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('customer_id = %d', $value)->getField($key2 . '_id', true);
							M($value2)->where('customer_id = %d', $value) -> delete();
							if(!is_int($key2)){
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
					}
					alert('success', '删除成功!',U('customer/index'));
				} else {
					alert('error', '删除失败，联系管理员！', U('customer/index'));
				}
			}
		} elseif($_GET['id']) {
			$customer = $m_customer->where('customer_id = %d', $_GET['id'])->find();
			if (is_array($customer)) {
				if($customer['owner_role_id'] == session('role_id') || session('?admin')){
					if($m_customer->where('customer_id = %d', $_GET['id'])->delete()){
						actionLog($_GET['id']);
                        M('CustomerDate')->where('customer_id = %d', $_GET['id'])->delete();
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('customer_id = %d', $_GET['id'])->getField($key2 . '_id', true);
							M($value2)->where('customer_id = %d', $_GET['id']) -> delete();
							if(!is_int($key2)){
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
						alert('success', '删除成功!', U('customer/index'));
					}else{
						alert('error', '删除失败，请联系管理员！', U('customer/index'));
					}
				} else {
					alert('error', '您没有权限！', $_SERVER['HTTP_REFERER']);
				}
					
			} else {
				alert('error', '记录不存在！', U('customer/index'));
			}			
		} else {
			alert('error', '请选择要删除的线索!',$_SERVER['HTTP_REFERER']);
		}
	}

	public function edit(){
        $customer = D('CustomerView')->where('customer.customer_id = %d',$this->_request('id'))->find();
		if (!$customer) {
            alert('error', '客户不存在!',$_SERVER['HTTP_REFERER']);
        }
        $customer['owner'] = D('RoleView')->where('role.role_id = %d', $customer['owner_role_id'])->find();
        $customer['contacts_name'] = M('contacts')->where('contacts_id = %d', $customer['contacts_id'])->getField('name');
        $field_list = M('Fields')->where('model = "customer"')->order('order_id')->select();

		if($this->isPost()){
			$m_customer = D('Customer');
			$m_customer_data = D('CustomerData');
			foreach ($field_list as $v){
				switch($v['form_type']) {
					case 'address':
						$_POST[$v['field']] = implode(chr(10),$_POST[$v['field']]);
					break;
					case 'datetime':
						$_POST[$v['field']] = strtotime($_POST[$v['field']]);
					break;
					case 'box':
						eval('$field_type = '.$v['setting'].';');
						if($field_type['type'] == 'checkbox'){
							$_POST[$v['field']] = implode(chr(10),$_POST[$v['field']]);
						}
					break;
				}
			}
            
			if($m_customer->create() && $m_customer_data->create()!==false){
				$a = $m_customer->where('customer_id=' . $customer['customer_id'])->save();
				$b = $m_customer_data->where('customer_id=' . $customer['customer_id'])->save();
				if($a !== false && $b !== false){
					actionLog($customer['customer_id']);
					alert('success', '客户编辑成功！', U('customer/index'));
				}else{
					alert('error', '客户编辑失败！',$_SERVER['HTTP_REFERER']);
				}
            
            }else{
                alert('error', $m_customer->getError().$m_customer_data->getError());
				
				$this->alert = parseAlert();				
				$this->error();
            }
		}else{
            $alert = parseAlert();
            $this->alert = $alert;
            $this->customer = $customer;
            $this->field_list = field_list_html("edit","customer",$customer);
            $this->display();
		}
		
	}
	
	public function index(){
		$d_v_customer = D('CustomerView');
        $by = isset($_GET['by']) ? trim($_GET['by']) : '';
		$below_ids = getSubRoleId(false);
		$all_ids = getSubRoleId();
		$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
		$outdate = empty($outdays) ? 0 : time()-86400*$outdays;
		$where = array();
		$params = array();
		$order = "";
		switch ($by) {
			case 'today' : $where['create_time'] =  array('gt',strtotime(date('Y-m-d', time()))); break;
			case 'week' : $where['create_time'] =  array('gt',(strtotime(date('Y-m-d')) - (date('N', time()) - 1) * 86400)); break;
			case 'month' : $where['create_time'] = array('gt',strtotime(date('Y-m-01', time()))); break;
			case 'add' : $order = 'create_time desc'; break;
			case 'update' : $order = 'update_time desc'; break;
			case 'sub' : $where['owner_role_id'] = array('in',implode(',', $below_ids)); break;
			case 'deleted' : $where['is_deleted'] = 1;break;
			case 'me' : $where['owner_role_id'] = session('role_id'); break;
			default :
		        if($this->_get('content') == 'resource'){
		            $where['_string'] = "customer.owner_role_id=0 or customer.update_time < $outdate";
		            $all_ids[] = "";
		            $where['owner_role_id'] = array('in', $all_ids);
		        }else{
					$where['owner_role_id'] = array('in',implode(',', $all_ids));
		        }
			break;
		}
		if ($by != 'deleted') {
			$where['is_deleted'] = array('neq',1);
		}
		if (!isset($where['owner_role_id'])) {
			$where['owner_role_id'] = array('in', $all_ids);
		}
		if($this->_get('content') != 'resource'){
			$where['update_time'] = array('gt',$outdate);
		}
		if($by == 'deleted') unset($where['update_time']);
		
		if ($_REQUEST["field"]) {
			if (trim($_REQUEST['field']) == "all") {
				$field = is_numeric(trim($_REQUEST['search'])) ? 'name|origin|address|email|telephone|website|account_type|industry|annual_revenue|sic_code|ticker_symbol|ownership|rating|description' : 'name|origin|address|email|telephone|website|account_type|industry|annual_revenue|sic_code|ticker_symbol|ownership|rating|description|create_time|update_time';
			} else {
				$field = trim($_REQUEST['field']);
			}
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
			
			$field_date = M('Fields')->where('is_main=1 and (model="" or model="customer") and form_type="datetime"')->select();
			foreach($field_date as $v){
				if	($field == $v['field']) $search = is_numeric($search)?$search:strtotime($search);
			}
            if ($this->_request('state') || $this->_request('city')) {
				$search = $this->_request('state') .chr(10) . $this->_request('city') .chr(10) .$search;
			}
			switch ($condition) {
				case "is" : $where[$field] = array('eq',$search);break;
				case "isnot" :  $where[$field] = array('neq',$search);break;
				case "contains" :  $where[$field] = array('like','%'.$search.'%');break;
				case "not_contain" :  $where[$field] = array('notlike','%'.$search.'%');break;
				case "start_with" :  $where[$field] = array('like',$search.'%');break;
				case "not_start_with" :  $where[$field] = array('notlike',$search.'%');break;
				case "end_with" :  $where[$field] = array('like','%'.$search);break;
				case "is_empty" :  $where[$field] = array('eq','');break;
				case "is_not_empty" :  $where[$field] = array('neq','');break;
				case "gt" :  $where[$field] = array('gt',$search);break;
				case "egt" :  $where[$field] = array('egt',$search);break;
				case "lt" :  $where[$field] = array('lt',$search);break;
				case "elt" :  $where[$field] = array('elt',$search);break;
				case "eq" : $where[$field] = array('eq',$search);break;
				case "neq" : $where[$field] = array('neq',$search);break;
				case "between" : $where[$field] = array('between',array($search-1,$search+86400));break;
				case "nbetween" : $where[$field] = array('not between',array($search,$search+86399));break;
				case "tgt" :  $where[$field] = array('gt',$search+86400);break;
				default : $where[$field] = array('eq',$search);
			}
			$params = array('field='.trim($_REQUEST['field']), 'condition='.$condition, 'search='.$search);
		}
      
		if(trim($_GET['act'] == 'sms')){
			$customer_ids = $d_v_customer->where($where)->getField('customer_id', true);
			$contacts_ids = M('RContactsCustomer')->where('customer_id in (%s)', implode(',', $customer_ids))->getField('contacts_id', true);
			$contacts_ids = implode(',', $contacts_ids);
			$contacts = D('ContactsView')->where('contacts.contacts_id in (%s)', $contacts_ids)->select();
			$this->contacts = $contacts;
			$this->display('Setting:sendsms');
		}else{
			if ($order) {
				$list = $d_v_customer->where($where)->order($order)->limit(15)->select();
			} else {
				$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
				$list = $d_v_customer->where($where)->order('create_time desc')->page($p.',15')->select();
				$count = $d_v_customer->where($where)->count();
				import("@.ORG.Page");
				$Page = new Page($count,15);
				if (!empty($_GET['by'])) {
					$params[] = "by=" . trim($_GET['by']);
				}
				$Page->parameter = implode('&', $params);
				$this->assign('page',$Page->show());
			}	
			if($by == 'deleted') {
				foreach ($list as $k => $v) {
					$list[$k]["delete_role"] = D('RoleView')->where('role.role_id = %d', $v['delete_role_id'])->find();
					$list[$k]["creator"] = D('RoleView')->where('role.role_id = %d', $v['creator_role_id'])->find();
					$list[$k]["owner"] = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
				}
			} else {
				foreach ($list as $k => $v) {
					$days = 0;
					$list[$k]["owner"] = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
					$list[$k]["creator"] = D('RoleView')->where('role.role_id = %d', $v['creator_role_id'])->find();
					$days =  M('Customer')->where('customer_id = %d', $v['customer_id'])->getField('update_time');
					$list[$k]["days"] = $outdays-floor((time()-$days)/86400);
				}
			}
			//echo "<pre>";print_r(getIndexFields('customer'));echo "</pre>";die;
			$this->customerlist = $list;
			$this->field_array = getIndexFields('customer');
			$this->field_list = getMainFields('customer');
			$this->alert = parseAlert();
			$this->display();
		}
		
        
	}

	public function listDialog(){
		$m_customer = M('Customer');
		$m_contacts = M('Contacts');
		$m_r_contacts_customer = M('RContactsCustomer');
		$underling_ids = getSubRoleId();
		$customer = $m_customer->where('owner_role_id in (%s) and is_deleted = 0',implode(',',$underling_ids))->order('create_time desc')->limit(10)->select();
		foreach($customer as $k=>$v){
			//如果存在首要联系人，则查出首要联系人。否则查出联系人中第一个。
			if(!empty($v['contacts_id'])){
				$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$v['contacts_id'])->find();
				$customer[$k]['contacts_name'] = $contacts['name'];
			}else{
				$contacts_customer = $m_r_contacts_customer->where('customer_id = %d',$v['customer_id'])->limit(1)->order('id desc')->select();
				$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$contacts_customer[0]['contacts_id'])->find();
				$customer[$k]['contacts_id'] = $contacts['contacts_id'];
				$customer[$k]['contacts_name'] = $contacts['name'];
			}
		}
		
		$this->customerList = $customer;
		$count = $m_customer->where('owner_role_id in (%s) and is_deleted = 0',implode(',',$underling_ids))->count();
		$this->total = $count%10 > 0 ? ceil($count/10) : $count/10;
		$data = getIndexFields('customer');
		$this->count_num = $count;
		$this->field_num = sizeof($data)+1;
        $this->field_array = $data;
		$this->display();
	}

    
	public function view(){
		$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
		if (0 == $customer_id) {
			alert('error', '参数错误！', U('customer/index'));
		} else {
            //查询客户数据
			$customer = D('CustomerView')->where('customer.customer_id = %d', $customer_id)->find();
            //取得字段列表
			$field_list = M('Fields')->where('model = "customer"')->order('order_id')->select();
            //查询固定信息
			$customer['owner'] = D('RoleView')->where('role.role_id = %d', $customer['owner_role_id'])->find();
			$customer['create'] = D('RoleView')->where('role.role_id = %d', $customer['creator_role_id'])->find();
			if($customer['contacts_id']) $customer['contacts_name'] = M('contacts')->where('contacts_id = %d', $customer['contacts_id'])->getField('name');
            
            if($customer['is_deleted'] == 1){
                $customer['deleted'] = D('RoleView')->where('role.role_id = %d', $customer['delete_role_id'])->find();
            }
			$log_ids = M('rCustomerLog')->where('customer_id = %d', $customer_id)->getField('log_id', true);
			$customer['log'] = M('log')->where('log_id in (%s)', implode(',', $log_ids))->select();
			$log_count = 0;
			foreach ($customer['log'] as $key=>$value) {
				$customer['log'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$log_count ++;
			}
			$customer['log_count'] = $log_count;
			
			$file_ids = M('rCustomerFile')->where('customer_id = %d', $customer_id)->getField('file_id', true);
			$customer['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$file_count = 0;
			foreach ($customer['file'] as $key=>$value) {
				$customer['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$file_count ++;
			}
			$customer['file_count'] = $file_count;
			
			$task_ids = M('rCustomerTask')->where('customer_id = %d', $customer_id)->getField('task_id', true);
			$customer['task'] = M('task')->where('task_id in (%s) and is_deleted=0', implode(',', $task_ids))->select();
			$task_count = 0;
			foreach ($customer['task'] as $key=>$value) {
				$customer['task'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
				$task_count ++;
			}
			$customer['task_count'] = $task_count;
			
			$event_ids = M('rCustomerEvent')->where('customer_id = %d', $customer_id)->getField('event_id', true);
			$customer['event'] = M('event')->where('event_id in (%s) and is_deleted=0', implode(',', $event_ids))->select();
			$event_count = 0;
			foreach ($customer['event'] as $key=>$value) {
				$customer['event'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
				$event_count ++;
			}
			$customer['event_count'] = $event_count;
			
			$customer['business'] = M('business')->where('customer_id = %d and is_deleted=0', $customer['customer_id'])->select();
			$customer['business_count'] = sizeof($customer['business']);
			foreach($customer['business'] as $k=>$v){
				$customer['business'][$k]['owner'] = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
				$customer['business'][$k]['status'] = M('BusinessStatus')->where('status_id = %d', $v['status_id'])->getField('name');
				$business_id[] = $v['business_id'];
			}
			
			
			$customer['receivables'] = D('ReceivablesView')->where('receivables.customer_id = %d and receivables.is_deleted=0', $customer['customer_id'])->select();
			$customer['receivables_count'] = sizeof($customer['receivables']);
			foreach($customer['receivables'] as $k=>$v){
				$customer['receivables'][$k]['owner'] = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
			}
			
			$customer['payables'] = D('PayablesView')->where('payables.customer_id = %d and payables.is_deleted=0', $customer['customer_id'])->select();
			$customer['payables_count'] = sizeof($customer['payables']);
			foreach($customer['payables'] as $k=>$v){
				$customer['payables'][$k]['owner'] = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
			}
			
			$customer['cares'] = D('CaresView')->where('customer_cares.customer_id = %d', $customer['customer_id'])->select();
			$customer['cares_count'] = sizeof($customer['cares']);
			
			$customer['contract'] = D('ContractView')->where('contract.business_id in (%s) and contract.is_deleted=0', implode(',', $business_id))->select();
			
			$customer['contract_count'] = $customer['contract'] ? sizeof($customer['contract']):0;
			foreach($customer['contract'] as $k=>$v){
				$customer['contract'][$k]['owner'] = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
			}
			
			$customer['product'] = D('BusinessProductView')->where('r_business_product.business_id in (%s)', implode(',', $business_id))->select();
			$customer['product_count'] = $customer['product'] ? sizeof($customer['product']) : 0;
			
			$contacts_ids = M('rContactsCustomer')->where('customer_id = %d', $customer_id)->getField('contacts_id', true);
			$customer['contacts'] = M('contacts')->where('contacts_id in (%s) and is_deleted=0', implode(',', $contacts_ids))->select();
			
			foreach($customer['contacts'] as $k=>$v){
				if(M('Customer')->where('contacts_id = %d',$v['contacts_id'])->select()){
					$customer['contacts'][$k]['is_firstContact'] = 'true';
				}else{
					$customer['contacts'][$k]['is_firstContact'] = 'false';
				}
			}
			$contacts_count = M('contacts')->where('contacts_id in (%s) and is_deleted=0', implode(',', $contacts_ids))->count();
			$customer['contacts_count'] = empty($contacts_count)?0:$contacts_count;
			$this->customer = $customer;		
            $this->field_list = $field_list;
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function excelExport(){
		import("ORG.PHPExcel.PHPExcel");
		$objPHPExcel = new PHPExcel();    
		$objProps = $objPHPExcel->getProperties();    
		$objProps->setCreator("5kcrm");
		$objProps->setLastModifiedBy("5kcrm");    
		$objProps->setTitle("5kcrm Customer");    
		$objProps->setSubject("5kcrm Customer Data");    
		$objProps->setDescription("5kcrm Customer Data");    
		$objProps->setKeywords("5kcrm Customer Data");    
		$objProps->setCategory("5kcrm");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
        $ascii = 65;
        $cv = '';
        $field_list = M('Fields')->where('model = \'customer\'')->order('order_id')->select();
        foreach($field_list as $field){
            $objActSheet->setCellValue($cv.chr($ascii).'1', $field['name']);
            $ascii++;
            if($ascii == 91){
                $ascii = 65;
                $cv .= chr(strlen($cv)+65);
            }
        }

		$where['owner_role_id'] = array('in',implode(',', getSubRoleId()));
		$where['is_deleted'] = 0;
		$list = M('Customer')->where($where)->select();
		
        
		$i = 1;
		foreach ($list as $k => $v) {
            $date = M('CustomerData')->where("customer_id = $v[customer_id]")->find();
            if(!empty($date)){
                $v = $v+$date;
            }
			$i++;
            $ascii = 65;
            $cv = '';
            foreach($field_list as $field){
                if($field['form_type'] == 'datetime'){
                    $objActSheet->setCellValue($cv.chr($ascii).$i, date('Y-m-d',$v[$field['field']]));
                }else{
                    $objActSheet->setCellValue($cv.chr($ascii).$i, $v[$field['field']]);
                }
                $ascii++;
                if($ascii == 91){
                    $ascii = 65;
                    $cv .= chr(strlen($cv)+65);
                }
            }
			
		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition:attachment;filename=5kcrm_customer_".date('Y-m-d',mktime()).".xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
	}
	
	public function excelImport(){
        
		$m_customer = D('Customer');
		$m_customer_data = D('CustomerData');
        
		if($this->isPost()){
            
			if (isset($_FILES['excel']['size']) && $_FILES['excel']['size'] != null) {
				import('@.ORG.UploadFile');
				$upload = new UploadFile();
				$upload->maxSize = 20000000;
				$upload->allowExts  = array('xls');
				$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					alert('error', '附件上传目录不可写', U('customer/index'));
				}
				$upload->savePath = $dirname;
				if(!$upload->upload()) {
					alert('error', $upload->getErrorMsg(), U('customer/index'));
				}else{
					$info =  $upload->getUploadFileInfo();
				}
			}
            
			if(is_array($info[0]) && !empty($info[0])){
				$savePath = $dirname . $info[0]['savename'];
			}else{
				alert('error', '上传失败', U('customer/index'));
			}
			import("ORG.PHPExcel.PHPExcel");
			$PHPExcel = new PHPExcel();
			$PHPReader = new PHPExcel_Reader_Excel2007();
			if(!$PHPReader->canRead($savePath)){
				$PHPReader = new PHPExcel_Reader_Excel5();
			}
			$PHPExcel = $PHPReader->load($savePath);
			$currentSheet = $PHPExcel->getSheet(0);
			$allRow = $currentSheet->getHighestRow();
			
			if ($allRow <= 2) {
				alert('error', '上传文件无有效数据', U('customer/index'));
                
			} else {
                $field_list = M('Fields')->where('model = \'customer\'')->order('order_id')->select();
				for($currentRow = 3;$currentRow <= $allRow;$currentRow++){
					$data = array();
					$data['owner_role_id'] = intval($_POST['owner_role_id']);
					$data['creator_role_id'] = session('role_id');
					$data['create_time'] = time();
					$data['update_time'] = time();
                    $ascii = 65;
                    $cv = '';
                    foreach($field_list as $field){
                        $info = (String)$currentSheet->getCell($cv.chr($ascii).$currentRow)->getValue();
                        if ($field['is_main'] == 1){
                            $data[$field['field']] = ($field['form_type'] == 'datetime' && $info != null) ? intval(PHPExcel_Shared_Date::ExcelToPHP($info))-8*60*60 : $info;
                        }else{
                            $data_date[$field['field']] = ($field['form_type'] == 'datetime' && $info != null) ? intval(PHPExcel_Shared_Date::ExcelToPHP($info))-8*60*60 : $info;
                        }
                        
                        $ascii++;
                        if($ascii == 91){
                            $ascii = 65;
                            $cv .= chr(strlen($cv)+65);
                        }
                    }
                    if ($m_customer->create($data) && $m_customer_data->create($data_date)) {
                        $customer_id = $m_customer->add();
                        $m_customer_data->customer_id = $customer_id;
                        $m_customer_data->add();
					}else{
                        alert('error', '导入至第' . $currentRow . '行出错'.$m_customer->getError().$m_customer_data->getError(), U('customer/index'));
                    }
				}
               
				alert('success', '导入成功！', U('customer/index'));
			}
		}else{
			$this->display();
		}
	}
    public function excelImportDownload(){
        import("ORG.PHPExcel.PHPExcel");
		$objPHPExcel = new PHPExcel();    
		$objProps = $objPHPExcel->getProperties();    
		$objProps->setCreator("5kcrm");
		$objProps->setLastModifiedBy("5kcrm");    
		$objProps->setTitle("5kcrm Customer");    
		$objProps->setSubject("5kcrm Customer Data");    
		$objProps->setDescription("5kcrm Customer Data");    
		$objProps->setKeywords("5kcrm Customer Data");    
		$objProps->setCategory("5kcrm");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
        $ascii = 65;
        $cv = '';
        $field_list = M('Fields')->where('model = \'customer\' ')->order('order_id')->select();
        
        foreach($field_list as $field){
            $objActSheet->setCellValue($cv.chr($ascii).'2', $field['name']);
            $ascii++;
            if($ascii == 91){
                $ascii = 65;
                $cv .= chr(strlen($cv)+65);
            }
        }
        $objActSheet->mergeCells('A1:'.$cv.chr($ascii).'1');
		$objActSheet->getRowDimension('1')->setRowHeight(80);
		$objActSheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFF0000');
		 $objActSheet->getStyle('A1')->getAlignment()->setWrapText(true);
        $content = '请按照规定格式填写地址：省级、市级、区级街道。每级地址之间使用 ALT + ENTER 组合键隔开。'."\n".'特殊地址和直辖市如：北京市市辖区昌平区XXX，格式如下:'."\n".
					'北京市 ALT + ENTER'."\n".
					'市辖区 ALT + ENTER'."\n".
					'昌平区XXX街道';
        $objActSheet->setCellValue('A1', $content);
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition:attachment;filename=5kcrm_customer.xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
    }
	
	public function revert(){
		$customer_id = isset($_GET['id']) ? intval(trim($_GET['id'])) : 0;
		if ($customer_id > 0) {
			$m_customer = M('customer');
			$customer = $m_customer->where('customer_id = %d', $customer_id)->find();
			if ($customer['delete_role_id'] == session('role_id') || session('?admin')) {
				if (isset($customer['is_deleted']) || $customer['is_deleted'] == 1) {
					if ($m_customer->where('customer_id = %d', $customer_id)->setField('is_deleted', 0)) {
						alert('success', '还原成功！', $_SERVER['HTTP_REFERER']);
					} else {
						alert('error', '还原失败！', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '已经还原！', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '您没有权限还原！', $_SERVER['HTTP_REFERER']);
			}
		} else {
			alert('error', '参数错误！', $_SERVER['HTTP_REFERER']);
		}
	}
	
	public function getCustomerList(){	
		$idArray = getSubRoleId();
		$idArray[] = session("role_id");
		
		//获取下级和自己的客户列表,搜索
		$customerList = M('customer')->where('owner_role_id in (%s) and is_deleted = 0', implode(',', $idArray))->select();
		$this->assign('customerlist',$list);

		$this->ajaxReturn($customerList, '', 1);
	}

	//客户关怀列表
	public function cares(){
		$m_cares = M('CustomerCares');
		$below_ids = getSubRoleId(false); 
		$all_ids = getSubRoleId();
		$by = isset($_GET['by']) ? trim($_GET['by']) : '';
		$where = array();
		$params = array();
		
		switch ($by) {
			case 'me' : $where['owner_role_id'] = session('role_id'); break;
			case 'sub' : $where['owner_role_id'] = array('in',implode(',', $below_ids)); break;
			case 'today' :
				$where['care_time'] = array('between',array(strtotime(date('Y-m-d')) -1 ,strtotime(date('Y-m-d')) + 86400));
				break;
			case 'week' : 
				$week = (date('w') == 0)?7:date('w');
				$where['care_time'] = array('between',array(strtotime(date('Y-m-d')) - ($week-1) * 86400 -1 ,strtotime(date('Y-m-d')) + (8-$week) * 86400));
				break;
			case 'month' : 
				$where['start_date'] = array('between',array(strtotime(date('Y-m-01')) -1 ,$month_time));
				break;
			case 'phone' : $where['type'] = 'phone';  break;
			case 'add' : $order = 'create_time desc';  break;
			case 'update' : $order = 'update_time desc';  break;
			case 'message' : $where['type'] = 'message';  break;
			case 'other' : $where['type'] = 'other';  break;
			default : $where['owner_role_id'] = array('in',implode(',', $all_ids)); break;
		}
		if ($by != 'deleted') {
			$where['is_deleted'] = array('neq',1);
		}
		if ($by != 'me' && $by != 'sub') {
			$where['owner_role_id'] = array('in',implode(',', getSubRoleId(true))); 
		}
		
		if ($_REQUEST["field"]) {
			$field = trim($_REQUEST['field']) == 'all' ? 'subject|description' : $_REQUEST['field'];
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
			if	('create_time' == $field || 'update_time' == $field || 'care_time' == $field) {
				$search = is_numeric($search)?$search:strtotime($search);
			}
			switch ($_REQUEST['condition']) {
				case "is" : $where[$field] = array('eq',$search);break;
				case "isnot" :  $where[$field] = array('neq',$search);break;
				case "contains" :  $where[$field] = array('like','%'.$search.'%');break;
				case "not_contain" :  $where[$field] = array('notlike','%'.$search.'%');break;
				case "start_with" :  $where[$field] = array('like',$search.'%');break;
				case "end_with" :  $where[$field] = array('like','%'.$search);break;
				case "is_empty" :  $where[$field] = array('eq','');break;
				case "is_not_empty" :  $where[$field] = array('neq','');break;
				case "gt" :  $where[$field] = array('gt',$search);break;
				case "egt" :  $where[$field] = array('egt',$search);break;
				case "lt" :  $where[$field] = array('lt',$search);break;
				case "elt" :  $where[$field] = array('elt',$search);break;
				case "eq" : $where[$field] = array('eq',$search);break;
				case "neq" : $where[$field] = array('neq',$search);break;
				case "between" : $where[$field] = array('between',array($search-1,$search+86400));break;
				case "nbetween" : $where[$field] = array('not between',array($search,$search+86399));break;
				case "tgt" :  $where[$field] = array('gt',$search+86400);break;
				default : $where[$field] = array('eq',$search);
			}
			$params = array('field='.$field, 'condition='.$condition, 'search='.trim($_REQUEST["search"]));
		}
		
		if ($order) {
			$list = $m_cares->where($where)->order($order)->limit(15)->select();
		} else {
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
			$list = $m_cares->where($where)->page($p.',10')->select();
			$count = $m_cares->where($where)->count();		
			import("@.ORG.Page");
			$Page = new Page($count,10);
			$params[] = 'a=cares';
			$params[] = 'by =' . trim($_GET['by']);
			$Page->parameter = implode('&', $params);
			$show = $Page->show();
			$this->assign('page',$show);
		}
		foreach ($list as $k => $v) {
			$list[$k]["customer"] = M('customer')->where('customer_id = %d', $v['customer_id'])->find();
			$list[$k]["creator"] = getUserByRoleId($v['creator_role_id']);
			$list[$k]["owner"] = getUserByRoleId($v['owner_role_id']);
		}
		$this->assign('caresList',$list);
		$this->alert = parseAlert();
		$this->display();
	}
	public function caresAdd(){
		$m_customer = M('Customer');
		$m_contacts = M('Contacts');
		$m_r_contacts_customer = M('RContactsCustomer');
		if($this->isPost()){
			$m_cares = M('CustomerCares');
			if($m_cares->create()){
				if(!$_POST['subject']) alert('error', '关怀主题不能为空！', $_SERVER['HTTP_REFERER']);
				if($_POST['care_time']) $m_cares->care_time = strtotime($_POST['care_time']);
				$m_cares->create_time = time();
				$m_cares->update_time = time();
				$m_cares->creator_role_id = session('role_id');
				if($m_cares->add()){
					if($_POST['submit'] == '保存'){
						alert('success', '添加成功', U('customer/cares'));
					}else{
						alert('success', '添加成功', U('customer/caresadd'));
					}
				}else{
					alert('error', '添加失败，请联系管理员', $_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '添加失败，请联系管理员', $_SERVER['HTTP_REFERER']);
			}
		}else{
			$customer_id = $_GET['customer_id'];
			$m_customer = M('Customer');
			$customer = $m_customer->where('customer_id = %d',$customer_id)->find();
			if(!empty($customer['contacts_id'])){
				$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$customer['contacts_id'])->find();
				$customer['contacts_name'] = $contacts['name'];
			}else{
				$contacts_customer = $m_r_contacts_customer->where('customer_id = %d',$customer['customer_id'])->limit(1)->order('id desc')->select();
				$contacts = $m_contacts->where('is_deleted = 0 and contacts_id = %d',$contacts_customer[0]['contacts_id'])->find();
				$customer['contacts_id'] = $contacts['contacts_id'];
				$customer['contacts_name'] = $contacts['name'];
			}
			$this->customer = $customer;
			$this->alert = parseAlert();
			$this->display();
		}
	}
	public function caresEdit(){
		if ($this->isPost()) {
			$m_cares = M('CustomerCares');
			if($m_cares->create()){
				if(!$_POST['subject']) alert('error', '关怀主题不能为空！', $_SERVER['HTTP_REFERER']);
				if($_POST['care_time']) $m_cares->care_time = strtotime($_POST['care_time']);
				$m_cares->update_time = time();
				if($m_cares->save()){
					alert('success', '修改成功', U('customer/cares'));
				}else{
					alert('error', '修改失败，请联系管理员', $_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '修改失败，请联系管理员', $_SERVER['HTTP_REFERER']);
			}
		} else {
			$care_id = intval($_GET['id']);
			if($care_id>0){
				$m_care = M('CustomerCares');
				$care = $m_care->where('care_id = %d', $care_id)->find();
				$care['owner'] = getUserByRoleId($care['owner_role_id']);
				$care['customer'] = M('customer')->where('customer_id = %d', $care['customer_id'])->find();
				$care['contacts'] = M('contacts')->where('contacts_id = %d', $care['contacts_id'])->find();
				$this->care = $care;
				$this->alert = parseAlert();
				$this->display();
			}else{
				alert('error', '参数错误', $_SERVER['HTTP_REFERER']);
			}
		}
	}
	
	public function caresView(){
		$care_id = intval($_GET['id']);
		if($care_id>0){
			$m_care = M('CustomerCares');
			$care = $m_care->where('care_id = %d', $_GET['id'])->find();
			if (is_array($care)) {
				
				$care = $m_care->where('care_id = %d', $care_id)->find();
				$care['owner'] = getUserByRoleId($care['owner_role_id']);
				$care['customer'] = M('customer')->where('customer_id = %d', $care['customer_id'])->find();
				$care['contacts'] = M('contacts')->where('contacts_id = %d', $care['contacts_id'])->find();
				$this->care = $care;
				$this->alert = parseAlert();
				$this->display();
			} else {
				alert('error', '记录不存在！', U('customer/cares'));
			}	
		}else{
			alert('error', '参数错误', $_SERVER['HTTP_REFERER']);
		}
	}
	
	public function caresDelete(){
		$m_cares = M('CustomerCares');
		if ($this->isPost()) {
			// foreach($_POST['care_id'] as $k => $v){
				// if($m_cares->where('care_id = %d', $v['care_id'])->getField('owner_role_id') != session('role_id')){
					// alert('error', '您没有全部的权限', U('leads/index'));
				// }
			// }
			$care_id = is_array($_POST['care_id']) ? implode(',', $_POST['care_id']) : '';
			if ('' == $care_id) {
				alert('error', '您没有选择任何内容！', U('customer/cares'));
			} else {
				if($m_cares->where('care_id in (%s)', $care_id)->delete()){					
					alert('success', '删除成功!',U('customer/cares'));
				} else {
					alert('error', '删除失败，联系管理员！', U('customer/cares'));
				}
			}
		} elseif($_GET['id']) {
			$care = $m_cares->where('care_id = %d', $_GET['id'])->find();
			if (is_array($care)) {
				if ($care['owner_role_id'] == session('role_id') || session('?admin')) {			
					if($m_customer->where('customer_id = %d', $_GET['id'])->delete()){
						alert('success', '删除成功!', U('customer/cares'));
					}else{
						alert('error', '删除失败，请联系管理员！', U('customer/cares'));
					}
				} else {
					alert('error', '您没有权限！', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '记录不存在！', U('customer/cares'));
			}
		} else {
			alert('error', '请选择要删除的线索!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function analytics(){
		$m_customer = M('Customer');
		if($_GET['role']) {
			$role_id = intval($_GET['role']);
		}else{
			$role_id = 'all';
		}
		if($_GET['department'] & $_GET['department'] != 'all'){
			$department_id = intval($_GET['department']);
		}else{
			$department_id = D('RoleView')->where('role.role_id = %d', session('role_id'))->getField('department_id');
		}
		if($_GET['start_time']) $start_time = strtotime($_GET['start_time']);
		$end_time = $_GET['end_time'] ?  strtotime($_GET['end_time']) : time();
		if($role_id == "all") {
			$roleList = getRoleByDepartmentId($department_id);
			$role_id_array = array();
			foreach($roleList as $v2){
				$role_id_array[] = $v2['role_id'];
			}
			$where_role_id = array('in', implode(',', $role_id_array));
			$where_source['creator_role_id'] = $where_role_id;
			$where_industry['owner_role_id'] = $where_role_id;
			$where_renenue['creator_role_id'] = $where_role_id;
			$where_employees['creator_role_id'] = $where_role_id;
		}else{
			$where_source['creator_role_id'] = $role_id;
			$where_industry['owner_role_id'] = $role_id;
			$where_renenue['creator_role_id'] = $role_id;
			$where_employees['creator_role_id'] = $role_id;
		}
		if($start_time){
			$where_create_time = array(array('lt',$end_time),array('gt',$start_time), 'and');
			$where_source['create_time'] = $where_create_time;
			$where_industry['create_time'] = $where_create_time;
			$where_renenue['create_time'] = $where_create_time;
			$where_employees['create_time'] = $where_create_time;
			
		}else{
			$where_source['create_time'] = array('lt',$end_time);
			$where_industry['create_time'] = array('lt',$end_time);
			$where_renenue['create_time'] = array('lt',$end_time);
			$where_employees['create_time'] = array('lt',$end_time);
		}
		
		//统计表内容
		$role_id_array = array();
		if($role_id == "all"){
			if($_GET['department'] != 'all'){
				$roleList = getRoleByDepartmentId($department_id);
				foreach($roleList as $v){
					$role_id_array[] = $v['role_id'];
				}
			}else{
				$role_id_array = getSubRoleId();
			}
		}else{
			$role_id_array[] = $role_id;
		}
		if($start_time){
			$create_time= array(array('lt',$end_time),array('gt',$start_time), 'and');
		}else{
			$create_time = array('lt',$end_time);
		}
		$add_count_total = 0;
		$own_count_total = 0;
		$success_count_total = 0;
		$deal_count_total = 0;
		$busi_customer_array = M('Business')->getField('customer_id', true);
		$busi_customer_id=implode(',', $busi_customer_array);
		foreach($role_id_array as $v){
			$user = getUserByRoleId($v);
			$add_count = $m_customer->where(array('is_deleted'=>0, 'creator_role_id'=>$v, 'create_time'=>$create_time))->count();
			$own_count = $m_customer->where(array('is_deleted'=>0, 'owner_role_id'=>$v, 'create_time'=>$create_time))->count();
			$success_count = $m_customer->where(array('is_deleted'=>0, 'customer_id'=>array('in', $busi_customer_id),'owner_role_id'=>$v, 'create_time'=>$create_time))->count();
			$reportList[] = array("user"=>$user,"add_count"=>$add_count,"own_count"=>$own_count,"success_count"=>$success_count);
			$add_count_total += $add_count;
			$own_count_total += $own_count;
			$success_count_total += $success_count;
		}
		
		//来源统计图
		$source_count_array = array();
		$setting = M('Fields')->where("model = 'customer' and field = 'origin'")->getField('setting');
		$setting_str = '$sourceList='.$setting.';';
		eval($setting_str);
		$source_total_count = 0;
		foreach($sourceList[data] as $v){
			unset($where_source['origin']);
			$where_source['origin'] = $v;
			$target_count = $m_customer ->where($where_source)->count();
			$source_count_array[] = '['.'"'.$v.'",'.$target_count.']';
			$source_total_count += $target_count;
		}
		$source_count_array[] = '['.'"'.其他.'",'.($add_count_total-$source_total_count).']';
		$this->source_count = implode(',', $source_count_array);

		
		
		//客户行业统计图
		$industry_count_array = array();
		$setting = M('Fields')->where("model = 'customer' and field = 'industry'")->getField('setting');
		$setting_str = '$industryList='.$setting.';';
		eval($setting_str);
		$where_industry['is_deleted'] = 0;
		$industry_total_count = 0;
		foreach($industryList['data'] as $v){
			unset($where_employees['industry']);
			$where_industry['industry'] = $v;
			$target_count = $m_customer ->where($where_industry)->count();
			$industry_total_count += $target_count;
			$industry_count_array[] = '['.'"'.$v.'",'.$target_count.']';
		}
		$industry_count_array[] = '['.'"'.其他.'",'.($add_count_total-$industry_total_count).']';
		$this->industry_count = implode(',', $industry_count_array);
		//客户员工数统计
		$employees_count_array = array();
		$setting = M('Fields')->where("model = 'customer' and field = 'no_of_employees'")->getField('setting');
		$setting_str = '$no_List='.$setting.';';
		eval($setting_str);
		$where_employees['is_deleted'] = 0;
		$no_total_count = 0;
		foreach($no_List['data'] as $v){
			unset($where_employees['no_of_employees']);
			$where_employees['no_of_employees'] = $v;
			$target_count = $m_customer ->where($where_employees)->count();
			$no_total_count+=$target_count;
			$employees_count_array[] = '['.'"'.$v.'",'.$target_count.']';
		}
		$employees_count_array[] = '['.'"'.其他.'",'.($add_count_total-$no_total_count).']';
		$this->employees_count = implode(',', $employees_count_array);	
		//客户营业额统计
		$revenue_count_array = array();
		$setting = M('Fields')->where("model = 'customer' and field = 'annual_revenue'")->getField('setting');
		$setting_str = '$revenueList='.$setting.';';
		eval($setting_str);
		$where_renenue['is_deleted'] = 0;
		$revenue_total_count = 0; 
		foreach($revenueList['data'] as $v){
			unset($where_renenue['annual_revenue']);
			$where_renenue['annual_revenue'] = $v;
			$target_count = $m_customer ->where($where_renenue)->count();
			$revenue_count_array[] = '['.'"'.$v.'",'.$target_count.']';
			$revenue_total_count+=$target_count;
		}
		$revenue_count_array[] = '['.'"'.其他.'",'.($add_count_total-$target_count).']';
		$this->revenue_count = implode(',', $revenue_count_array);
		
		
		
		$this->total_report = array("add_count"=>$add_count_total, "own_count"=>$own_count_total, "success_count"=>$success_count_total);
		$this->reportList = $reportList;
		
		$idArray = getSubRoleId();
		$roleList = array();
		foreach($idArray as $roleId){				
			$roleList[$roleId] = getUserByRoleId($roleId);
		}
		$this->roleList = $roleList;
		
		$departments = M('roleDepartment')->select();
		$department_id = M('Role')->where('role_id = %d', session('role_id'))->getField('department_id'); 
		$departmentList[] = M('roleDepartment')->where('department_id = %d', $department_id)->find();
		$departmentList = array_merge($departmentList, getSubDepartment($department_id,$departments,''));
		$this->assign('departmentList', $departmentList);
		$this->display();
	}
	public function set(){
		if($this->isPost()){
			if(!empty($_POST['days'])){
				if(M('config') -> where('name="customer_outdays"') -> setField('value',$_POST['days']))
				alert('success', '修改设置成功！', U('customer/index'));
				else alert('success', '数据无变化！', $_SERVER['HTTP_REFERER']);
			}else{
				alert('error', '参数错误！', $_SERVER['HTTP_REFERER']);
			}
		}else{
			$outdays = M('config') -> where('name="customer_outdays"')->getField('value');
			$this->assign('outdays', $outdays);
			$this->display();
		}
	}
}