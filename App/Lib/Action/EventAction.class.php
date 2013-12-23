<?php
class EventAction extends Action{
	public function _initialize(){
		$action = array(
			'permission'=>array('tips'),
			'allow'=>array('close')
		);
		B('Authenticate', $action);
	}

	public function add(){
		if($this->isPost()){
			$m_event = M('Event'); 
			if($m_event->create()){
				$subject = trim($_POST['subject']);
				if($subject=='' || $subject==null){
					alert('error', '请填写日程主题！', $_SERVER['HTTP_REFERER']);
				}
				$m_event->start_date = strtotime($_POST['start_date']);
				$m_event->end_date = strtotime($_POST['end_date']);
				$m_event->create_date = time();
				$m_event->update_date = time();
				$m_event->creator_role_id = session('role_id');
				if($event_id = $m_event->add()){
					$module = isset($_POST['module']) ? $_POST['module'] : '';
					if($_POST['id']) {
						$data[$_POST['module2'] . '_id'] = $_POST['id'];
						$data['event_id'] = $event_id;
						if(M($_POST['r'])->add($data)<=0){
							alert('error', '关联失败！', $_SERVER['HTTP_REFERER']);
						}
					} elseif($module != ''){
						switch ($module) {
							case 'contacts' : $m_r = M('RContactsEvent'); $module_id = 'contacts_id'; break;
							case 'leads' : $m_r = M('REventLeads'); $module_id = 'leads_id'; break;
							case 'customer' : $m_r = M('RCustomerEvent'); $module_id = 'customer_id'; break;
							case 'product' : $m_r = M('REventProduct'); $module_id = 'product_id'; break;
							case 'business' : $m_r = M('RBusinessEvent'); $module_id = 'business_id'; break;
						}
						if ($_POST['module_id']) {
							$data[$module_id] = intval($_POST['module_id']);
							$data['event_id'] = $event_id;
							$rs = $m_r->add($data);
							if ($rs<=0) {
								alert('error', '关联失败！', $_SERVER['HTTP_REFERER']);
							}
						} else {
							alert('error', '请选择对应项', $_SERVER['HTTP_REFERER']);
						}
					}
					
					if($_POST['send_email']) {
						C(F('smtp'),'smtp');
						//C('','smtp');
						import('@.ORG.Mail');
						$to_user = D('RoleView')->where('role.role_id = %d', $_POST['owner_role_id'])->find();
						
						$content ='尊敬的' . $to_user['user_name'] . ':  <br/><br/> &nbsp; &nbsp; &nbsp; 这是一封系统自动生成的日程通知邮件!
						<br/> &nbsp; &nbsp; &nbsp; 内容如下：<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 主题：' . $_POST['subject'] . '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;日程开始日期:' . $_POST['start_date'] . ' &nbsp; 日程截止日期:' . $_POST['end_date'] .  '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 日程地点:' . $_POST['venue'] . '<br/> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 日程内容：' . $_POST['description'];
						
						$send =  SendMail($to_user['email'],'悟空CRM日程通知',$content,'悟空CRM管理员');
					}
					actionLog($event_id);
					if($_POST['submit'] == "保存") {
						alert('success', '添加成功！', U('event/index'));
					} elseif($_POST['submit'] == '保存并新建') {
						alert('success', '添加成功！', U('event/add'));
					} else {
						alert('success', '添加成功！', $_SERVER['HTTP_REFERER']);
					}
				}
			}else{
				alert('error', '日程添加失败!',$_SERVER['HTTP_REFERER']);
			}
		}elseif($_GET['r'] & $_GET['module'] & $_GET['id']){
			$this->r = $_GET['r'];
			$this->module2 = $_GET['module'];
			$this->id = $_GET['id'];
			$this->display('Event:add_dialog');
		}elseif($_POST['dialog_add']){
			$module = $_POST['module'];
			$r = $_POST['r'];
			$id = $_POST['id'];
			$m_event = M('Event');
			if($m_event->create()){
				$m_event->start_date = strtotime($_POST['start_date']);
				$m_event->end_date = strtotime($_POST['end_date']);
				
				$m_event->create_date = time();
				$m_event->update_date = time();
				if($event_id = $m_event->add()){
					$data[$module . '_id'] = $id;
					$data['event_id'] = $event_id;
					if(M($r)->add($data)){
						actionLog($event_id);
						alert('success', '添加成功！', $_SERVER['HTTP_REFERER']);
					}else{
						alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
					}
				}else{
					alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '添加失败，请联系管理员!', $_SERVER['HTTP_REFERER']);
			}
		}else{
			$this->alert = parseAlert();
			$this->display();
		}
	}
	
	public function close(){
		$id = isset($_GET['id']) ? $_GET['id'] : 0; 
		if($id >= 0){
			$m_event = M('event');
			if($m_event->where('event_id = %d', $id)->setField('isclose', 0)){
				alert('success', '已关闭', $_SERVER['HTTP_REFERER']);
			} else {
				alert('error', '关闭日程失败!', $_SERVER['HTTP_REFERER']);
			}
		}else{
			alert('error', '参数错误', $_SERVER['HTTP_REFERER']);
		}
	}
	
	public function edit(){
		if ($_POST['owner_name']) {
			$m_event = M('Event');
			$m_event->create();
			$subject = trim($_POST['subject']);
			if($subject=='' || $subject==null){
				alert('error', '请填写日程主题！', $_SERVER['HTTP_REFERER']);
			}
			
			$m_event->start_date = strtotime($_POST['start_date']);
			$m_event->end_date = strtotime($_POST['end_date']);
			$m_event->update_date = time();
			
			$module = isset($_POST['module']) ? $_POST['module'] : '';
			$event_id = intval($_POST['event_id']);
			$is_updated = false;
			if ($module != '') {
				switch ($module) {
					case 'contacts' : $m_r = M('RContactsEvent'); $module_id = 'contacts_id'; break;
					case 'leads' : $m_r = M('REventLeads'); $module_id = 'leads_id'; break;
					case 'customer' : $m_r = M('RCustomerEvent'); $module_id = 'customer_id'; break;
					case 'product' : $m_r = M('REventProduct'); $module_id = 'product_id'; break;
					case 'business' : $m_r = M('RBusinessEvent'); $module_id = 'business_id'; break;
				}
				if ($_POST['module_id']) {
					if (!$m_r->where('event_id = %d and '.$module.'_id = %d', $task_id, intval($_POST['module_id']))->find()) {
						$r_module = array('Leads'=>'REventLeads', 'Business'=>'RBusinessEvent', 'Product'=>'REventProduct', 'Customer'=>'RCustomerEvent', 'Event'=>'RContactsEvent');
						foreach ($r_module as $key=>$value) {
							$r_m = M($value);
							$r_m->where('event_id = %d', $event_id)->delete();
						}
						$data[$module_id] = intval($_POST['module_id']);
						$data['event_id'] = $event_id;
						$rs = $m_r->add($data);
						if ($rs<=0) {
							alert('error', '关联失败！', $_SERVER['HTTP_REFERER']);
						}
						$is_updated = true;
					}
				} else {
					alert('error', '请选择对应项', $_SERVER['HTTP_REFERER']);
				}
			}
			if ($m_event->save()) $is_updated = true;
			
			if($is_updated){
				actionLog($event_id);
				if($_POST['submit'] == "保存") {
					alert('success', '日程信息修改成功！', U('event/index'));
				} else {
					alert('success', '日程信息修改成功！', U('event/add'));
				}
			}else{
				alert('error', '无数据变化，修改失败！',$_SERVER['HTTP_REFERER']); 
			}
		} elseif($_GET['id']) {
			$d_event = M('Event');
			$event = $d_event->where('event_id = %d',$_GET['id'])->find();
			
			$event['owner'] = D('RoleView')->where('role.role_id = %d', $event['owner_role_id'])->find();
			
			$r_module = array('Leads'=>'REventLeads', 'Business'=>'RBusinessEvent', 'Product'=>'REventProduct', 'Customer'=>'RCustomerEvent', 'Event'=>'RContactsEvent');
			
			foreach ($r_module as $key=>$value) {
				$r_m = M($value);				
				if($module_id = $r_m->where('event_id = %d', trim($_GET['id']))->getField($key . '_id')){
					if($key == 'Leads') {
						$leads = M($key)->where($key.'_id = %d', $module_id)->find();
						$name = $leads['name']. ' ' . $leads['company'];
					} else {
						$name = M($key)->where($key.'_id = %d', $module_id)->getField('name');
					}
					$module = M($key)->where($key.'_id = %d', $module_id)->find();
					if($key == 'Leads') {
						$name = $module['name']. ' ' . $module['company'];
					} else {
						$name = $module['name'];
					}
					$event['module']=array('module_name'=>$key,'name'=>$name,'module_id'=>$module_id);
					break;
				}
			}
			
			$this->event = $event;
			$this->alert = parseAlert();
			$this->display();
		} else {
			$this->error('参数错误');
		}
	}
	
	public function delete(){
		$m_event = M('Event');
		$r_module = array('Log'=>'REventLog', 'File'=>'REventFile', 'REventLeads', 'RBusinessEvent', 'REventProduct', 'RCustomerEvent', 'RContactsEvent');
		if($this->isPost()){
			$event_ids = is_array($_POST['event_id']) ? implode(',', $_POST['event_id']) : '';
			if ('' == $event_ids) {
				alert('error', '您没有选择任何内容！', U('event/index'));
			} else {
				if(!session('?admin')){
					foreach($_POST['event_id'] as $key => $value){
						if(!$m_event->where('owner_role_id = %d and event_id = %d', session('role_id'), $value) -> find()){
							alert('error', '您没有全部权限进行操作！', $_SERVER['HTTP_REFERER']);
						}
					}
				}
				if($m_event->where('event_id in (%s)', $event_ids)->delete()){	
					foreach ($_POST['event_id'] as $value) {
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('event_id = %d', $value)->getField($key2 . '_id', true);
							M($value2)->where('event_id = %d', $value) -> delete();
							if(!is_int($key2)){	
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
					}
					actionLog($event_id);
					alert('success', '删除成功!',U('event/index'));
				} else {
					alert('error', '删除失败，联系管理员！', U('event/index'));
				}
			}
		} elseif($_GET['id']) {
			$event = $m_event->where('event_id = %d', $_GET['id'])->find();
			if (is_array($event)) {
				if($event['owner_role_id'] == session('role_id') || session('?admin')){
					if($m_event->where('event_id = %d', $_GET['id'])->delete()){
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('event_id = %d', $_GET['id'])->getField($key2 . '_id', true);
							M($value2)->where('event_id = %d', $_GET['id']) -> delete();
							if(!is_int($key2)){
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
						if($_GET['redirect']){
							actionLog($event_id);
							alert('success', '删除成功！', U('event/index'));
						} else {
							actionLog($event_id);
							alert('success', '删除成功！', $_SERVER['HTTP_REFERER']);
						}
					}else{
						alert('error', '删除失败，请联系管理员！', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '您没有权限！', $_SERVER['HTTP_REFERER']);
				}
					
			} else {
				alert('error', '记录不存在！', $_SERVER['HTTP_REFERER']);
			}			
		} else {
			alert('error', '请选择要删除的线索!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function index(){
		$m_event = M('Event');
		$below_ids = getSubRoleId(false); 
		$all_ids = getSubRoleId(); 
		$by = isset($_GET['by']) ? trim($_GET['by']) : '';
		$where = array();
		$params = array();
		$order = "";
		
		switch ($by) {
			case 'today' :
				$data1['start_date'] = array('lt', strtotime(date('Y-m-d')) -1 );
				$data1['end_date'] = array('gt', strtotime(date('Y-m-d')) -1 );
				$data['start_date'] = array('between',array(strtotime(date('Y-m-d')) -1 ,strtotime(date('Y-m-d')) + 86400));
				$data['_logic'] = 'or';
				$data['_complex'] = $data1;
				$where['_complex'] = $data;
				break;
			case 'week' : 
				$week = (date('w') == 0)?7:date('w');
				$data1['start_date'] = array('lt', strtotime(date('Y-m-d')) - ($week-1) * 86400 -1 );
				$data1['end_date'] = array('gt', strtotime(date('Y-m-d')) - ($week-1) * 86400 -1 );
				$data['start_date'] = array('between',array(strtotime(date('Y-m-d')) - ($week-1) * 86400 -1 ,strtotime(date('Y-m-d')) + (8-$week) * 86400));
				$data['_logic'] = 'or';
				$data['_complex'] = $data1;
				$where['_complex'] = $data;
				break;
			case 'month' : 
				$data1['start_date'] = array('lt', strtotime(date('Y-m-01')) -1 );
				$data1['end_date'] = array('gt', strtotime(date('Y-m-01')) -1 );
				$next_year = date('Y')+1;
				$next_month = date('m')+1;
				$month_time = date('m') ==12 ? strtotime($next_year.'-01-01') : strtotime(date('Y').'-'.$next_month.'-01');
				$data['start_date'] = array('between',array(strtotime(date('Y-m-01')) -1 ,$month_time));
				$data['_logic'] = 'or';
				$data['_complex'] = $data1;
				$where['_complex'] = $data;
				break;
			case 'add' : $order = 'create_date desc';  break;
			case 'update' : $order = 'update_date desc';  break;
			case 'sub' : $where['owner_role_id'] = array('in',implode(',', $below_ids)); break;
			case 'public' : $where['owner_role_id'] = ''; break;
			case 'deleted' : $where['is_deleted'] = 1; break;
			case 'transformed' : $where['is_transformed'] = 1; break;
			case 'me' :
				$where['owner_role_id'] = session('role_id');
				break;
			default: 
				$where['owner_role_id'] = array('in',implode(',', $all_ids)); 
				break;
		}

		if (!isset($where['owner_role_id'])) {
			$where['owner_role_id'] = array('in',implode(',', $all_ids));
		}
		if($_GET['by'] != 'deleted') {
			$where['is_deleted'] = 0;
		}

		if ($_REQUEST["field"]) {
			$field = trim($_REQUEST['field']) == 'all' ? 'subject|description' : $_REQUEST['field'];
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
			if	('create_date' == $field || 'update_date' == $field || 'start_date' == $field || 'end_date' == $field) {
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
			$list = $m_event->order($order)->limit(15)->select();
		} else {
			$p = isset($_GET['p']) ? intval($_GET['p']) : 1 ;
			$list = $m_event->where($where)->page($p.',10')->select();
			
			$count = $m_event->where($where)->count();

			import("@.ORG.Page");
			$Page = new Page($count,10);
			$params[] = 'by =' . trim($_GET['by']);
  			$Page->parameter = implode('&', $params);
			$show = $Page->show();		
			$this->assign('page',$show);
		}
		$user = M('User');
		foreach($list as $key=>$value){
			$list[$key]["owner"] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
		}
		
		$this->assign('eventlist',$list);
		$this->alert = parseAlert();
		$this->display();
	}  
	
	public function view(){
		$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if (0 == $event_id) {
			alert('error', '参数错误！', U('event/index'));
		} else {
			$event = M('Event')->where('event_id = %d',$event_id)->find();
			
			$r_module = array('Leads'=>'REventLeads', 'Business'=>'RBusinessEvent', 'Product'=>'REventProduct', 'Customer'=>'RCustomerEvent', 'Contacts'=>'RContactsEvent');
			
			foreach ($r_module as $key=>$value) {
				$r_m = M($value);
				if($module_id = $r_m->where('event_id = %d', $event_id)->getField($key . '_id')){
					if($key == 'Leads') {
						$leads = M($key)->where($key.'_id = %d', $module_id)->find();
						$name = $leads['first_name'].$leads['last_name']. ' ' . $leads['company'];
					} else {
						$name = M($key)->where($key.'_id = %d', $module_id)->getField('name');
					}
					switch ($key){
						case 'Product' : $module_name='产品'; break;
						case 'Leads' : $module_name='线索'; break;
						case 'Contacts' : $module_name='联系人'; break;
						case 'Business' : $module_name='商机'; break;
						case 'Customer' : $module_name='客户'; break;
					}
					$event['module']=array('module'=>$key,'module_name'=>$module_name,'name'=>$name,'module_id'=>$module_id);
					break;
				}
			}
			$log_ids = M('rEventLog')->where('event_id = %d', $event_id)->getField('log_id', true);
			$event['log'] = M('log')->where('log_id in (%s)', implode(',', $log_ids))->select();
			$log_count = 0;
			foreach ($event['log'] as $key=>$value) {
				$event['log'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$log_count++;
			}
			$event['log_count'] = $log_count;
			
			$file_ids = M('rEventFile')->where('event_id = %d', $event_id)->getField('file_id', true);
			$event['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$file_count = 0;
			foreach ($event['file'] as $key=>$value) {
				$event['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$file_count++;
			}
			$event['file_count'] = $file_count;
			
			$event["owner"] = D('RoleView')->where('role.role_id = %d', $event['owner_role_id'])->find();
			$this->event = $event;
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
		$objProps->setTitle("5kcrm Event Data");    
		$objProps->setSubject("5kcrm Event Data");    
		$objProps->setDescription("5kcrm Event Data");    
		$objProps->setKeywords("5kcrm Event Data");    
		$objProps->setCategory("Event");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
		$objActSheet->setCellValue('A1', '主题');
		$objActSheet->setCellValue('B1', '地点');
		$objActSheet->setCellValue('C1', '负责人');
		$objActSheet->setCellValue('D1', '开始时间');
		$objActSheet->setCellValue('E1', '结束时间');
		$objActSheet->setCellValue('F1', '是否发送通知邮件（1是0否）');
		$objActSheet->setCellValue('G1', '内容');
		$objActSheet->setCellValue('H1', '创建人');
		$objActSheet->setCellValue('I1', '创建时间');
		
		$where['owner_role_id'] = array('in',implode(',', getSubRoleId()));
		$list = M('event')->where($where)->select();
		$i = 1;
		foreach ($list as $k => $v) {
			$i++;
			$creator = D('RoleView')->where('role.role_id = %d', $v['creator_role_id'])->find();
			$owner = D('RoleView')->where('role.role_id = %d', $v['owner_role_id'])->find();
			$objActSheet->setCellValue('A'.$i, $v['subject']);
			$objActSheet->setCellValue('B'.$i, $v['venue']);
			$objActSheet->setCellValue('C'.$i, $owner['user_name'].'['.$owner['department_name'].'-'.$owner['role_name'].']');
			$v['start_date'] == 0 || strlen($v['start_date']) != 10 ? '' : $objActSheet->setCellValue('D'.$i, date("Y-m-d", $v['start_date']));
			$v['end_date'] == 0 || strlen($v['end_date']) != 10 ?  '': $objActSheet->setCellValue('E'.$i, date("Y-m-d", $v['end_date']));
			$v['send_email'] == 0 ? $objActSheet->setCellValue('F'.$i, '否') : $objActSheet->setCellValue('F'.$i, '是');
			$objActSheet->setCellValue('G'.$i, $v['description']);
			$objActSheet->setCellValue('H'.$i, $creator['user_name'].'['.$creator['department_name'].'-'.$creator['role_name'].']');
			$objActSheet->setCellValue('I'.$i, date("Y-m-d H:i:s", $v['create_date']));
		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition:attachment;filename=5kcrm_event_".date('Y-m-d',mktime()).".xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
	}
	
	public function excelImport(){
		$m_event = M('event');
		if($_POST['submit']){
			if (isset($_FILES['excel']['size']) && $_FILES['excel']['size'] != null) {
				import('@.ORG.UploadFile');
				$upload = new UploadFile();
				$upload->maxSize = 20000000;
				$upload->allowExts  = array('xls');
				$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					alert('error', '附件上传目录不可写', U('event/index'));
				}
				$upload->savePath = $dirname;
				if(!$upload->upload()) {
					alert('error', $upload->getErrorMsg(), U('event/index'));
				}else{
					$info =  $upload->getUploadFileInfo();
				}
			}
			if(is_array($info[0]) && !empty($info[0])){
				$savePath = $dirname . $info[0]['savename'];
			}else{
				alert('error', '上传失败', U('event/index'));
			};
			import("ORG.PHPExcel.PHPExcel");
			$PHPExcel = new PHPExcel();
			$PHPReader = new PHPExcel_Reader_Excel2007();
			if(!$PHPReader->canRead($savePath)){
				$PHPReader = new PHPExcel_Reader_Excel5();
			}
			$PHPExcel = $PHPReader->load($savePath);
			$currentSheet = $PHPExcel->getSheet(0);
			$allRow = $currentSheet->getHighestRow();
			for ($currentRow = 2;$currentRow <= $allRow;$currentRow++) {
				$data['subject'] = $currentSheet->getCell('B'.$currentRow)->getValue();
				$data['venue'] = $currentSheet->getCell('C'.$currentRow)->getValue();
				$data['owner_role_id'] = $currentSheet->getCell('F'.$currentRow)->getValue();
				$data['start_date'] = strtotime($currentSheet->getCell('H'.$currentRow)->getValue());
				$data['end_date'] = strtotime($currentSheet->getCell('I'.$currentRow)->getValue());
				$data['recurring'] = $currentSheet->getCell('J'.$currentRow)->getValue();
				$data['send_email'] = $currentSheet->getCell('K'.$currentRow)->getValue();
				$data['description'] = $currentSheet->getCell('L'.$currentRow)->getValue();
				$data['creator_role_id'] = $currentSheet->getCell('O'.$currentRow)->getValue();
				$data['create_date'] = strtotime($currentSheet->getCell('P'.$currentRow)->getValue());
				$data['update_date'] = strtotime($currentSheet->getCell('Q'.$currentRow)->getValue());
				$m_event->add($data);
			}
			alert('success', '导入成功', U('event/index'));
		}else{
			$this->display();
		}
	}

	public function tips(){
		$m_task = M('Event');
		$current_time = time();
		$num = $m_task->where("owner_role_id = %d and isclose = 0 and is_deleted <> 1 and $current_time > start_date and $current_time < end_date", session('role_id'))->count();
		$this->ajaxReturn($num,"",1);
	}
}