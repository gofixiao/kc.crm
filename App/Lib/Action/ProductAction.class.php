<?php
class ProductAction extends Action {
	
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('listdialog','changecontent','adddialog','editdialog', 'mdelete','allproductdialog','validate','check')
		);
		B('Authenticate', $action);
	}
	public function validate() {
		if($this->isAjax()){
            if(!$this->_request('clientid','trim') || !$this->_request($this->_request('clientid','trim'),'trim')) $this->ajaxReturn("","",3);
            $field = M('Fields')->where('model = "product" and field = "'.$this->_request('clientid','trim').'"')->find();
            $m_product = $field['is_main'] ? D('Product') : D('ProductData');
            $where[$this->_request('clientid','trim')] = array('eq',$this->_request($this->_request('clientid','trim'),'trim'));
            if($this->_request('id','intval',0)){
                $where[$m_product->getpk()] = array('neq',$this->_request('id','intval',0));
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
	public function check(){
		import("@.ORG.SplitWord");
		$sp = new SplitWord();
		$m_product = M('Product');
		$useless_words = array('公司','有限','的','有限公司');
		if ($this->isAjax()) {
			$split_result = $sp->SplitRMM($_POST['name']);
			if(!is_utf8($split_result)) $split_result = iconv("GB2312//IGNORE", "UTF-8", $split_result) ;
			$result_array = explode(' ',trim($split_result));
			foreach($result_array as $k=>$v){
				if(in_array($v,$useless_words)) unset($result_array[$k]);
			}
			$name_list = $m_product->getField('name', true);
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
				$this->ajaxReturn($seach_sort_result,"已创建相近产品！",1);
			}
		}
	}
	public function index(){
		$product = D('ProductView'); // 实例化User对象
		import('@.ORG.Page');// 导入分页类
		$category = M('product_category');
		$where = array();
		$params = array();
		
		$idArray = Array();
		if($_GET['category_id']){
			$categoryList = getSubCategory($_GET['category_id'], $category_list, '');
			foreach($categoryList as $value){
				$idArray[] = $value['category_id'];
			}
		}
		$idList =empty($idArray)?$_GET['category_id']:$_GET['category_id'] . ',' . implode(',', $idArray);
		$p = isset($_GET['p'])?$_GET['p']:1;
		if ($_REQUEST["field"]) {
			if (trim($_REQUEST['field']) == "all") {
				$field = is_numeric(trim($_REQUEST['search'])) ? 'product.name|cost_price|sales_price|link|pre_sale_count|stock_count' : 'product.name|link|development_team';
			} else {
				$field = trim($_REQUEST['field']);
			}
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
			$field_date = M('Fields')->where('(is_main=1 and model="product" and form_type="datetime") or(is_main=1 and model="" and form_type="datetime")')->select();
			foreach($field_date as $v){
				if	($field == $v['field']) $search = is_numeric($search)?$search:strtotime($search);
			}
			if ($this->_request('state') || $this->_request('city')) {
				$search = $this->_request('state') .chr(10) . $this->_request('city') .chr(10) .$search;
			}
			if (!empty($field)) {
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
			$params = array('field='.trim($_REQUEST['field']), 'condition='.$condition, 'search='.$_REQUEST["search"]);
		}
		if ($_GET['category_id']) {
			$where['product.category_id'] = array('in',$idList);
			$count = $product->where($where)->count();
			$list = $product->order('product_id desc')->where($where)->Page($p.',15')->select();
		} else {
			$count = $product->where($where)->count();// 查询满足要求的总记录数
			$list = $product->order('product_id desc')->where($where)->Page($p.',15')->select();
		}
		foreach ($list as $k => $v) {
			$list[$k]["creator"] = D('RoleView')->where('role.role_id = %d', $v['creator_role_id'])->find();
		}
		$Page = new Page($count,15);// 实例化分页类 传入总记录数和每页显示的记录数
		if (!empty($_GET['category_id'])) {
			$params['category_id'] = 'category_id='.trim($_GET['category_id']);
		}
		$Page->parameter = implode('&', $params);
		$show = $Page->show();// 分页显示输出
		//获取下级和自己的岗位列表,搜索用
		$category_list = $category->select();
		$this->categoryList = getSubCategory(0, $category_list, ''); //类别选项
		$this->field_array = getIndexFields('product');
		$this->field_list = getMainFields('product');
		$this->assign('list',$list);// 赋值数据集
		$this->assign('page',$show);// 赋值分页输出
		$this->alert=parseAlert();
		$this->display(); // 输出模板
	}
	
	public function edit(){
		$product = D('ProductView')->where('product.product_id = %d',$this->_request('id'))->find();
		//var_dump($product);die;
		if (!$product) {
			alert('error', '产品不存在!',$_SERVER['HTTP_REFERER']);
		}
		$field_list = M('Fields')->where('model = "product"')->order('order_id')->select();
		if($this->isPost()){
			$m_product = D('Product');
			$m_product_data = D('ProductData');
			$field_list = M('Fields')->where('model = "product"')->order('order_id')->select();
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
			if($m_product->create() && $m_product_data->create()!==false){
				if ($m_product->name == '') {
					alert('error', '请填写产品名！',$_SERVER['HTTP_REFERER']);
				}
				$m_product->update_time = time();
				$a = $m_product->where('product_id= %d',$product['product_id'])->save();
				$b = $m_product_data->where('product_id=' . $product['product_id'])->save();
				actionLog($product['product_id']);
				if($a && $b!==false) {
					alert('success', '产品编辑成功！', U('product/index'));
				} else {
					alert('success', '产品编辑成功！', U('product/add'));
				}
			}else{
				alert('error', $m_product->getError().$m_product_data->getError());
				
				$this->alert = parseAlert();				
				$this->error();
			}
		}else{
			$field_list = field_list_html("edit","product",$product);
		}
		$alert = parseAlert();
		$this->alert = $alert;
		$this->product = $product;
		$this->field_list = $field_list;
		$this->display();
	}
	
	public function add() {
		if($this->isPost()) {
			$m_product = D('Product');
			$m_product_data = D('ProductData');
			if (!isset($_POST['name']) || $_POST['name'] == '') {
				alert('error', '产品名不能为空！', $_SERVER['HTTP_REFERER']);
			} elseif ($m_product->where('name = "%s"', trim($_POST['name']))->find()) {
				alert('error', '该产品名已存在！',$_SERVER['HTTP_REFERER']);
			}
			$field_list = M('Fields')->where('model = "product"')->order('order_id')->select();
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
			if($m_product->create() && $m_product_data->create()!==false){
				$m_product->creator_role_id = session('role_id');
				$m_product->create_time = time();
				if ($product_id = $m_product->add()) {
					$m_product_data->product_id = $product_id;
					actionLog($product_id);
					if($_POST['submit'] == "保存" && $m_product_data->add()) {
						alert('success', '产品添加成功！', U('product/index'));
					} elseif ($_POST['submit'] != "保存" && $m_product_data->add()) {
						alert('success', '产品添加成功！', U('product/add'));
					}
				} else {
					alert('error', $m_product->getError().$m_product_data->getError());
					
					$this->alert = parseAlert();				
					$this->error();
				}
			}else{
				alert('error', $m_product->getError().$m_product_data->getError(), U('product/add'));
			}
		}else{
			$field_list = field_list_html("add","product");

		}
			$this->field_list = $field_list;
			$this->alert = parseAlert();
			$this->display();
	}
	public function view(){
		$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		$field_list = M('Fields')->where('model = "product"')->order('order_id')->select();
		foreach($field_list as $k=>$v){
			if(trim($v['input_tips'])){
				$input_tips = ' &nbsp; <span style="color:red">(注: '.$v['input_tips'].')</span>';
			}else{
				$input_tips = '';
			}
		}
		
		if (0 == $product_id) {
			alert('error', '参数错误！', U('product/index'));
		} else {
			$product = D('ProductView')->where('product.product_id = %d',$product_id)->find();
			
//			$product_category = M('product_category');
//			$categoryList = $product_category->select();
			
			$product['owner'] = D('RoleView')->where('role.role_id = %d', $product['creator_role_id'])->find();
			
			$log_ids = M('rLogProduct')->where('product_id = %d', $product_id)->getField('log_id', true);
			$product['log'] = M('log')->where('log_id in (%s)', implode(',', $log_ids))->select();
			$log_count = 0;
			foreach ($product['log'] as $key=>$value) {
				$product['log'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$log_count++;
			}
			$product['log_count'] = $log_count; 
			
			$file_ids = M('rFileProduct')->where('product_id = %d', $product_id)->getField('file_id', true);
			$product['file'] = M('file')->where('file_id in (%s)', implode(',', $file_ids))->select();
			$file_count = 0;
			foreach ($product['file'] as $key=>$value) {
				$product['file'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['role_id'])->find();
				$file_count++;
			}
			$product['file_count'] = $file_count;
			
			$task_ids = M('rProductTask')->where('product_id = %d', $product_id)->getField('task_id', true);
			$product['task'] = M('task')->where('task_id in (%s) and is_deleted=0', implode(',', $task_ids))->select();
			$task_count = 0;
			foreach ($product['task'] as $key=>$value) {
				$product['task'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
				$task_count ++;
			}
			$product['task_count'] = $task_count;
			
			$event_ids = M('rEventProduct')->where('product_id = %d', $product_id)->getField('event_id', true);
			$product['event'] = M('event')->where('event_id in (%s)', implode(',', $event_ids))->select();
			$event_count = 0;
			foreach ($product['event'] as $key=>$value) {
				$product['event'][$key]['owner'] = D('RoleView')->where('role.role_id = %d', $value['owner_role_id'])->find();
				$event_count ++;
			}
			$product['event_count'] = $event_count;
			
			$this->product = $product;
//			$this->categoryList = $categoryList;
			$this->field_list = $field_list;			
			$this->alert = parseAlert();
			$this->display();
		}	
	}
	public function delete(){
		$m_product = M('product');
		$m_product_data = M('product_data');
		$r_module = array('Log'=>'RLogProduct', 'File'=>'RFileProduct','rproductProduct','rContractProduct');
		if($this->isPost()){
			$product_ids = is_array($_POST['product_id']) ? implode(',', $_POST['product_id']) : '';
			if ('' == $product_ids) {
				alert('error', '您没有选择任何内容！', U('product/index'));
			} else {
				if (!session('?admin')) {
					foreach($_POST['product_id'] as $key => $value){
						if(!$m_product->where('creator_role_id = %d and product_id = %d', session('role_id'), $value)->find()){
						echo $m_product->getLastSql(); die();
							alert('error', '您没有全部权限进行操作！', $_SERVER['HTTP_REFERER']);
						}
					}
				}
				$product_delete = $m_product->where('product_id in (%s)', $product_ids)->delete();
				$product_data_delete = $m_product_data->where('product_id in (%s)', $product_ids)->delete();
				if($product_delete && $product_data_delete){
					foreach ($_POST['product_id'] as $value) {
						actionLog($value);
						foreach ($r_module as $key2=>$value2) {
							$module_ids = M($value2)->where('product_id = %d', $value)->getField($key2 . '_id', true);
							M($value2)->where('product_id = %d', $value) -> delete();
							if(!is_int($key2)){	
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
					}
					alert('success', '删除成功!',U('product/index'));
				} else {
					alert('error', '删除失败，联系管理员！', U('product/index'));
				}
				
			}
		} elseif($_GET['id']) {
			$product_id = intval($_GET['id']);
			$product = $m_product->where('product_id = %d', $product_id)->find();
			if (is_array($product)) {
				if(session('?admin') || $product['creator_role_id'] == session('role_id')){
					if($m_product->where('product_id = %d', $product_id)->delete()){
						foreach ($r_module as $key2=>$value2) {
							if(!is_int($key2)){
								$module_ids = M($value2)->where('product_id = %d', $product_id)->getField($key2 . '_id', true);
								M($value2)->where('product_id = %d', $product_id) -> delete();
								M($key2)->where($key2 . '_id in (%s)', implode(',', $module_ids))->delete();
							}
						}
						alert('success', '删除成功!', U('product/index'));
					}else{
						alert('error', '删除失败，请联系管理员！', U('product/index'));
					}
				} else {
					alert('error', '您没有权限！', $_SERVER['HTTP_REFERER']);
				}
					
			} else {
				alert('error', '记录不存在！', U('leads/index'));
			}			
		} else {
			alert('error', '请选择要删除的产品!',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function mDelete(){
		if($_GET['id']){
			$m_r = M($_GET['r']);
			if($m_r->where("id = %d", trim($_GET['id']))->delete()){
				alert('success','删除成功',$_SERVER['HTTP_REFERER']);
			} else {
				alert('error','删除失败',$_SERVER['HTTP_REFERER']);
			}
		} else {
			alert('error','参数错误！',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function editDialog(){
		if($this->isPost()){
			$r = trim($_POST['r']);
			$d_r = D($r);
			$d_r->create();
			if($d_r->save()){
				alert('success', '修改成功',$_SERVER['HTTP_REFERER']);
			}else{
				alert('error', '修改失败！',$_SERVER['HTTP_REFERER']);
			}
		}elseif ($_GET['r'] && $_GET['id']) {
			$rbs = M($_GET['r'])->where('id = %d', $_GET['id'])->find();
			$rbs['info'] = M('product')->where('product_id = %d', $rbs['product_id'])->find();
			$this->r = $_GET['r'];
			$this->rbs = $rbs;
			$this->display();
		}else{
			alert('error', '参数错误！',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function listDialog(){
		if($this->isPost()){
			$r = $_POST['r'];
			$model_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
			$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
			$m_r = M($r);
			$m_id = $_POST['module'] . '_id';  //对应模块的id字段
			
			$data[$m_id] = $model_id;
			foreach ($_POST['product_id'] as $value) {
				$data['product_id'] = $value;
				if ($m_r -> add($data) <= 0) {
					alert('error', '选择产品失败！',$_SERVER['HTTP_REFERER']);
				}
			}
			alert('success', '选择产品成功！',$_SERVER['HTTP_REFERER']);
		}elseif ($_GET['r'] && $_GET['module'] && $_GET['id']) {
			$id_array = M($_GET['r']) -> where($_GET['module'] . '_id = %d', $_GET['id']) -> getField('product_id', true);
			$id_array[] = 0;
			$this -> r = $_GET['r'];
			$this -> module = $_GET['module'];
			$this -> model_id = $_GET['id'];
			$d_product = D('ProductView');
			$a = $d_product->where('product_id not in (%s)', implode(',',$id_array))->select();
			$this->list = $a;
			$this->display();
		}else{
			alert('error', '参数错误！',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function addDialog(){
		if($this->isPost()){
			$r = $_POST['r'];
			$model_id = isset($_POST['model_id']) ? intval($_POST['model_id']) : 0;
			$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
			$m_r = D($r);
			$m_id = $_POST['module'] . '_id';  //对应模块的id字段
			$m_r->create();
			$m_r->$m_id = $model_id;
			if ($m_r -> add()) {
				alert('success', '添加成功！',$_SERVER['HTTP_REFERER']);
			} else {
				alert('error', '添加失败！',$_SERVER['HTTP_REFERER']);
			}
			
		}elseif ($_GET['r'] && $_GET['module'] && $_GET['id']) {
			$id_array = M($_GET['r']) -> where($_GET['module'] . '_id = %d', $_GET['id']) -> getField('product_id', true);
			$id_array[] = 0;
			$this -> r = $_GET['r'];
			$this -> module = $_GET['module'];
			$this -> model_id = $_GET['id'];
			$d_product = D('ProductView');
			$a = $d_product->where('product_id not in (%s)', implode(',',$id_array))->select();
			$this->list = $a;
			$this->display();
		}else{
			alert('error', '参数错误！',$_SERVER['HTTP_REFERER']);
		}
	}
	
	public function allProductDialog(){
		$d_product = D('ProductView');
		$this->list = $d_product->select();
		$count = $d_product->count();
		$this->total = $count%10 > 0 ? ceil($count/10) : $count/10;
		$this->count_num = $count;
		$this->display();
	}
	
	public function changeContent(){
		if($this->isAjax()){
			$product = D('ProductView'); // 实例化User对象
			import('@.ORG.Page');// 导入分页类
			$category = M('product_category');
			$where = array();
			$params = array();

			$p = !$_REQUEST['p']||$_REQUEST['p']<=0 ? 1 : intval($_REQUEST['p']);
			if ($_REQUEST["field"]) {
				$field = trim($_REQUEST['field']);
				
				$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
				$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
				if	('development_time' == $field || 'listing_time' == $field) $search = is_numeric($search)?$search:strtotime($search);;
				if (!empty($field)) {
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
				$params = array('field='.trim($_REQUEST['field']), 'condition='.$condition, 'search='.$_REQUEST["search"]);
			}
			

			$count = $product->where($where)->count();// 查询满足要求的总记录数
			$list = $product->order('product_id')->where($where)->Page($p.',10')->select();

			$data['list'] = $list;
			$data['p'] = $p;
			$data['count'] = $count;
			$data['total'] = $count%10 > 0 ? ceil($count/10) : $count/10;
			$this->ajaxReturn($data,"",1);
		}
	}
	
	public function category(){
		$product_category = M('product_category');			
		$category_list = $product_category->select();	
		$category_list = getSubCategory(0, $category_list, '');
		
		foreach($category_list as $key=>$value){
			$product = M('product');
			$count = $product->where('category_id = %d', $value['category_id'])->count();
			$category_list[$key]['count'] = $count;
			$category_list[$key]['list'] = $product->where('category_id = %d', $value['category_id'])->select();
		}
		$this->alert=parseAlert();
		$this->assign('category_list', $category_list);
		$this->display();
	}
	
	public function category_add(){
		if (isset($_POST['name']) && $_POST['name'] != '') {
			$category = D('ProductCategory');
			if ($t = $category->create()) {
				if ($category->add()) {					
					alert('success', '添加成功！',$_SERVER['HTTP_REFERER']);			
				} else {
					alert('error', '参数错误,添加失败！',$_SERVER['HTTP_REFERER']);			
				}				
			} else {	
				exit($category->getError());				
			}
		}else{
			$category = M('product_category');			
			$category_list = $category->select();			
			$this->assign('category_list', getSubCategory(0, $category_list, ''));
			$this->display();
		}
	}
	
	public function category_delete(){
		$product_category = M('Product_category');
		$product = M('product');
		if($_POST['category_list']){
			foreach($_POST['category_list'] as $value){
				if($product->where('category_id = %d',$value)->select()){
					$name = $product_category->where('category_id = %d',$value)->getField('name');
					alert('error', '删除失败，请先删除"'.$name.'"类别下的产品',$_SERVER['HTTP_REFERER']);
				}
				if($product_category->where('parent_id = %d',$value)->select()){
					$name = $product_category->where('category_id = %d',$value)->getField('name');
					alert('error', '删除失败，请先删除"'.$name.'"类别下的子类别',$_SERVER['HTTP_REFERER']);
				}
			}
			if($product_category->where('category_id in (%s)', join($_POST['category_list'],','))->delete()){
				alert('success', '删除类别成功！',$_SERVER['HTTP_REFERER']);
			}else{
				alert('error', '删除类别失败！',$_SERVER['HTTP_REFERER']);
			}
		}elseif($_GET['id']){
			if($product->where('category_id = %d',$_GET['id'])->select()){
				$this->error('删除失败，清先删除该类别下的产品');
				alert('error', '参数错误,添加失败！',$_SERVER['HTTP_REFERER']);	
			}
			if($product->where('parent_id = %d',$_GET['id'])){
				alert('error', '请先删除该类别的子类别',$_SERVER['HTTP_REFERER']);	
			}else{
				$this->error('参数错误！');
			}
		}else{
			$this->error('删除失败！');
		}	
	}
	
	//编辑产品分类信息
	public function category_edit(){
		if($_GET['id']){
			$product_category = M('product_category');			
			$category_list = $product_category->select();	
			$this->assign('category_list', getSubCategory(0, $category_list, ''));
			$product_category = M('product_category');
			$categoryList = $product_category->select();	//读取分类列表 加载下拉框
			foreach($categoryList as $key=>$value){
				if($value['category_id'] == $_GET['id']){
					unset($categoryList[$key]);
				}
			}
			
			$this->category_list = $categoryList;
			$this->temp =$product_category->where('category_id = ' . $_GET['id'])->find();
			
			$this->display();
		}elseif($_POST['category_id']){
			$product_category = M('product_category');	
			$product_category -> create();
			if($product_category->save()){
				alert('success','修改类别信息成功！',$_SERVER['HTTP_REFERER']);
			}else{
				alert('error','没有数据变化，修改类别信息失败',$_SERVER['HTTP_REFERER']);
			}
		}else{
			$this->error('参数错误!');
		}
	}
	
	//产品销量统计
	public function count(){
		//商机	产品	销量	成本	交易价	盈利
		$sales = D('SalesView');
		$sales_list = $sales->order('create_time')->select();
		foreach($sales_list as $key=>$value){
			$count = $value['product_amount'];
			$sales_price = $value['sales_price'];
			$cost_price = $value['cost_price'];
			$profit = $count*($sales_price-$cost_price);
			$sales_list[$key]['profit'] = $profit;
		}
		
		$this->salesList = $sales_list;
		$this->display();
	}
	
	public function excelExport(){
		import("ORG.PHPExcel.PHPExcel");
		$objPHPExcel = new PHPExcel();    
		$objProps = $objPHPExcel->getProperties();    
		$objProps->setCreator("5kcrm");    
		$objProps->setLastModifiedBy("5kcrm");    
		$objProps->setTitle("5kcrm Product");    
		$objProps->setSubject("5kcrm Product Data");    
		$objProps->setDescription("5kcrm Product Data");    
		$objProps->setKeywords("5kcrm Product");    
		$objProps->setCategory("5kcrm");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
        $ascii = 65;
        $cv = '';
        $field_list = M('Fields')->where('model = \'product\'')->order('order_id')->select();
        foreach($field_list as $field){
            $objActSheet->setCellValue($cv.chr($ascii).'1', $field['name']);
            $ascii++;
            if($ascii == 91){
                $ascii = 65;
                $cv .= chr(strlen($cv)+65);
            }
        }
		
		$list = D('ProductView')->select();
		$i = 1;
		foreach ($list as $k => $v) {
            $data = m('ProductData')->where("product_id = $v[product_id]")->find();
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
                }elseif($field['field'] == 'category_id'){
					$m_category = M('ProductCategory');
					$category = $m_category->where('category_id = %d',$v['category_id'])->find();
					$objActSheet->setCellValue($cv.chr($ascii).$i, $category['name']);
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
        header("Content-Disposition:attachment;filename=5kcrm_product_".date('Y-m-d',mktime()).".xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
	}

	public function excelImport(){
		$m_product = D('product');
		$m_product_data = D('ProductData');
		if($_POST['submit']){
			if (isset($_FILES['excel']['size']) && $_FILES['excel']['size'] != null) {
				import('@.ORG.UploadFile');
				$upload = new UploadFile();
				$upload->maxSize = 20000000;
				$upload->allowExts  = array('xls');
				$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					alert('error', '附件上传目录不可写', U('product/index'));
				}
				$upload->savePath = $dirname;
				if(!$upload->upload()) {
					alert('error', $upload->getErrorMsg(), U('product/index'));
				}else{
					$info =  $upload->getUploadFileInfo();
				}
			}
			if(is_array($info[0]) && !empty($info[0])){
				$savePath = $dirname . $info[0]['savename'];
			}else{
				alert('error', '上传失败', U('product/index'));
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
			
			if ($allRow <= 2) {
				alert('error', '上传文件无有效数据', U('product/index'));
			} else {
				$field_list = M('Fields')->where('model = \'product\'')->order('order_id')->select();
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
							if($field['field'] == 'category_id'){
								$m_product_category = M('ProductCategory');
								$product_category = $m_product_category->where('name like "%s"',$info)->find();
								$info = $product_category['category_id'];
							}
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
                    if ($m_product->create($data) && $m_product_data->create($data_date)) {
                        $product_id = $m_product->add();
                        $m_product_data->product_id = $product_id;
                        $m_product_data->add();
					}else{
                        alert('error', '导入至第' . $currentRow . '行出错'.$m_product->getError().$m_product_data->getError(), U('product/index'));
                    }
				}
				alert('success', '导入成功！', U('product/index'));
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
		$objProps->setTitle("5kcrm Product");    
		$objProps->setSubject("5kcrm Product Data");    
		$objProps->setDescription("5kcrm Product Data");    
		$objProps->setKeywords("5kcrm Product Data");    
		$objProps->setCategory("5kcrm");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
        $ascii = 65;
        $cv = '';
        $field_list = M('Fields')->where('model = \'product\' ')->order('order_id')->select();
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
        header("Content-Disposition:attachment;filename=5kcrm_product.xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
    }
}