<?PHP 
class KnowledgeAction extends Action{
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('index')
		);
		B('Authenticate', $action);
	}
	public function announce(){
		if($this->isPost()){
			$title = trim($_POST['title']);
			if ($title == '' || $title == null) {
				alert('error','标题不能为空!',$_SERVER['HTTP_REFERER']);
			}
			$knowledge = M('knowledge');
			if($knowledge->create()){
				$knowledge->update_time = time();
				if($knowledge->save()){
					if($_POST['submit'] == "保存") {
						alert('success', '公告保存成功！', U('index/index'));
					}
				} else {
					alert('error', '修改失败,数据无变化!',$_SERVER['HTTP_REFERER']);
				}
			}else{
				alert('error', '修改失败，请联系管理员!',$_SERVER['HTTP_REFERER']);
			}
		}elseif($this->isGet()){
			$m_knowledge = M('Knowledge');
			$knowledge =  $m_knowledge->where('knowledge_id = 1')->find();

			$this -> knowledge = $knowledge;
			$this->display();
		}
	}
	public function index(){
		$d_knowledge = D('KnowledgeView'); // 实例化User对象
		import('@.ORG.Page');// 导入分页类
		$where = array();
		$params = array();
		if ($_REQUEST["field"]) {
			$field = trim($_REQUEST['field']) == 'all' ? 'title|content' : $_REQUEST['field'];
			$search = empty($_REQUEST['search']) ? '' : trim($_REQUEST['search']);
			$condition = empty($_REQUEST['condition']) ? 'is' : trim($_REQUEST['condition']);
			if	('create_time' == $field || 'update_time' == $field) $search = is_numeric($search)?$search:strtotime($search);
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
			$params = array('field='.$field, 'condition='.$condition, 'search='.trim($_REQUEST["search"]));
		}
		$p = isset($_GET['p'])?$_GET['p']:1;
		if ($_REQUEST['category_id']) {
			$idArray = Array();
			$categoryList = getSubCategory($_GET['category_id'],M('knowledge_category')->select(),'');
			foreach ($categoryList as $value) {
				$idArray[] = $value['category_id'];
			}
			$idList =empty($idArray) ? $_GET['category_id'] : $_GET['category_id'] . ',' . implode(',', $idArray);
			$where['knowledge.category_id'] = array('in',$idList);
			$count = $d_knowledge->where($where)->count();
			$list = $d_knowledge->order('create_time desc')->where($where)->Page($p.',15')->select();
			$params['category_id'] = 'category_id=' . trim($_REQUEST['category_id']);
			//var_dump($list);die;
		} else {
			$count = $d_knowledge->count();// 查询满足要求的总记录数
			$list = $d_knowledge->where($where)->order('create_time desc')->Page($p.',15')->select();
		} 
		$Page = new Page($count,15);// 实例化分页类 传入总记录数和每页显示的记录数
		$Page->parameter = implode('&', $params);
		$userRole = M('userRole');
		foreach($list as $k => $v){
			$list[$k]['owner'] = D('RoleView')->where('role.role_id = %d', $v['role_id'])->find();
		}
		
		$category = M('knowledge_category');
		$category_list = $category->select();
		$this->categoryList = getSubCategory(0, $category_list, '');
		
		$this->assign('list',$list);// 赋值数据集
		$this->assign('page',$Page->show());// 赋值分页输出
		$this->alert=parseAlert();
		$this->display(); // 输出模板
	}
	public function add(){
		if($_POST['submit']){
			$title = trim($_POST['title']);
			if ($title == '' || $title == null) {
				alert('error','标题不能为空!',$_SERVER['HTTP_REFERER']);
			}
			$knowledge = D('Knowledge');
			if($knowledge->create()){
				$knowledge->create_time = time();
				$knowledge->update_time = time();
				$knowledge->add();
				if($_POST['submit'] == "保存") {
					alert('success', '文章添加成功！', U('Knowledge/index'));
				} else {
					alert('success', '添加成功！', U('Knowledge/add'));
				}
			}else{
				exit($knowledge->getError());
			}

		}else{
			$knowledge_category = M('knowledge_category');
			$category_list = $knowledge_category->select();
			$this->assign('category_list', getSubCategory(0, $category_list, ''));
			$this->alert = parseAlert();
			$this->display();
		}
	}
	public function view(){
		if($_GET['id']){
			$knowledge = M('Knowledge');
			$knowledge->where('knowledge_id=%d',$_GET['id'])->setInc('hits');
			$knowledge = $knowledge->where('knowledge_id = %d ', $_GET['id'])->find();
			$knowledge['owner'] = D('RoleView')->where('role.role_id = %d', $knowledge['role_id'])->find();
			$m_userRole = M('userRole');
			$knowledge['username']  = $m_userRole->where('role_id = %d',$knowledge['role_id'])->getField('name');
			$this->knowledge = $knowledge;
			$this->alert = parseAlert();
			$this->display();
		}else{
			$this->error("参数错误！");
		}
	}
	public function edit(){
		if($this->isPost()){
			$title = trim($_POST['title']);
			if ($title == '' || $title == null) {
				alert('error','标题不能为空!',$_SERVER['HTTP_REFERER']);
			}
			$knowledge = M('knowledge');
			if($knowledge->create()){
				$knowledge->update_time = time();
				if($knowledge->save()){
					if($_POST['submit'] == "保存") {
						alert('success', '文章保存成功！', U('knowledge/index'));
					} else {
						alert('success', '保存成功！请继续输入！', U('knowledge/add'));
					}
				} else {
					alert('error', '修改失败,数据无变化!',U('knowledge/index'));
				}
			}else{
				alert('error', '修改失败，请联系管理员!',U('knowledge/index'));
			}
		}elseif($_GET['id']){
			$m_knowledgeCategory = M('knowledgeCategory');
			$category_list = $m_knowledgeCategory->select();
			$this->assign('category_list', getSubCategory(0, $category_list, ''));
			$m_knowledge = M('Knowledge');
			$this -> knowledge = $m_knowledge->where('knowledge_id = %d',$_GET['id'])->find();
			$this->display();
		}else{
			$this->error("参数错误！");
		}
	}
	public function delete(){
		$knowledge = M('Knowledge');
		$knowledge_idarray = $_POST['knowledge_id'];
		if (is_array($knowledge_idarray)) {
			if (!session('?admin')) {
				foreach ($knowledge_idarray as $v) {
					if (!$knowledge->where('knowledge_id = %d and role_id = %d', $v, session('role_id'))->find()){
						alert('error', '您没有全部的权限，只有作者或管理员可以删除!',U('knowledge/index'));
					}
				}
			}
			if ($knowledge->where('knowledge_id in ("%s")', join(',', $knowledge_idarray))->delete()) {
				alert('success', '删除成功!',U('knowledge/index'));
			} else {
				$this->error('删除失败，联系管理员！');
			}
		} elseif($_GET['id']) {
			if (!session('?admin')) {
				if (!$knowledge->where('knowledge_id = %d and role_id = %d', $_GET['id'], session('role_id'))->find()){
					alert('error', '您没有全部的权限，只有作者或管理员可以删除!',U('knowledge/index'));
				}
			}
			
			if($knowledge->where('knowledge_id = %d', $_GET['id'])->delete()){
				alert('success', '删除成功!',U('knowledge/index'));
			}else{
				$this->error('删除失败，联系管理员！');
			}
		} else {
			alert('error', '请选择要删除的文章!',$_SERVER['HTTP_REFERER']);
		}
	}
	public function category(){
		$knowledge_category = M('knowledge_category');
		$category_list = $knowledge_category->select();
		$category_list = getSubCategory(0, $category_list, '');

		foreach($category_list as $key=>$value){
			$knowledge = M('knowledge');
			$count = $knowledge->where('category_id = %d', $value['category_id'])->count();
			$category_list[$key]['count'] = $count;
			$category_list[$key]['list'] = $knowledge->where('category_id = %d', $value['category_id'])->select();
		}
		$this->alert=parseAlert();
		$this->assign('category_list', $category_list);
		$this->display();
	}
	public function categoryAdd(){
		if (isset($_POST['submit'])) {
			$category = D('KnowledgeCategory');
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
			$category = M('knowledge_category');
			$category_list = $category->select();
			$this->assign('category_list', getSubCategory(0, $category_list, ''));
			$this->display();
		}
	}
	public function categoryEdit(){
		if($_GET['id']){
			$knowledge_category = M('knowledgeCategory');
			$category_list = $knowledge_category -> select();
			$this->assign('category_list', getSubCategory(0, $category_list, ''));
			$this->knowledge_category =$knowledge_category->where('category_id = ' . $_GET['id'])->find();
			$this->display();
		}elseif($_POST['submit']){
			$knowledge_category = M('knowledgeCategory');
			$knowledge_category -> create();
			if($knowledge_category -> save()){
				alert('success','修改类别信息成功！',U('knowledge/category'));
			}else{
				alert('error','没有数据变化，修改类别信息失败',$_SERVER['HTTP_REFERER']);
			}
		}else{
			$this->error('参数错误!');
		}
	}
	public function categoryDelete(){
		$knowledge_category = M('KnowledgeCategory');
		$knowledge = M('knowledge');
		if($_POST['category_list']){
			foreach($_POST['category_list'] as $value){
				if($knowledge->where('category_id = %d',$value)->select()){
					$name = $knowledge_category->where('category_id = %d',$value)->getField('name');
					alert('error', '删除失败，请先删除"'.$name.'"类别下的产品',$_SERVER['HTTP_REFERER']);
				}
				if($knowledge_category->where('parent_id = %d',$value)->select()){
					$name = $knowledge_category->where('category_id = %d',$value)->getField('name');
					alert('error', '删除失败，请先删除"'.$name.'"类别下的子类别',$_SERVER['HTTP_REFERER']);
				}
			}
			if($knowledge_category->where('category_id in (%s)', join($_POST['category_list'],','))->delete()){
				alert('success', '删除类别成功！',$_SERVER['HTTP_REFERER']);
			}else{
				alert('error', '删除类别失败！',$_SERVER['HTTP_REFERER']);
			}
		}elseif($_GET['id']){
			if($knowledge->where('category_id = %d',$_GET['id'])->select()){
				$this->error('删除失败，清先删除该类别下的产品');
				alert('error', '参数错误,添加失败！',$_SERVER['HTTP_REFERER']);	
			}
			if($knowledge->where('parent_id = %d',$_GET['id'])){
				alert('error', '请先删除该类别的子类别',$_SERVER['HTTP_REFERER']);	
			}else{
				$this->error('参数错误！');
			}
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
		$objProps->setTitle("5kcrm Konwledge");    
		$objProps->setSubject("5kcrm Konwledge Data");    
		$objProps->setDescription("5kcrm Konwledge Data");    
		$objProps->setKeywords("5kcrm Konwledge");    
		$objProps->setCategory("5kcrm");
		$objPHPExcel->setActiveSheetIndex(0);     
		$objActSheet = $objPHPExcel->getActiveSheet(); 
		   
		$objActSheet->setTitle('Sheet1');
		$objActSheet->setCellValue('A1', '标题');
		$objActSheet->setCellValue('B1', '类别');
		$objActSheet->setCellValue('C1', '内容');
		$objActSheet->setCellValue('D1', '点击数');
		$objActSheet->setCellValue('E1', '创建人');
		$objActSheet->setCellValue('F1', '创建时间');
		$list = D('KnowledgeView')->select();
		$i = 1;
		foreach ($list as $k => $v) {
			$i++;
			$creator = D('RoleView')->where('role.role_id = %d', $v['role_id'])->find();
			$objActSheet->setCellValue('A'.$i , $v['title']);
			$objActSheet->setCellValue('B'.$i, $v['name']);
			$objActSheet->setCellValue('C'.$i, $v['content']);
			$objActSheet->setCellValue('D'.$i, $v['hits']);
			$objActSheet->setCellValue('E'.$i, $creator['user_name'].'['.$creator['department_name'] . '-' . $creator['role_name'] .']');
			$objActSheet->setCellValue('F'.$i, date("Y-m-d H:i:s", $v['create_time']));
		}
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition:attachment;filename=5kcrm_knowledge_".date('Y-m-d',mktime()).".xls");
        header("Pragma:no-cache");
        header("Expires:0");
        $objWriter->save('php://output'); 
	}
	public function excelImport(){
		$m_knowledge = M('knowledge');
		if($_POST['submit']){
			if (isset($_FILES['excel']['size']) && $_FILES['excel']['size'] != null) {
				import('@.ORG.UploadFile');
				$upload = new UploadFile();
				$upload->maxSize = 20000000;
				$upload->allowExts  = array('xls');
				$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					alert('error', '附件上传目录不可写', U('knowledge/index'));
				}
				$upload->savePath = $dirname;
				if(!$upload->upload()) {
					alert('error', $upload->getErrorMsg(), U('knowledge/index'));
				}else{
					$info =  $upload->getUploadFileInfo();
				}
			}
			if(is_array($info[0]) && !empty($info[0])){
				$savePath = $dirname . $info[0]['savename'];
			}else{
				alert('error', '上传失败', U('knowledge/index'));
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
			if ($allRow <= 1) {
				alert('error', '上传文件无有效数据', U('knowledge/index'));
			} else {
				for($currentRow = 3;$currentRow <= $allRow;$currentRow++){
					$data = array();
					$data['category_id'] = intval($_POST['category_id']);
					$data['role_id'] = session('role_id');
					$data['create_time'] = time();
					$data['update_time'] = time();
					$title = (string)$currentSheet->getCell('A'.$currentRow)->getValue();
					if($title != '' && $title != null) $data['title'] = $title;
					
					$category = (String)$currentSheet->getCell('B'.$currentRow)->getValue();
					$category_id = M('KnowledgeCategory')->where('name = "%s"' ,trim($category))->getField('category_id');
					if($category){
						if($category_id > 0){
							$data['category_id'] = $category_id;
						} else {
							alert('error', '导入至第' . $currentRow . '行出错, 原因："'.$category.'"来源不存在', U('knowledge/index'));
							break;
						}
					}
					
					$content = (string)$currentSheet->getCell('C'.$currentRow)->getValue();
					if($content != '' && $content != null) $data['content'] = $content;
					if (!$m_knowledge->add($data)) {
						alert('error', '导入至第' . $currentRow . '行出错', U('knowledge/index'));
						break;
					}
				}
				alert('success', '导入成功！', U('knowledge/index'));
			}
		}else{
			$this->category_list = getSubCategory(0, M('KnowledgeCategory')->select(), '');
			$this->display();
		}
	}
}
