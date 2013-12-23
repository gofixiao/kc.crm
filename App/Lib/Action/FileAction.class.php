<?php 

class FileAction extends Action{
	public function _initialize(){
		$action = array(
			'permission'=>array(),
			'allow'=>array('editor', 'add', 'delete')
		);
		B('Authenticate', $action);
	}
	
	public function editor(){
		$m_config = M('config');
		if (isset($_FILES['imgFile']['size']) && $_FILES['imgFile']['size'] != null) {
			//如果有文件上传 上传附件
			import('@.ORG.UploadFile');
			//导入上传类
			$upload = new UploadFile();
			//设置上传文件大小
			$upload->maxSize = 20000000;
			//设置上传文件类型
			$defaultinfo = $m_config->where('name = "defaultinfo"')->find();
		    $value = unserialize($defaultinfo['value']);
			$upload->allowExts  = explode(',', $value['allow_file_type']);// 设置附件上传类型
			//设置附件上传目录
			$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
			if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
				$this->error("附件上传目录不可写!");
			}
			$upload->savePath = $dirname;
			
			if(!$upload->upload()) {// 上传错误提示错误信息
				//alert('error', $upload->getErrorMsg(), $_SERVER['HTTP_REFERER']);
				echo $upload->getErrorMsg(); die();
			}else{// 上传成功 获取上传文件信息
				$info =  $upload->getUploadFileInfo();
			}
		}
		if(is_array($info[0]) && !empty($info[0])){
			$a['error']=0;
			$a['url'] = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/' . $info[0]['savename'];
			//$this->ajaxReturn($a,'JSON');
			echo json_encode($a);
		}else{
			$this->error('失败');
		};
	}

	public function add(){
		$m_config = M('config');
		if($_POST['submit']){
			if (array_sum($_FILES['file']['size'])) {
				//如果有文件上传 上传附件
				import('@.ORG.UploadFile');
				//导入上传类
				$upload = new UploadFile();
				//设置上传文件大小
				$upload->maxSize = 20000000;
				//设置附件上传目录
				$dirname = './Uploads/' . date('Ym', time()).'/'.date('d', time()).'/';
				
				$defaultinfo = $m_config->where('name = "defaultinfo"')->find();
				$value = unserialize($defaultinfo['value']);
				$upload->allowExts  = explode(',', $value['allow_file_type']);// 设置附件上传类型
				
				if (!is_dir($dirname) && !mkdir($dirname, 0777, true)) {
					$this->error("附件上传目录不可写!");
				}
				$upload->savePath = $dirname;
				
				if(!$upload->upload()) {// 上传错误提示错误信息
					alert('error', $upload->getErrorMsg(), $_SERVER['HTTP_REFERER']);
				}else{// 上传成功 获取上传文件信息
					$info =  $upload->getUploadFileInfo();
				}
			}

			$m_file = M('File');
			$r_file_module = M($_POST['r']);
			$module = $_POST['module'];
			$m_id = $_POST['id'];
			foreach($info as $key=>$value){
				$data['name'] = $value['name'];
				$data['file_path'] = $value['savepath'].$value['savename'];
				$data['role_id'] = $_POST['role_id'];
				$data['size'] = $value['size'];
				$data['create_date'] = time(); 
				if($file_id = $m_file->add($data)){
					$temp['file_id'] = $file_id;
					$temp[$module . '_id'] = $m_id;
					if(0 >= $r_file_module->add($temp)){
						alert('error', '部分附件添加失败', $_SERVER['HTTP_REFERER']);
					}
				}else{
					alert('error', '添加附件失败', $_SERVER['HTTP_REFERER']);
				};
			}
			alert('success', '添加附件成功！', $_SERVER['HTTP_REFERER']);
		}elseif($_GET['r'] & $_GET['module'] & $_GET['id']){
			$defaultinfo = $m_config->where('name = "defaultinfo"')->find();
			$value = unserialize($defaultinfo['value']);
			$this->allowExts  = $value['allow_file_type'];// 设置附件上传类型
			$this->r = $_GET['r'];
			$this->module = $_GET['module'];
			$this->id = $_GET['id'];
			$this->display();
		}
	} 
		
//	public function delete(){
//		$id = isset($_GET['id']) ? $_GET['id'] : 0;
//		if(0 < $id){
//			$m_file = M('File');
//			$m_file = $m_file->where('file_id = %d', $_GET['id'])->find();
//			if (is_array($m_file) && ($m_file['role_id'] == session('role_id'))) {
//				if($m_file->where('file_id = %d', $_GET['id'])->delete()){
//					alert('success', '操作成功！', $_SERVER['HTTP_REFERER']);
//				}
//			} else {
//				alert('error', '您无权删除此附件！', $_SERVER['HTTP_REFERER']);
//			}			
//		} else {
//			alert('error', '参数错误！', $_SERVER['HTTP_REFERER']);
//		}
//	}
	public function delete(){
		$file_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if (0 == $file_id){
			alert('error','参数错误',$_SERVER['HTTP_REFERER']);
		}else{
			if (isset($_GET['r']) && isset($_GET['id'])) {
				$m_r = M($_GET['r']);
				$m_file = M('file');
				$file = $m_file->where('file_id = %d', $_GET['id'])->find();
				
				if (is_array($file) && ($file['role_id'] == session('role_id'))){
					if ($m_r->where('file_id = %d',$_GET['id'])->delete()) {
						if ($m_file->where('file_id = %d',$_GET['id'])->delete()) {
							alert('success','删除成功！',$_SERVER['HTTP_REFERER']);
						}else{
							alert('success','删除失败！请联系管理员！',$_SERVER['HTTP_REFERER']);
						}
					}else {
						alert('success','删除失败！请联系管理员！',$_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('success','您无权删除此附件！',$_SERVER['HTTP_REFERER']);
				}
			} elseif (empty($_GET['r']) && isset($_GET['id'])){
				$m_file = M('file');
				$file = $m_file->where('file_id = %d', $_GET['id'])->find();
				if (is_array($file) && ($file['role_id'] == session('role_id'))) {
					if($m_file->where('file_id = %d', $_GET['id'])->delete()){
						alert('success', '操作成功！', $_SERVER['HTTP_REFERER']);
					}
				} else {
					alert('error', '您无权删除此附件！', $_SERVER['HTTP_REFERER']);
				}
			}
		}
	}
}