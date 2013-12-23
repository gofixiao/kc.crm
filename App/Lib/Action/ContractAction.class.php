<?php 
class ContractAction extends Action {
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('changecontent')
		);
		B('Authenticate', $action);
	}
	public function add(){
		if($_POST['submit']){
			$contract = M('contract');
			$data['number'] = (trim($_POST['number']) && (trim($_POST['number']) != '自动生成')) ?trim($_POST['number']):'5KCRM'.date('Ymd').rand(1000,9999);
			$data['due_time'] = $_POST['due_time']?strtotime($_POST['due_time']):time();
			$data['business_id'] = $_POST['business_id']?$_POST['business_id']:alert('error','请选择商机',$_SERVER['HTTP_REFERER']);
			$data['owner_role_id'] = $_POST['owner_role_id']?$_POST['owner_role_id']:session('role_id');
			$data['creator_role_id'] = session('role_id');
			$data['price'] = intval($_POST['price']);
			$data['content'] = trim($_POST['content']);
			$data['description'] = trim($_POST['description']);
			$data['start_date'] = strtotime($_POST['start_date']);
			$data['end_date'] = strtotime($_POST['end_date']);
			$data['create_time'] = time();
			$data['update_time'] = time();
			$data['status'] = '已创建';
			if($contractId = $contract->add($data)){
				M('RBusinessContract')->add(array('contract_id'=>$contractId,'business_id'=>$data['business_id']));
				if('保存' == $_POST['submit']){
					alert('success', '创建合同成功', U('contract/index'));
				}else{
					alert('success', '创建合同成功', $_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('success', '创建合同失败', U('contract/add'));
			}
			
		}else{
			if(intval($_GET['business_id'])){
				$this->assign('business_id',intval($_GET['business_id']));
				$this->alert = parseAlert();
				$this->display('adddialog');
			}else{
				$this->alert = parseAlert();
				$this->display();
			}
		}
	}
	
	public function edit(){
		$contract = D('ContractView');
		$contract_id = intval($_REQUEST['id']) ? intval($_REQUEST['id']) : alert('error', '参数错误!',$_SERVER['HTTP_REFERER']);
		$contract_info = $contract->where('contract.contract_id = %d',$contract_id)->find();

		if (is_array($contract_info)) {
			if($_POST['submit']){
				$data['due_time'] = $_POST['due_time']?strtotime($_POST['due_time']):time();
				$data['business_id'] = $_POST['business_id']?$_POST['business_id']:alert('error','请选择商机',$_SERVER['HTTP_REFERER']);
				$data['owner_role_id'] = $_POST['owner_role_id']?$_POST['owner_role_id']:session('role_id');
				$data['price'] = intval($_POST['price']);
				$data['content'] = trim($_POST['content']);
				$data['description'] = trim($_POST['description']);
				$data['start_date'] = strtotime($_POST['start_date']);
				$data['end_date'] = strtotime($_POST['end_date']);

				$data['update_time'] = time();
				$data['status'] = $_POST['status'];
				if(M('contract')->where(array('contract_id'=>$contract_id))->save($data)){
					M('rBusinessContract')->where(array('contract_id'=>$contract_id))->save(array('business_id'=>$data['business_id']));
					alert('success', '修改成功',U('contract/view','id='.$contract_id));
				}else{
					alert('success', '数据无变化',$_SERVER['HTTP_REFERER']);
				}
			}else{
				$this->assign('info',$contract_info);
				$this->alert = parseAlert();
				$this->display();
			}
		}else{
			alert('error', '数据不存在!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function view(){
		$contract_id = intval($_REQUEST['id']);
		$contract = D('ContractView');
		if (0 == $contract_id) alert('error', '您没有选择任何内容！', U('contract/index'));
		
		$info = $contract->where(array('contract_id'=>$contract_id))->find();
		if(empty($info)) alert('error', '合同不存在或已被删除！', U('contract/index'));
		$info['creator_name'] = M('user')->where('role_id = %d', $info['creator_role_id'])->getField('name');
		
		$info['product'] = M('rContractProduct')->where('contract_id = %d', $contract_id)->select();
		$product_count =  M('rContractProduct')->where('contract_id = %d', $contract_id)->count();
		$info['product_count'] = empty($product_count)? 0 : $product_count;
		foreach ($info['product'] as $k => $v) {
			$m_product_category = M('productCategory');
			$product = M('product')->where('product_id = %d', $v['product_id'])->find();
			$info['product'][$k]['info'] = $product;
			$info['product'][$k]['category_name'] = $m_product_category->where('category_id = %d',$product['category_id'])->getField('name'); 
		}
		
		
		$info['receivables'] = D('ReceivablesView')->where('receivables.contract_id = %d and receivables.is_deleted=0', $contract_id)->select();
		foreach ($info['receivables'] as $k=>$v){
			$info['receivables'][$k]['owner'] = getUserByRoleId($v['owner_role_id']);
		}
		
		$receivables_count =  D('ReceivablesView')->where('receivables.contract_id = %d and receivables.is_deleted=0', $contract_id)->count();
		
		$info['payables'] = D('PayablesView')->where('payables.contract_id = %d and payables.is_deleted=0', $contract_id)->select();
		foreach ($info['payables'] as $k=>$v){
			$info['payables'][$k]['owner'] = getUserByRoleId($v['owner_role_id']);
		}
		$payables_count =  D('PayablesView')->where('payables.contract_id = %d and payables.is_deleted=0', $contract_id)->count();
		$info['finance_count'] = $receivables_count + $payables_count;
		
		$file_ids = M('rContractFile')->where('contract_id = %d', $contract_id)->getField('file_id', true);
		$info['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
		$file_count = 0;
		foreach ($info['file'] as $key=>$value) {
			$info['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
			$file_count++;
		}
		$info['file_count'] = $file_count;
		
		
		$this->assign('info',$info);
		$this->alert = parseAlert();
		$this->display();

	}
	
	public function completeDelete(){
		$contract_id = is_array($_REQUEST['contract_id']) ? implode(',', $_REQUEST['contract_id']) : $_REQUEST['contract_id'];
		if ('' == $contract_id) {
			alert('error', '您没有选择任何内容！', U('contract/index'));
		} else {
			if(M('contract')->where('contract_id in (%s)', $contract_id)->delete()){
				M('rBusinessContract')->where(array('contract_id'=>$contract_id))->delete();
				M('rContractProduct')->where(array('contract_id'=>$contract_id))->delete();
				alert('success', '删除成功!',U('contract/index'));
			} else {
				alert('error', '删除失败', U('contract/index'));
			}
		}
	}
	
	public function revert(){
		$contract_id = isset($_GET['id']) ? intval(trim($_GET['id'])) : 0;
		if ($contract_id > 0) {
			$m_contract = M('contract');
			$contract = $m_contract->where('contract_id = %d', $contract_id)->find();
			if (session('?admin') || $contract['delete_role_id'] == session('role_id')) {
				if ($m_contract->where('contract_id = %d', $contract_id)->setField('is_deleted', 0)) {
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
	
	public function delete(){
		$contract_ids = is_array($_REQUEST['contract_id']) ? $_REQUEST['contract_id'] : array($_REQUEST['contract_id']);
		if ('' == $contract_ids) {
			alert('error', '您没有选择任何内容！', U('contract/index'));
		} else {
			$m_contract = M('Contract');
			$m_receivables = M('Receivables');
			$m_payables = M('Payables');
			$m_r_contract_product = M('rContractProduct');
			$m_r_contract_file = M('rContractFile');
			//如果合同下有产品，财务和文件信息，提示先删除产品，财务和文件数据。
			$data = array('is_deleted'=>1, 'delete_role_id'=>session('role_id'), 'delete_time'=>time());
			foreach($contract_ids as $k=>$v){
				$contract = $m_contract->where('contract_id = %d',$v)->find();
				$contract_product = $m_r_contract_product->where('contract_id = %d',$v)->select();//合同关联的产品记录
				$contract_file = $m_r_contract_file->where('contract_id = %d',$v)->select();//合同关联的文件
				$contract_receivables = $m_receivables->where('is_deleted <> 1 and contract_id = %d',$v)->select();//合同关联的应收款
				$contract_payables = $m_payables->where('is_deleted <> 1 and contract_id = %d',$v)->select();//合同关联的应付款
				
				if(empty($contract_product) && empty($contract_file) && empty($contract_receivables) && empty($contract_payables)){
					if(!$m_contract->where('contract_id = %d', $v)->save($data)){
						alert('error','删除失败，请联系管理员!',$_SERVER['HTTP_REFERER']);
					}
				}else{
					if(!empty($contract_product)){
						alert('error', '删除失败！请先删除 “'.$contract['number'].'” 合同下的产品信息！', U('contract/index'));
					}elseif(!empty($contract_file)){
						alert('error', '删除失败！请先删除 “'.$contract['number'].'” 合同下的文件信息！', U('contract/index'));
					}elseif(!empty($contract_receivables)){
						alert('error', '删除失败！请先删除 “'.$contract['number'].'” 合同中财务下的应收款信息！', U('contract/index'));
					}else{
						alert('error', '删除失败！请先删除 “'.$contract['number'].'” 合同中财务下的应付款信息！', U('contract/index'));
					}
				}
			}
			alert('success','删除成功!',U('contract/index'));
		}
	}
	
	public function index(){
		$contract = D('ContractView');
		$below_ids = getSubRoleId(false);
		$all_ids = getSubRoleId();
		$where = array();
		$order = 'contract.create_time desc';
		switch ($_GET['by']){
			case 'create':
				$where['contract.creator_role_id'] = session('role_id');
				break;
			case 'sub' : 
				$where['contract.owner_role_id'] = array('in',implode(',', $below_ids)); 
				break;
			case 'subcreate' : 
				$where['contract.creator_role_id'] = array('in',implode(',', $below_ids)); 
				break;
			case 'today' : 
				$where['contract.due_time'] =  array('between',array(strtotime(date('Y-m-d')) -1 ,strtotime(date('Y-m-d')) + 86400)); 
				break;
			case 'week' : 
				$week = (date('w') == 0)?7:date('w');
				$where['contract.due_time'] =  array('between',array(strtotime(date('Y-m-d')) - ($week-1) * 86400 -1 ,strtotime(date('Y-m-d')) + (8-$week) * 86400)); 
				break;
			case 'month' : 
				$next_year = date('Y')+1;
				$next_month = date('m')+1;
				$month_time = date('m') ==12 ? strtotime($next_year.'-01-01') : strtotime(date('Y').'-'.$next_month.'-01');
				$where['contract.due_time'] = array('between',array(strtotime(date('Y-m-01')) -1 ,$month_time));
				break;
			case 'add' : 
				$order = 'contract.create_time desc'; 
				break;
			case 'deleted' : 
				$where['contract.is_deleted'] = 1; 
				break;
			case 'update' : 
				$order = 'contract.update_time desc'; 
				break;
			case 'me' :
				$where['contract.owner_role_id'] = session('role_id');
				break;
			default: 
				$where['contract.owner_role_id'] = array('in',implode(',', $all_ids)); 
				break;
		}
		
		if (!isset($where['contract.is_deleted'])) {
			$where['contract.is_deleted'] = 0;
		}
		if (!isset($where['contract.owner_role_id'])) {
			$where['contract.owner_role_id'] = array('in',implode(',', getSubRoleId())); 
		}
		
		if ($_REQUEST["field"]) {
			if (trim($_REQUEST['field']) == "all") {
				$field = is_numeric(trim($_REQUEST['search'])) ? 'number|price|contract.description' : 'number|contract.description';
			} else {
				$field = trim($_REQUEST['field']);
			}
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);

			if	('create_time' == $field || 'update_time' == $field || 'due_date' == $field) {
				$search = is_numeric($search)?$search:strtotime($search);
			}
			switch ($condition) {
				case "is" : $where['contract.'.$field] = array('eq',$search);break;
				case "isnot" :  $where['contract.'.$field] = array('neq',$search);break;
				case "contains" :  $where['contract.'.$field] = array('like','%'.$search.'%');break;
				case "not_contain" :  $where['contract.'.$field] = array('notlike','%'.$search.'%');break;
				case "start_with" :  $where['contract.'.$field] = array('like',$search.'%');break;
				case "end_with" :  $where['contract.'.$field] = array('like','%'.$search);break;
				case "is_empty" :  $where['contract.'.$field] = array('eq','');break;
				case "is_not_empty" :  $where['contract.'.$field] = array('neq','');break;
				case "gt" :  $where['contract.'.$field] = array('gt',$search);break;
				case "egt" :  $where['contract.'.$field] = array('egt',$search);break;
				case "lt" :  $where['contract.'.$field] = array('lt',$search);break;
				case "elt" :  $where['contract.'.$field] = array('elt',$search);break;
				case "eq" : $where['contract.'.$field] = array('eq',$search);break;
				case "neq" : $where['contract.'.$field] = array('neq',$search);break;
				case "between" : $where['contract.'.$field] = array('between',array($search-1,$search+86400));break;
				case "nbetween" : $where['contract.'.$field] = array('not between',array($search,$search+86399));break;
				case "tgt" :  $where['contract.'.$field] = array('gt',$search+86400);break;
				default : $where['contract.'.$field] = array('eq',$search);
			}
			$params = array('field='.trim($_REQUEST['field']), 'condition='.$condition, 'search='.$_REQUEST["search"]);
		}
		
		$p = intval($_GET['p'])?intval($_GET['p']):1;
		$list = $contract->where($where)->page($p.',15')->order($order)->select();
		$count = $contract->where($where)->count();
		import("@.ORG.Page");
		$Page = new Page($count,15);
		if (!empty($_GET['by'])) {
			$params[] =   "by=".trim($_GET['by']);
		}
		foreach ($list as $key=>$value) {
			$list[$key]['owner'] = getUserByRoleId($value['owner_role_id']);
			$list[$key]['creator'] = getUserByRoleId($value['creator_role_id']);
			$list[$key]['deletor'] = getUserByRoleId($value['delete_role_id']);
		}
		$Page->parameter = implode('&', $params);
		$this->assign('page', $Page->show());
		$this->assign('list',$list);
		$this->alert = parseAlert();
		$this->display();
	}
	
	public function changeContent(){
		if($this->isAjax()){
			$contract = D('ContractView');
			$where = array();
			
			$where['contract.is_deleted'] = 0;
			$where['contract.owner_role_id'] = array('in',implode(',', getSubRoleId())); 
			
			if ($_REQUEST["field"]) {
				if (trim($_REQUEST['field']) == "all") {
					$field = is_numeric(trim($_REQUEST['search'])) ? 'number|price|contract.description' : 'number|contract.description';
				} else {
					$field = trim($_REQUEST['field']);
				}
				$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
				$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);

				if	('create_time' == $field || 'update_time' == $field || 'due_date' == $field) {
					$search = is_numeric($search)?$search:strtotime($search);
				}
				switch ($condition) {
					case "is" : $where['contract.'.$field] = array('eq',$search);break;
					case "isnot" :  $where['contract.'.$field] = array('neq',$search);break;
					case "contains" :  $where['contract.'.$field] = array('like','%'.$search.'%');break;
					case "not_contain" :  $where['contract.'.$field] = array('notlike','%'.$search.'%');break;
					case "start_with" :  $where['contract.'.$field] = array('like',$search.'%');break;
					case "end_with" :  $where['contract.'.$field] = array('like','%'.$search);break;
					case "is_empty" :  $where['contract.'.$field] = array('eq','');break;
					case "is_not_empty" :  $where['contract.'.$field] = array('neq','');break;
					case "gt" :  $where['contract.'.$field] = array('gt',$search);break;
					case "egt" :  $where['contract.'.$field] = array('egt',$search);break;
					case "lt" :  $where['contract.'.$field] = array('lt',$search);break;
					case "elt" :  $where['contract.'.$field] = array('elt',$search);break;
					case "eq" : $where['contract.'.$field] = array('eq',$search);break;
					case "neq" : $where['contract.'.$field] = array('neq',$search);break;
					case "between" : $where['contract.'.$field] = array('between',array($search-1,$search+86400));break;
					case "nbetween" : $where['contract.'.$field] = array('not between',array($search,$search+86399));break;
					case "tgt" :  $where['contract.'.$field] = array('gt',$search+86400);break;
					default : $where['contract.'.$field] = array('eq',$search);
				}
			}
			
			$p = !$_REQUEST['p']||$_REQUEST['p']<=0 ? 1 : intval($_REQUEST['p']);
			$list = $contract->where($where)->page($p.',10')->order('contract.create_time desc')->select();
			$count = $contract->where($where)->count();
			foreach ($list as $key=>$value) {
				$list[$key]['owner'] = getUserByRoleId($value['owner_role_id']);
				$list[$key]['creator'] = getUserByRoleId($value['creator_role_id']);
				$list[$key]['deletor'] = getUserByRoleId($value['delete_role_id']);
			}
			$data['list'] = $list;
			$data['p'] = $p;
			$data['count'] = $count;
			$data['total'] = $count%10 > 0 ? ceil($count/10) : $count/10;
			$this->ajaxReturn($data,"",1);
		}
	}
	
	public function listDialog(){
		$below_ids = getSubRoleId(true);
		$contract = D('ContractView');
		
		$where['contract.owner_role_id'] = array('in',implode(',', $below_ids));
		$where['contract.is_deleted'] = 0;
		$list = $contract->where($where)->order('create_time desc')->select();
		$count = $contract->where($where)->count();
		$this->total = $count%10 > 0 ? ceil($count/10) : $count/10;
		$this->count_num = $count;
		$this->assign('contractList',$list);
		$this->display();
	}
	public function getcontractlist(){
		$contract = D('ContractView');
		$list = $contract->where(array('contract.is_deleted' => 0))->select();
		$this->ajaxReturn($list, '', 1);
	}
}