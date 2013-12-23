<?php 
class BusinessAction extends Action{

	public function _initialize(){
		$action = array(
			'permission'=>array('changecontent'),
			'allow'=>array('close','analytics','getaddchartbyroleid','changecontent','getownchartbyroleid','advance','validate','check','revert')
		);
		B('Authenticate', $action);
	}

	//检验name重复
	public function check() {
		import("@.ORG.SplitWord");
		$sp = new SplitWord();
		$m_business = M('business');
		$useless_words = array('公司','有限','的','有限公司');
		if ($this->isAjax()) {
			$split_result = $sp->SplitRMM($_POST['name']);
			if(!is_utf8($split_result)) $split_result = iconv("GB2312//IGNORE", "UTF-8", $split_result) ;
			$result_array = explode(' ',trim($split_result));
			foreach($result_array as $k=>$v){
				if(in_array($v,$useless_words)) unset($result_array[$k]);
			}
			$name_list = $m_business->getField('name', true);
			$seach_array = array();
			foreach($name_list as $k=>$v){
				$search = 0;
				foreach($result_array as $k2=>$v2){
					if(strpos($v, $v2) > -1){
						$search++;
						$v = str_replace("$v2","<span style='color:red;'>$v2</span>", $v);
					}
				}
				if($search > 0) $customer_search_array[$k] = array('value'=>$v,'search'=>$search);
			}
			$seach_sort_result = array_sort($seach_array,'search','desc');
			if(empty($seach_sort_result)){
				$this->ajaxReturn(0,"可以添加！",0);
			}else{
				$this->ajaxReturn($seach_sort_result,"已创建相近商机！",1);
			}
		}
	}

	
	public function validate() {
		if($this->isAjax()){
            if(!$this->_request('clientid','trim') || !$this->_request($this->_request('clientid','trim'),'trim')) $this->ajaxReturn("","",3);
            $field = M('Fields')->where('model = "Business" and field = "'.$this->_request('clientid','trim').'"')->find();
            $m_customer = $field['is_main'] ? D('Business') : D('BusinessData');
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
	
	public function add(){
		if($this->isPost()){
			$m_business = D('Business');
			$m_business_data = D('BusinessData');
			if (!isset($_POST['name']) || $_POST['name'] == '') {
				alert('error', '商机名不能为空！', $_SERVER['HTTP_REFERER']);
			} elseif ($m_business->where('name = "%s"', trim($_POST['name']))->find()) {
				alert('error', '该商机已存在！',$_SERVER['HTTP_REFERER']);
			}
			$field_list = M('Fields')->where('model = "business"')->order('order_id')->select();
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
							$a =array_filter($_POST[$v['field']]);
							$_POST[$v['field']] = !empty($a) ? implode(chr(10),$a) : '';
						}
					break;
				}
			}
			if($m_business->create() && $m_business_data->create()!==false){
				$m_business->create_time = time();
				$m_business->creator_role_id = session('role_id');
				if($business_id = $m_business->add()){
					$m_business_data->business_id = $business_id;
					$m_business_data->add();
					actionLog($business_id);
					if($_POST['submit'] == "保存") {
						alert('success', '添加商机成功！', U('business/index'));
					} else {
						alert('success', '添加商机成功！', U('business/add'));
					}
				} else {
					alert('error', '添加商机失败！', U('business/index'));
				}
			}else{
				alert('error', $m_business->getError().$m_business_data->getError());
				
				$this->alert = parseAlert();				
				$this->error();
			}
		}else{
			$alert = parseAlert();
			$this->alert = $alert;
			$this->field_list = field_list_html('add','business');
			$this->display();
		}
	}
	
	public function add2(){
		if ($this->isPost()) {
			$name = trim($_POST['name']);
			$d_business = D('Business');
			if ($name==''){
				alert('error', '商机名不能为空', $_SERVER['HTTP_REFERER']);
			} elseif ($d_business->where('name = "%s"', $name)->find()) {
				alert('error', '该商机已创建', $_SERVER['HTTP_REFERER']);
			}
			if ($d_business->create()) {
				$d_business->due_date = isset($_POST['due_date']) ? strtotime($_POST['due_date']) : time();
				$d_business->nextstep_time = strtotime($_POST['nextstep_time']);
				$d_business->creator_role_id = session('role_id');
				$d_business->create_time = time();
				$d_business->update_time = time();
				if ($business_id = $d_business->add()) {
					if($_POST['submit'] == "保存") {
						alert('success', '添加成功！', U('business/index'));
					} else {
						alert('success', '添加成功！', U('business/add'));
					}
				} else {
					alert('error', '商机添加失败，请联系管理员！', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '商机添加失败，请联系管理员！', $_SERVER['HTTP_REFERER']);
			}
		} else {
			$this->statusList = M('BusinessStatus')->order('order_id')->select();
			$this->sourceList = M('InfoSource')->order('order_id')->select();
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function edit(){
		$v_business = D('BusinessView');
		$business = $v_business ->where('business.business_id = %d',$this->_request('id'))->find();
		if (!$business) {
            alert('error', '商机不存在!',$_SERVER['HTTP_REFERER']);
        }
        $field_list = M('Fields')->where('model = "business"')->order('order_id')->select();

		if($this->isPost()){
			$m_business = D('business');
			$m_business_data = D('BusinessData');
			if (!isset($_POST['name']) || $_POST['name'] == '') {
				alert('error', '商机名不能为空！', $_SERVER['HTTP_REFERER']);
			} elseif ($m_business->where('business_id <> %d and name = "%s"',intval($_POST['business_id']), trim($_POST['name']))->find()) {
				alert('error', '该商机已存在！',$_SERVER['HTTP_REFERER']);
			}
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
			if($m_business->create() && $m_business_data->create()!==false){
				$m_business->update_time = time();
				$a = $m_business->where('business_id=' . $business['business_id'])->save();
				$b = $m_business_data->where('business_id=' . $business['business_id'])->save();
				if($a && $b!==false) {
					actionLog($business['business_id']);
					alert('success', '修改商机信息成功！', U('business/index'));
				} else {
					alert('error', '修改商机信息失败！',$_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', $m_business->getError().$m_business_data->getError());
				
				$this->alert = parseAlert();				
				$this->error();
			}
		}else{
			$business['owner'] = getUserByRoleId($business['owner_role_id']);
			$this->business = $business;
			$alert = parseAlert();
			$this->alert = $alert;
			$this->field_list = field_list_html('edit','business',$business);
			$this->display();
		}
	}
	
	public function view(){
		if (intval($this->_request('id')) <= 0) {
			alert('error', '参数错误！', U('business/index'));
		}
		$business_id = $this->_request('id');
	
		$v_business = D('BusinessView');
		$business = $v_business ->where('business.business_id = %d',$this->_request('id'))->find();
		if (!$business) {
            alert('error', '商机不存在!',$_SERVER['HTTP_REFERER']);
        }
        $field_list = M('Fields')->where('model = "business"')->order('order_id')->select();

		$business['customer'] = M('Customer')->where('customer_id = %d', $business['customer_id'])->find();
		$business['contacts'] = M('contacts')->where('contacts_id = %d and is_deleted=0', $business['contacts_id'])->find();
		$business['owner'] = getUserByRoleId($business['owner_role_id']);
		$business['status_id'] = M('BusinessStatus')->where('status_id = %d', $business['status_id'])->getField('name');
		$bsList = M('RBusinessStatus')->where('business_id = %d', $business_id)->select();
		foreach($bsList as $key => $value) {
			$bsList[$key]['status_name'] = M('BusinessStatus')->where('status_id = %d', $value['status_id'])->getField('name');
			$bsList[$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			$bsList[$key]['update'] = D('RoleView')->where('role.role_id = %d', $value['update_role_id'])->find();
		}
		$business['bsList'] = $bsList;
		$log_ids = M('rBusinessLog')->where('business_id = %d', $business_id)->getField('log_id', true);
		$business['log'] = M('log')->where('log_id in (%s)', implode(',', $log_ids))->select();
		$log_count = M('log')->where('log_id in (%s)', implode(',', $log_ids))->count();
		$business['log_count'] = empty($log_count)? 0 : $log_count;
		foreach ($business['log'] as $key=>$value) {
			$business['log'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
		}
		
		$file_ids = M('rBusinessFile')->where('business_id = %d', $business_id)->getField('file_id', true);
		$business['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
		$file_count= M('file')->where('file_id in (%s)', implode(',', $file_ids))->count();
		$business['file_count'] = empty($file_count)? 0 : $file_count;
		foreach ($business['file'] as $key=>$value) {
			$business['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
		}
		
		$task_ids = M('rBusinessTask')->where('business_id = %d', $business_id)->getField('task_id', true);
		$business['task'] = M('task')->where('task_id in (%s) and is_deleted=0', implode(',', $task_ids))->select();
		$task_count = M('task')->where('task_id in (%s) and is_deleted=0', implode(',', $task_ids))->count();
		$business['task_count'] = empty($task_count)? 0 : $task_count;
		foreach ($business['task'] as $key=>$value) {
			$business['task'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
		}
		
		$contract_ids = M('rBusinessContract')->where('business_id = %d', $business_id)->getField('contract_id', true);
		$business['contract'] = M('contract')->where('contract_id in (%s) and is_deleted=0', implode(',', $contract_ids))->select();
		$contract_count = M('contract')->where('contract_id in (%s) and is_deleted=0', implode(',', $contract_ids))->count();
		$business['contract_count'] = empty($contract_count) ? 0 : $contract_count;
		foreach ($business['contract'] as $key=>$value) {
			$business['contract'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			$payables = D('PayablesView')->where(array('payables.contract_id'=>$value['contract_id'],'payables.is_deleted'=>0))->select();
			if(empty($payables) || empty($business['payables'])){
				$business['payables'] = $business['payables']?$business['payables']:$payables;
			}else{
				$business['payables'] = array_merge($payables,$business['payables']);
			}
			$receivables = D('ReceivablesView')->where(array('receivables.contract_id'=>$value['contract_id'],'receivables.is_deleted'=>0))->select();
			if(empty($receivables) || empty($business['receivables'])){
				$business['receivables'] = $business['receivables']?$business['receivables']:$receivables;
			}else{
				$business['receivables'] = array_merge($receivables,$business['receivables']);
			}
		}
		foreach ($business['payables'] as $key=>$value) {
			$business['payables'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
		}
		foreach ($business['receivables'] as $key=>$value) {
			$business['receivables'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
		}
		$business['payables_count'] = count($business['payables']);
		$business['receivables_count'] = count($business['receivables']);
		
		$event_ids = M('rBusinessEvent')->where('business_id = %d', $business_id)->getField('event_id', true);
		$business['event'] = M('event')->where('event_id in (%s)', implode(',', $event_ids))->select();
		$event_count = M('event')->where('event_id in (%s)', implode(',', $event_ids))->count();
		$business['event_count'] = empty($event_count)? 0 : $event_count;
		foreach ($business['event'] as $key=>$value) {
			$business['event'][$key]['owner'] = D('RoleView')->where('role.role_id = %d and is_deleted=0', $value['owner_role_id'])->find();
		}
		
		$business['product'] = M('rBusinessProduct')->where('business_id = %d', $business_id)->select();
		$product_count =  M('rBusinessProduct')->where('business_id = %d', $business_id)->count();
		$business['product_count'] = empty($product_count)? 0 : $product_count;
		$product_category = M('product_category');
		foreach ($business['product'] as $k => $v) {
			$m_product_category = M('productCategory');
			$info = M('product')->where('product_id = %d', $v['product_id'])->find();
			$business['product'][$k]['info'] = $info;
			$business['product'][$k]['category_name'] = $m_product_category->where('category_id = %d',$info['category_id'])->getField('name'); 
		}
		$alert = parseAlert();
		$this->alert = $alert;
		$this->business = $business;
		$this->field_list = $field_list;
		actionLog($business['business_id']);
		$this->display();
	}
	
	public function edit2() {
		if($_POST['submit']){
			$is_updated = false;
			$name = trim($_POST['name']);
			if ($name==''){
				alert('error', '商机名不能为空', $_SERVER['HTTP_REFERER']);
			}
			$m_business = M('business');
			$m_business->create();
			$m_business->update_time = time();
			$m_business->nextstep_time = strtotime($_POST['nextstep_time']);
			$m_business->due_date = strtotime($_POST['due_date']);
			if($m_business->save()) $is_updated = true;
			if($is_updated){
				alert('success', '修改成功！', U('business/view', 'id='.$_POST['business_id']));
			}else{
				alert('error', '修改失败,数据无变化!',$_SERVER['HTTP_REFERER']);
			}
		}elseif($_GET['id']){
			$business = M('business')->where('business_id = %d',$_GET['id'])->find();
			if (is_array($business)) {
				$business['owner']  = D('RoleView')->where('role.role_id = %d', $business['owner_role_id'])->find();
				$business['customer'] = M('Customer')->where('customer_id = %d', $business['customer_id'])->find();
				$business['contacts'] = M('contacts')->where('contacts_id = %d', $business['contacts_id'])->find();
				$business['rstatus'] = M('RBusinessStatus')->where('business_id = %d and status_id=%d', $business['business_id'], $business['status_id'])->find();
				
				// $id_array = M('RBusinessStatus')->where('business_id = %d', $business['business_id'])->getField('status_id', true);
				// $id_array[] = 0;
				// $statusList = M('BusinessStatus')->where('status_id not in (%s)', implode(',', $id_array))->select();
				// $statusList[] = M('BusinessStatus')->where('status_id = %d', $business['status_id'])->find();
				$order_id = M('BusinessStatus')->where('status_id = %d', $business['status_id'])->getField('order_id');
				$order_id = $order_id ? intval($order_id) : 0;
				$statusList = M('BusinessStatus')->where('order_id >= %d', $order_id)->order('order_id')->select();
				$this->sourceList = M('InfoSource')->order('order_id')->select();
				$this->statusList = $statusList;
				$this->business = $business;
				$this->alert = parseAlert();
				$this->display();
			} else {
				alert('error', '数据不存在!',$_SERVER['HTTP_REFERER']);
			}
		}else{
			alert('error', '参数错误!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function completeDelete(){
		$m_business = M('business');
		$m_business_data = M('BusinessData');
		$r_module = array('RBusinessCustomer', 'Event'=>'RBusinessEvent', 'File'=>'RBusinessFile', 'Log'=>'RBusinessLog', 'RBusinessProduct', 'Task'=>'RBusinessTask');
		if (!session('?admin')) {
			alert('error', '非管理员不能删除回收站的内容', $_SERVER['HTTP_REFERER']);
		}
		if ($this->isPost()) {
			$business_ids = is_array($_POST['business_id']) ? implode(',', $_POST['business_id']) : '';
			if ('' == $business_ids) {
				alert('error', '您没有选择任何内容！', U('business/index'));
			} else {
				//if(M('Contract')->where('business_id in(%s)', $business_ids)->select()) alert('error', '您所选的商机中已创建合同', U('business/index'));;
				if($m_business->where('business_id in (%s)', $business_ids)->delete() && $m_business_data->where('business_id in (%s)', $business_ids)->delete()){
					foreach ($_POST['business_id'] as $value) {
						actionLog($value);
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('business_id = %d', $value)->getField($key2 . '_id',true);
							M($value2)->where('business_id = %d', $value) -> delete();
							if(!is_int($key2)){
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
					}
					alert('success', '删除成功!',$_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '删除失败！', U('business/index'));
				}
			}
		} elseif($_GET['id']) {
			$business_id = intval($_GET['id']);
			$business = $m_business->where('business_id = %d', $business_id)->find();
			if (is_array($business)) {
				if($m_business->where('business_id = %d', $business_id)->delete()){
					actionLog($_GET['id']);
					foreach ($r_module as $key2=>$value2) {
						if(!is_int($key2)){
							$module_ids = M($value2)->where('business_id = %d', $business_id)->getField($key2 . '_id',true);
							$m_key = M($key2);
							$m_key->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							M($value2)->where('business_id = %d', $business_id)->delete();
						}
					}
					alert('success', '删除成功!',U('business/index'));
				} else {
					alert('error', '删除失败！', U('business/index'));
				}
			} else {
				alert('error', '您要删除的纪录不存在！', $_SERVER['HTTP_REFERER']);
			}
		} else {
			alert('error', '请选择要删除的项!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function delete(){
		$m_business = M('business');
		
		$business_ids = is_array($_REQUEST['business_id']) ? implode(',', $_REQUEST['business_id']) : $_REQUEST['id'];
		if ('' == $business_ids) {
			actionLog($business_ids);
			alert('error', '您没有选择任何内容！', U('business/index'));
		} else {
			foreach($_REQUEST['business_id'] as $v){
				actionLog($v);
			}
			$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
			if($m_business->where('business_id in (%s)', $business_ids)->setField($data)){	
				alert('success', '删除成功!',U('business/index'));
			} else {
				alert('error', '删除失败，联系管理员！', U('business/index'));
			}
		}
	}
	
	public function revert(){
		$business_id = isset($_GET['id']) ? intval(trim($_GET['id'])) : 0;
		if ($business_id > 0) {
			$m_business = M('business');
			$business = $m_business->where('business_id = %d', $business_id)->find();
			if (session('?admin') || $business['delete_role_id'] == session('role_id')) {
				if ($m_business->where('business_id = %d', $business_id)->setField('is_deleted', 0)) {
					alert('success', '还原成功！', $_SERVER['HTTP_REFERER']);
				} else {
					alert('error', '还原失败！', $_SERVER['HTTP_REFERER']);
				}
			} else {
				alert('error', '您没有权限还原！', $_SERVER['HTTP_REFERER']);
			}
		} else {
			alert('error', '参数错误！', $_SERVER['HTTP_REFERER']);
		}
	}
	
	public function index(){
		$d_v_business = D('BusinessView');
		$below_ids = getSubRoleId(false);
		$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
		$by = isset($_GET['by']) ? trim($_GET['by']) : '';
		$where = array();
		$params = array();
		$order = "";
		
		switch ($by) {
			case 'create' : $where['creator_role_id'] = session('role_id'); break;
			case 'sub' : $where['owner_role_id'] = array('in',implode(',', $below_ids)); break;
			case 'subcreate' : $where['creator_role_id'] = array('in',implode(',', $below_ids)); break;
			case 'today' : 
				$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-d', time()))+86400), array('gt',0), 'and'); 
				break;
			case 'week' : 
				$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-d', time())) + (8-date('N', time())) * 86400), array('gt', 0),'and');
				break;
			case 'month' : 
				$where['nextstep_time'] =  array(array('lt',strtotime(date('Y-m-01', strtotime('+1 month')))), array('gt', 0),'and'); 
				break;
			case 'd7' : 
				$where['update_time'] =  array('lt',strtotime(date('Y-m-d', time()))-86400*6); 
				break;
			case 'd15' : 
				$where['update_time'] =  array('lt',strtotime(date('Y-m-d', time()))-86400*14); 
				break;
			case 'd30' : 
				$where['update_time'] =  array('lt',strtotime(date('Y-m-d', time()))-86400*29); 
				break;
			case 'deleted' : $where['is_deleted'] = 1; break;
			case 'add' : $order = 'create_time desc'; break;
			case 'update' : $order = 'update_time desc'; break;
			case 'me' : $where['business.owner_role_id'] = session('role_id'); break;
			default : $where['business.owner_role_id'] = array('in',implode(',', getSubRoleId())); break;
		}
		
		if($by){
			if($by != 'deleted') {
				if(!$_REQUEST["field"] || ($_REQUEST["field"] != 'status_id' && $_REQUEST["field"])) $where['business.status_id'] = array(array('neq', 99), array('neq', 100), 'and');
			}
		}else{
			if(!$_REQUEST["field"] || ($_REQUEST["field"] != 'status_id' && $_REQUEST["field"])) $where['business.status_id'] = array(array('neq', 99), array('neq', 100), 'and');
		}
		if (!isset($where['is_deleted'])) {
			$where['business.is_deleted'] = 0;
		}
		if (!isset($where['business.owner_role_id'])) {
			$where['business.owner_role_id'] = array('in',implode(',', getSubRoleId())); 
		}
		if ($_REQUEST["field"]) {
			if (trim($_REQUEST['field']) == "all") {
				$field = is_numeric(trim($_REQUEST['search'])) ? 'name|origin|type|description|estimate_price|gain_rate|gain_cycle|sales_price|product_amount|total_price|estimate_income' : 'name|origin|type|description';
			} else {
				$field = trim($_REQUEST['field']);
			}
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
//--------datetime字段验证			
			$field_date = M('Fields')->where('(is_main=1 and model="" and form_type="datetime") or (is_main=1 and model="business" and form_type="datetime")')->select();
			foreach($field_date as $v){
				if	($field == $v['field']) $search = is_numeric($search)?$search:strtotime($search);
			}
//--------address字段验证				
			if ($this->_request('state') || $this->_request('city')) {
				$search = $this->_request('state') .chr(10) . $this->_request('city') .chr(10) .$search;
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

			if ($this->_request('state') || $this->_request('city')) {
				$params = array('field='.trim($_REQUEST['field']), 'state='.trim($_REQUEST['field']), 'city='.trim($_REQUEST['field']), 'condition='.$condition, 'search='.$where);//$_REQUEST["search"]
			}else{
				$params = array('field='.trim($_REQUEST['field']), 'condition='.$condition, 'search='.$search );//$_REQUEST["search"]
			}
		}
		$order = empty($order) ? 'business.update_time desc' : $order;
		$list = $d_v_business->where($where)->order($order)->page($p.',15')->select();
		$count =  $d_v_business->where($where)->count();
		import("@.ORG.Page");
		$Page = new Page($count,15);
		if (!empty($_GET['by'])) {
			$params[] = "by=".trim($_GET['by']);
		}
		$Page->parameter = implode('&', $params);
		$this->assign('page', $Page->show());
		foreach($list as $key => $value){
			$list[$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			$list[$key]['creator'] = D('RoleView')->where('role.role_id = %d', $value['creator_role_id'])->find();
			$list[$key]['customer_name'] = M('customer')->where('customer_id = %s',$value['customer_id'])->getField('name');
			$list[$key]['status_name'] = M('BusinessStatus')->where('status_id = %d', $value['status_id'])->getField('name');
			if($by == 'deleted') {
				$list[$key]["delete_role"] = D('RoleView')->where('role.role_id = %d', $value['delete_role_id'])->find();
			}
		}
		$d_role_view = D('RoleView');
		$this->role_list = $d_role_view->where('role.role_id in (%s)', implode(',', $below_ids))->select();
		$this->customer_list = M('customer')->where('owner_role_id in (%s)', implode(',', getSubRoleId()))->select();
		$this->assign('list',$list);// 赋值数据集
	
		$this->search_field_array = getMainFields('business');
		$this->field_array = getIndexFields('business');
		$this->alert = parseAlert();
	    $this->display(); // 输出模板
	}
	
	public function listDialog(){
		$d_business = D('BusinessView');
		$underling_ids = getSubRoleId();
		$list = $d_business->order('business.create_time desc')->where('business.owner_role_id in (%s) and business.is_deleted = 0',implode(',', $underling_ids))->limit(10)->select();
		foreach($list as $k=>$v){
			$list[$k]['customer_name'] = M('Customer')->where('customer_id = %d', $v['customer_id'])->getField('name');
		}
		$count = $d_business->where('business.owner_role_id in (%s) and business.is_deleted = 0',implode(',', $underling_ids))->count();
		$this->total = $count%10 > 0 ? ceil($count/10) : $count/10;
		$this->count_num = $count;
		$this->assign('businessList',$list);
		$this->display();
	}
	
	public function changeContent(){
		if($this->isAjax()){
			$m_business = D('BusinessView');
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
			$where = array();
			$order = "";
			if($_REQUEST["field"] != 'business.status') $where['business.status_id'] = array(array('neq', 99), array('neq', 100), 'and');
			
			$where['is_deleted'] = 0;
			$where['owner_role_id'] = array('in',implode(',', getSubRoleId()));
			
			if ($_REQUEST["field"]) {
				if (trim($_REQUEST['field']) == "all") {
					$field = is_numeric(trim($_REQUEST['search'])) ? 'business.name|business.origin|business.description|business.estimate_price|business.gain_rate|business.gain_cycle|business.sales_price|business.product_amount|business.total_price|business.estimate_income' : 'business.name|business.origin|business.description';
				} else {
					$field = trim($_REQUEST['field']);
				}
				$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
				$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);

				if	('business.create_time' == $field || 'business.update_time' == $field || 'business.due_date' == $field) {
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
			}
			
			$p = !$_REQUEST['p']||$_REQUEST['p']<=0 ? 1 : intval($_REQUEST['p']);
			$list = $m_business->where($where)->order('business.create_time desc')->page($p.',10')->select();		
			foreach($list as $key => $value){
				$list[$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
				$list[$key]['creator'] = D('RoleView')->where('role.role_id = %d', $value['creator_role_id'])->find();
				$list[$key]['customer_name'] = M('customer')->where('customer_id = %s',$value['customer_id'])->getField('name');
				$list[$key]['status_name'] = M('BusinessStatus')->where('status_id = %d', $value['status_id'])->getField('name');
				if($by == 'deleted') {
					$list[$key]["delete_role"] = D('RoleView')->where('role.role_id = %d', $value['delete_role_id'])->find();
				}
				if(!$list[$key]['customer_name']) $list[$key]['customer_name'] = '';
				if(!$list[$key]['customer_id']) $list[$key]['customer_id'] = '';
				if($list[$key]['estimate_price'] == 0) $list[$key]['estimate_price'] = '';
				if(!$list[$key]['status_name']) $list[$key]['status_name'] = '';
				$list[$key]['customer_name'] = M('Customer')->where('customer_id = %d', $value['customer_id'])->getField('name');
			}
	
			$count = $m_business->where($where)->count();
			$data['list'] = $list;
			$data['p'] = $p;
			$data['count'] = $count;
			$data['total'] = $count%10 > 0 ? ceil($count/10) : $count/10;
			$this->ajaxReturn($data,"",1);
		}
	}

	
	public function view2(){ 
		$business_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if (0 == $business_id) {
			alert('error', '参数错误！', U('business/index'));
		} else {
			$business = M('business')->where('business_id = %d', $business_id)->find();
			$business['owner'] = D('RoleView')->where('role.role_id = %d', $business['owner_role_id'])->find();
			$business['creator'] = D('RoleView')->where('role.role_id = %d', $business['creator_role_id'])->find();
			$business['customer'] = M('Customer')->where('customer_id = %d', $business['customer_id'])->find();
			$business['contacts'] = M('contacts')->where('contacts_id = %d', $business['contacts_id'])->find();
			$business['status_name'] = M('BusinessStatus')->where('status_id = %d', $business['status_id'])->getField('name');
			$business['source'] = M('InfoSource')->where('source_id = %d', $business['source_id'])->getField('name');
			$business['status_description'] = M('RBusinessStatus')->where('status_id = %d and business_id = %d', $business['status_id'],$business['business_id'])->getField('description');
			$bsList = M('RBusinessStatus')->where('business_id = %d', $business_id)->select();
			foreach($bsList as $key => $value) {
				$bsList[$key]['status_name'] = M('BusinessStatus')->where('status_id = %d', $value['status_id'])->getField('name');
				$bsList[$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
				$bsList[$key]['update'] = D('RoleView')->where('role.role_id = %d', $value['update_role_id'])->find();
			}
			$business['bsList'] = $bsList;
			
			$log_ids = M('rBusinessLog')->where('business_id = %d', $business_id)->getField('log_id', true);
			$business['log'] = M('log')->where('log_id in (%s)', implode(',', $log_ids))->select();
			$log_count = M('log')->where('log_id in (%s)', implode(',', $log_ids))->count();
			$business['log_count'] = empty($log_count)? 0 : $log_count;
			foreach ($business['log'] as $key=>$value) {
				$business['log'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				
			}
			
			$file_ids = M('rBusinessFile')->where('business_id = %d', $business_id)->getField('file_id', true);
			$business['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$file_count= M('file')->where('file_id in (%s)', implode(',', $file_ids))->count();
			$business['file_count'] = empty($file_count)? 0 : $file_count;
			foreach ($business['file'] as $key=>$value) {
				$business['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
			}
			
			$task_ids = M('rBusinessTask')->where('business_id = %d', $business_id)->getField('task_id', true);
			$business['task'] = M('task')->where('task_id in (%s)', implode(',', $task_ids))->select();
			$task_count = M('task')->where('task_id in (%s)', implode(',', $task_ids))->count();
			$business['task_count'] = empty($task_count)? 0 : $task_count;
			foreach ($business['task'] as $key=>$value) {
				$business['task'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			}
			
			$contract_ids = M('rBusinessContract')->where('business_id = %d', $business_id)->getField('contract_id', true);
			$business['contract'] = M('contract')->where('contract_id in (%s)', implode(',', $contract_ids))->select();
			$contract_count = M('contract')->where('contract_id in (%s)', implode(',', $contract_ids))->count();
			$business['contract_count'] = empty($contract_count) ? 0 : $contract_count;
			foreach ($business['contract'] as $key=>$value) {
				$business['contract'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
				$payables = D('PayablesView')->where(array('payables.contract_id'=>$value['contract_id'],'payables.is_deleted'=>0))->select();
				if(empty($payables) || empty($business['payables'])){
					$business['payables'] = $business['payables']?$business['payables']:$payables;
				}else{
					$business['payables'] = array_merge($payables,$business['payables']);
				}
				$receivables = D('ReceivablesView')->where(array('receivables.contract_id'=>$value['contract_id'],'receivables.is_deleted'=>0))->select();
				if(empty($receivables) || empty($business['receivables'])){
					$business['receivables'] = $business['receivables']?$business['receivables']:$receivables;
				}else{
					$business['receivables'] = array_merge($receivables,$business['receivables']);
				}
			}
			foreach ($business['payables'] as $key=>$value) {
				$business['payables'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			}
			foreach ($business['receivables'] as $key=>$value) {
				$business['receivables'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			}
			$business['payables_count'] = count($business['payables']);
			$business['receivables_count'] = count($business['receivables']);
			
			$event_ids = M('rBusinessEvent')->where('business_id = %d', $business_id)->getField('event_id', true);
			$business['event'] = M('event')->where('event_id in (%s)', implode(',', $event_ids))->select();
			$event_count = M('event')->where('event_id in (%s)', implode(',', $event_ids))->count();
			$business['event_count'] = empty($event_count)? 0 : $event_count;
			foreach ($business['event'] as $key=>$value) {
				$business['event'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
			}
			
			$business['product'] = M('rBusinessProduct')->where('business_id = %d', $business_id)->select();
			$product_count =  M('rBusinessProduct')->where('business_id = %d', $business_id)->count();
			$business['product_count'] = empty($product_count)? 0 : $product_count;
			$product_category = M('product_category');
			foreach ($business['product'] as $k => $v) {
				$m_product_category = M('productCategory');
				$info = M('product')->where('product_id = %d', $v['product_id'])->find();
				$business['product'][$k]['info'] = $info;
				$business['product'][$k]['category_name'] = $m_product_category->where('category_id = %d',$info['category_id'])->getField('name'); 
			}
			$this->business = $business;
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function logging(){
		$this->temp =M('Business')->where('business_id =' . $_GET['id'])->find(); 
		
		$logging = M('BusinessLogging');
		$a = $logging->where('business_id =' . $_GET['id'])->order('create_time desc')->select();
		foreach($a as $key => $value){
			$count = count(unserialize($value['filepath']));
			if(empty($value['filepath']) || $value['filepath']==''){
				$count = 0;
			}
			$a[$key]['count'] = $count;//添加附件数元素
		}
		$this->loggingList = $a;
		$this->display();
	}

	public function addLogging(){
		if($_POST['title']){
			if (array_sum($_FILES['file']['size'])) {
				//如果有文件上传 上传附件
				import('@.ORG.UploadFile');
				//导入上传类
				$upload = new UploadFile();
				//设置上传文件大小
				$upload->maxSize = 3292200;
				//设置上传文件类型
				$upload->allowExts  = array('jpg', 'gif', 'png', 'jpeg','xls');// 设置附件上传类型
				//设置附件上传目录
				$dirname = './uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					$this->error("附件上传目录不可写!");
				}
				$upload->savePath = $dirname;
				
				if(!$upload->upload()) {// 上传错误提示错误信息
					$this->error($upload->getErrorMsg());
				}else{// 上传成功 获取上传文件信息
					$info =  $upload->getUploadFileInfo();
				}
            }
			
			$files = array();
			foreach($info as $key=>$value){
				$files[] = array('name' => $value['name'], 'filepath' => $value['savepath'] . $value['savename']);
			}
			$logging = D('BusinessLogging');
			if($logging->create()){	
				$logging->filepath = serialize($files); 
				$logging->add();
				$this->success('添加成功！');
			}else{
				$this->error('添加失败，联系管理员');
			}
		}else{
			$this->id = $_GET['id'];
			$this->display();
		}
	}
	
	public function editLogging(){
		$logging = M('BusinessLogging');
		if($_GET['id']){		
			$temp= $logging->where('logging_id =' . $_GET['id'])->find();
			$fileList = unserialize($temp['filepath']);
			$this->files = $fileList;
			$this->vo = $temp;
			$this->display();
		}else if($_GET['editId']){
			if (array_sum($_FILES['file']['size'])) {
				//如果有文件上传 上传附件
				import('@.ORG.UploadFile');
				//导入上传类
				$upload = new UploadFile();
				//设置上传文件大小
				$upload->maxSize = 3292200;
				//设置上传文件类型
				$upload->allowExts  = array('jpg', 'gif', 'png', 'jpeg','xls');// 设置附件上传类型
				//设置附件上传目录
				$dirname = './uploads/'.date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					$this->error("附件上传目录不可写!");
				}
				$upload->savePath = $dirname;
				
				if(!$upload->upload()) {// 上传错误提示错误信息
					$this->error($upload->getErrorMsg());
				}else{// 上传成功 获取上传文件信息
					$info =  $upload->getUploadFileInfo();
				}
            }
			
			$files = array();
			foreach($_POST['exist_files'] as $value){
				$array = explode("|",$value);
				$name = $array[0];
				$filepath = $array[1];
				$files[] = array('name' => $name, 'filepath' => $filepath);
			}
			foreach($info as $key=>$value){
				$files[] = array('name' => $value['name'], 'filepath' => $value['savepath'] . $value['savename']);
			}

			$logging->create(); 
			$logging->filepath = serialize($files);
			if($logging->where('logging_id =' . $_GET['editId'])->save()){
				$this->success('修改成功！','/Business/logging/?id=' . $logging->where('logging_id =' . $_GET['editId'])->getField('business_id'));
			}else{
				$this->error('数据无变化，修改失败！');
			}
		}	
	}
	
	public function deleteLogging(){
		$logging = M('BusinessLogging');
		if($logging->where('logging_id=' . $_GET['id'])->delete()){
			$this->success('删除成功！');
		}else{
			$this->error('删除失败！');
		}
	}
	
	public function excelExport(){
		import("ORG.PHPExcel.PHPExcel");
		$objPHPExcel = new PHPExcel();    
		$objProps = $objPHPExcel->getProperties();    
		$objProps->setCreator("5kcrm");    
		$objProps->setLastModifiedBy("5kcrm");    
		$objProps->setTitle("5kcrm Business Data");    
		$objProps->setSubject("5kcrm Business Data");    
		$objProps->setDescription("5kcrm Business Data");    
		$objProps->setKeywords("5kcrm Business Data");    
		$objProps->setCategory("5kcrm");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
        $ascii = 65;
        $cv = '';
        $field_list = M('Fields')->where('model = \'business\'')->order('order_id')->select();
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
		$list = M('business')->where($where)->select();
		
		$i = 1;
		foreach ($list as $k => $v) {
            $data = M('BusinessData')->where("business_id = $v[business_id]")->find();
            if(!empty($data)){
                $v = $v+$data;
            }
			$i++;
            $ascii = 65;
            $cv = '';
            foreach($field_list as $field){
                if($field['form_type'] == 'datetime'){
					if($v[$field['field']] == 0 || strlen($v[$field['field']]) != 10){
						$objActSheet->setCellValue($cv.chr($ascii).$i, '');
					}else{
						$objActSheet->setCellValue($cv.chr($ascii).$i, date('Y-m-d',$v[$field['field']]));
					} 
                }elseif($field['field'] == 'customer_id'){
					$m_customer = M('Customer');
					$customer = $m_customer->where('customer_id = %d',$v['customer_id'])->find();
					$objActSheet->setCellValue($cv.chr($ascii).$i, $customer['name']);
				}elseif($field['field'] == 'contacts_id'){
					$m_contacts = M('Contacts');
					$contacts = $m_contacts->where('contacts_id = %d',$v['contacts_id'])->find();
					$objActSheet->setCellValue($cv.chr($ascii).$i, $contacts['name']);
				}elseif($field['field'] == 'status_id'){
					$m_business_status = M('BusinessStatus');
					$business_status = $m_business_status->where('status_id = %d',$v['status_id'])->find();
					$objActSheet->setCellValue($cv.chr($ascii).$i, $business_status['name']);
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
        header("Content-Disposition:attachment;filename=5kcrm_business_".date('Y-m-d',mktime()).".xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
	}
	
	public function excelImport(){
		$m_business = D('Business');
		$m_business_data = D('BusinessData');
		
		if($_POST['submit']){
			if (isset($_FILES['excel']['size']) && $_FILES['excel']['size'] != null) {
				import('@.ORG.UploadFile');
				$upload = new UploadFile();
				$upload->maxSize = 20000000;
				$upload->allowExts  = array('xls');
				$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					alert('error', '附件上传目录不可写', U('business/index'));
				}
				$upload->savePath = $dirname;
				if(!$upload->upload()) {
					alert('error', $upload->getErrorMsg(), U('business/index'));
				}else{
					$info =  $upload->getUploadFileInfo();
				}
			}
			if(is_array($info[0]) && !empty($info[0])){
				$savePath = $dirname . $info[0]['savename'];
			}else{
				alert('error', '上传失败', U('business/index'));
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
			if($allRow <= 1){
				alert('error', '上传文件无有效数据', U('business/index'));
			}else{
				$field_list = M('Fields')->where('model = \'business\'')->order('order_id')->select();
				for($currentRow = 2;$currentRow <= $allRow;$currentRow++){
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
                            $data_date[$field['field']] = ($field['form_type'] == 'datetime' && $info != null) ? intval(PHPExcel_Shared_Date::ExcelToPHP($info))-8*60*60 : $info;;
                        }
                        
                        $ascii++;
                        if($ascii == 91){
                            $ascii = 65;
                            $cv .= chr(strlen($cv)+65);
                        }
                    }
                    if ($m_business->create($data) && $m_business_data->create($data_date)) {
                        $business_id = $m_business->add();
                        $m_business_data->business_id = $business_id;
                        $m_business_data->add();
					}else{
                        alert('error', '导入至第' . $currentRow . '行出错'.$m_business->getError().$m_business_data->getError(), U('business/index'));
                    }
				}
				alert('success', '导入成功！', U('business/index'));
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
		$objProps->setTitle("5kcrm Business");    
		$objProps->setSubject("5kcrm Business Data");    
		$objProps->setDescription("5kcrm Business Data");    
		$objProps->setKeywords("5kcrm Business Data");    
		$objProps->setCategory("5kcrm");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
        $ascii = 65;
        $cv = '';
        $field_list = M('Fields')->where('model = \'business\' ')->order('order_id')->select();
        foreach($field_list as $field){
            $objActSheet->setCellValue($cv.chr($ascii).'1', $field['name']);
            $ascii++;
            if($ascii == 91){
                $ascii = 65;
                $cv .= chr(strlen($cv)+65);
            }
        }
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition:attachment;filename=5kcrm_business.xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
    }
	
	public function advance(){
		if($this->isPost()){
			$id = $_POST['business_id'];
			
			$is_updated = false;
			
			$m_r_bs = D('RBusinessStatus');
			$business = M('business')->where('business_id = %d', $_POST['business_id'])->find();			
			$data['business_id'] = $business['business_id'];
			$data['gain_rate'] = $business['gain_rate'];
			$data['status_id'] = $business['status_id'];
			if($business['description'])	$data['description'] = $business['description'];
			$data['owner_role_id'] = $business['owner_role_id'];
			$data['update_time'] = $business['update_time'];
			$data['update_role_id'] = $business['update_role_id'];
			$m_r_bs->add($data);
			
			$m_business = M('business');
			$data2['update_time'] = time();
			$data2['status_id'] = $_POST['status_id'];
			$data2['nextstep_time'] = strtotime($_POST['nextstep_time']);
			$data2['nextstep'] = $_POST['nextstep'];
			$data2['description'] = $_POST['description'];
			$data2['update_role_id'] = session('role_id');
			if($m_business->where('business_id = %d', $id)->save($data2)){
				M('customer')->where('customer_id = %d',$business['customer_id'])->setField('update_time',time());
				alert('success', '推进成功！', $_SERVER['HTTP_REFERER']);
			}else{
				alert('error', '推进失败,数据无变化!',$_SERVER['HTTP_REFERER']);
			}
			
		}elseif($this->isGet()){
			$id = intval(trim($_GET['id']));
			if($id > 0){
				$status_id = M('Business')->where('business_id = %d', $id)->getField('status_id');
				$order_id = M('BusinessStatus')->where('status_id = %d', $status_id)->getField('order_id');
				if(!$order_id) $order_id = 0;
				$statusList =  M('BusinessStatus')->where('order_id >= %d', $order_id)->order('order_id')->select();
				$this->statusList = $statusList;
				$this->business_id = $id;
				$this->display();
			}else{
				alert('error', '参数错误!',$_SERVER['HTTP_REFERER']);
			}
		}
	}
	/*定制商机状态流 <td>{$vo.status_name} &nbsp; </td>
	<td>
		<!-- Icons -->
		<a href="/business/status_change/?id={$vo.business_id}">{$vo.next_status}</a>
	</td>
	
	public function status_change(){
		$flow = M('Business_status_flow');
		$business = M('Business');
		$current_status = $business->where('business_id = %d',$_GET['id'])->getField('status');
		$data = $flow->where('flow_id = 5')->getField('data');
		$status_flow = unserialize($data);
		foreach($status_flow as $key=>$value){
			if($value == $current_status){
				if($status_flow[$key+1]){
					$current_status=$status_flow[$key+1];
					break;
				}else{
					$this->error('已是最终状态');
				}
			}
		}
		$temp['status'] = $current_status;
		if($business->where('business_id = %d',$_GET['id'])->save($temp)){
			$this->success('前进到了下一状态！');
		}else{
			$this->error('状态前进失败');
		}
	}
	*/

	public function analytics(){
		$m_business = M('Business');
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
			$where_source['creator_role_id'] = array('in', implode(',', $role_id_array));
			$where_status['owner_role_id'] = array('in', implode(',', $role_id_array));
			$where_money['owner_role_id'] = array('in', implode(',', $role_id_array));
		}else{
			$where_source['creator_role_id'] = $role_id;
			$where_status['owner_role_id'] = $role_id;
			$where_money['owner_role_id'] = $role_id;
		}
		if($start_time){
			$where_source['create_time'] = array(array('lt',$end_time),array('gt',$start_time), 'and');
			$where_status['create_time'] = array(array('lt',$end_time),array('gt',$start_time), 'and');
			$where_money['create_time'] = array(array('lt',$end_time),array('gt',$start_time), 'and');
		}else{
			$where_source['create_time'] = array('lt',$end_time);
			$where_status['create_time'] = array('lt',$end_time);
			$where_money['create_time'] = array('lt',$end_time);
		}
		
		//统计表内容
		$role_id_array = array();
		if($role_id == "all"){
			if($department_id != "all"){
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
		foreach($role_id_array as $v){
			$user = getUserByRoleId($v);
			$add_count = $m_business->where(array('is_deleted'=>0, 'creator_role_id'=>$v, 'create_time'=>$create_time))->count();
			$own_count = $m_business->where(array('is_deleted'=>0, 'owner_role_id'=>$v, 'create_time'=>$create_time))->count();
			$success_count = $m_business->where(array('is_deleted'=>0, 'status_id'=>100,'owner_role_id'=>$v, 'create_time'=>$create_time))->count();
			$deal_count = $m_business->where('is_deleted = 0 and status_id not in(99,100) and owner_role_id = %d and update_time>create_time', $v)->count();
			$reportList[] = array("user"=>$user,"add_count"=>$add_count,"own_count"=>$own_count,"success_count"=>$success_count,"deal_count"=>$deal_count);
			$add_count_total += $add_count;
			$own_count_total += $own_count;
			$success_count_total += $success_count;
			$deal_count_total += $deal_count;
		}
		//商机来源统计图
		$source_count_array = array();
		$setting = M('Fields')->where("model = 'business' and field = 'origin'")->getField('setting');
		$setting_str = '$sourceList='.$setting.';';
		eval($setting_str);
		$where_source['is_deleted'] = 0;
		$source_total_count = 0;
		foreach($sourceList['data'] as $v){
			unset($where_source['origin']);
			$where_source['origin'] = $v;
			$target_count = $m_business ->where($where_source)->count();
			$source_count_array[] = '['.'"'.$v.'",'.$target_count.']';
			$source_total_count += $target_count;
		}
		$source_count_array[] = '['.'"'.其他.'",'.($add_count_total-$source_total_count).']';
		$this->source_count = implode(',', $source_count_array);
		//商机阶段统计图
		$status_count_array = array();
		$statusList = M('BusinessStatus')->order('order_id desc')->where('status_id <> 99')->select();
		$where_status['is_deleted'] = 0;
		$temp_count = 0;
		foreach($statusList as $v){
			unset($where_status['status_id']);
			$where_status['status_id'] = $v['status_id'];
			$target_count = $m_business ->where($where_status)->count();
			$status_count_array[] = '['.'"'.$v['name'].'",'.($target_count+$temp_count).']';
			$temp_count += $target_count;
		}
		$this->status_count = implode(',', array_reverse($status_count_array));
	
		$max_money = $m_business->where($where_money)->Max('total_price');
		$rank1 = format_price($max_money * 0.1);
		$rank2 = format_price($max_money * 0.3);
		$rank3 = format_price($max_money * 0.5);
		$rank4 = format_price($max_money * 0.8);
		$rank5 = format_price($max_money);
		$money_where = array(
			array('name'=>$rank1.'元以下','where_money'=>array(array('lt',$rank1),array('gt',0), 'and')),
			array('name'=>$rank1.'~'.$rank2.'元','where_money'=>array(array('elt',$rank2),array('gt',$rank1), 'and')),
			array('name'=>$rank2.'~'.$rank3.'元','where_money'=>array(array('elt',$rank3),array('gt',$rank2), 'and')),
			array('name'=>$rank3.'~'.$rank4.'元','where_money'=>array(array('elt',$rank4),array('gt',$rank3), 'and')),
			array('name'=>$rank4.'~'.$rank5.'元','where_money'=>array(array('elt',$rank5),array('gt',$rank4), 'and'))
		);

		$money_count_array = array();
		foreach($money_where as $v){
			$where_money['total_price'] = array();
			$where_money['total_price'] = $v['where_money'];
			$target_count = $m_business ->where($where_money)->count();
			$money_count_array[] = '['.'"'.$v['name'].'",'.$target_count.']';
		}
		$this->money_count = implode(',', $money_count_array);

		$this->total_report = array("add_count"=>$add_count_total, "own_count"=>$own_count_total, "success_count"=>$success_count_total, "deal_count"=>$deal_count_total);
		$this->reportList = $reportList;
		
		$idArray = getSubRoleId();
		$roleList = array();
		foreach($idArray as $roleId){				
			$roleList[$roleId] = getUserByRoleId($roleId);
		}
		$this->roleList = $roleList;
		
		$departments = M('roleDepartment')->select();
		$department_id = M('Role')->where('role_id = %d', session('role_id'))->getField('department_id'); 
		$departmentList[] = M('roleDepartment')->where('department_id = %d', $department_id)->find();$departmentList = array_merge($departmentList, getSubDepartment($department_id,$departments,''));
		$this->assign('departmentList', $departmentList);
		$this->display();
	}
	
	public function analytics2(){
		$source_count_array = array();
		$sourceList = M('InfoSource')->select();
		foreach($sourceList as $v){
			$source_count_array['count'][] = M('Business')->where('source_id = %d', $v['source_id'])->count();
			$source_count_array['name'][] = '\''.$v['name'].'\'';
		}
		$source_count_array['count'] = implode(',', $source_count_array['count']);
		$source_count_array['name'] = implode(',', $source_count_array['name']);
		$this->source_count_array = $source_count_array;
		
		$status_count_array = array();
		$statusList = M('BusinessStatus')->order('order_id')->select();
		foreach($statusList as $v){
			$status_count_array['count'][] = M('Business')->where('status_id = %d', $v['status_id'])->count();
			$status_count_array['name'][] = '\''.$v['name'].'\'';
		}
		$status_count_array['count'] = implode(',', $status_count_array['count']);
		$status_count_array['name'] = implode(',', $status_count_array['name']);
		
		$idArray = getSubRoleId();
		$roleList = array();
		foreach($idArray as $roleId){				
			$roleList[$roleId] = getUserByRoleId($roleId);
		}
		$this->roleList = $roleList;
		
		$this->status_count_array = $status_count_array;
		$id = session('role_id');
		$this->role_add_chart = $this->getAddDataByRoleId($id);
		$this->role_own_chart = $this->getOwnDataByRoleId($id);
		$this->display();
	}
}