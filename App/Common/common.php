<?php
function deldir($dir) {
        $dh = opendir($dir);
         while ($file = readdir($dh)) {
             if ($file != "." && $file != "..") {
                 $fullpath = $dir . "/" . $file;
                if (!is_dir($fullpath)) {
                     unlink($fullpath);
                } else {
					 deldir($fullpath);
				 }
             }
         }
    }

function format_price($num){
	$num = round($num, 0);
	$s_num = strval($num);
	$len = strlen($s_num)-1;
	$result = round($num, -$len);
	return $result;
}

//方法说明	获取首页需要显示的列名字符串
function getIndexFields($model){
    if(!$model) return false;
	$m_model = M($model);
	$where['in_index'] = 1;
	$where['model'] = $model;
	$model_fields = M('Fields')->where($where)->order('order_id ASC')->select();
	return $model_fields;
}
//获取主表字段 用于搜索
function getMainFields($model){
	if(!$model) return false;
	$m_model = M($model);
	$where['is_main'] = 1;
	$where['model'] = $model;
	$model_fields = M('Fields')->where($where)->order('order_id ASC')->select();
	return $model_fields;
}
/*记录操作日志
*$id 操作对象id
*$text 附加信息
*2013-10-23
*/
function actionLog($id,$text=''){
    $modules = array(
        'customer'  =>'客户',
        'business'  =>'商机',
        'leads'     =>'线索',
        'product'   =>'产品',
        'contacts'  =>'联系人',
        'task'      =>'任务',
        'event'     =>'活动',
        'user'      =>'员工',
        'finance'   =>'财务',
        'log'       =>'日志',
        'role'      =>'岗位',
        'file'      =>'文件',
        'knowledge' =>'知识'
    );
    $actions = array(
        'add'    =>'添加',
        'edit'   =>'修改',
     	'completedelete' =>'彻底删除',
        'delete' =>'删除',
        'test'   =>'测试'
    );
    $role_id = session('role_id');
    $user = M('user')->where(array('user_id'=>session('user_id')))->find();
    $category = $user['category_id'] == 1 ? '管理员' : '员工';
    $data['role_id'] = $role_id;
    $data['module_name'] = strtolower(MODULE_NAME);
    $data['action_name'] = strtolower(ACTION_NAME);
    $data['create_time'] = time();
    $data['action_id'] = $id;
    $data['content'] = sprintf('%s%s在%s%s了id为%d的%s。%s',$category,$user['name'],date('Y-m-d H:i:s'),$actions[$data['action_name']],$id,$modules[$data['module_name']],$text);
    $actionLog = M('actionLog');
    $actionLog->create($data);
    if($actionLog->add()) return true;
    return false;
}
function msubstr($str, $start=0, $length, $charset="utf-8", $suffix=true) {
    if(function_exists("mb_substr"))
        $slice = mb_substr($str, $start, $length, $charset);
    elseif(function_exists('iconv_substr')) {
        $slice = iconv_substr($str,$start,$length,$charset);
        if(false === $slice) {
            $slice = '';
        }
    }else{
        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice.'...' : $slice;
}

function getSubCategory($category_id, $category, $separate) {
	$array = array();
	foreach($category AS $value) {
		if ($category_id == $value['parent_id']) {
			$array[] = array('category_id' => $value['category_id'], 'name' => $separate.$value['name'],'description'=>$value['description']);
			$array = array_merge($array, getSubCategory($value['category_id'], $category, $separate.'--'));
		}
	}
	return $array;
}

// 不包括自己所在部门
function getSubDepartment($department_id, $department, $separate, $no_separater) {
	$array = array();
	if($no_separater){
		foreach($department AS $value) {
			if ($department_id == $value['parent_id']) {
				$array[] = array('department_id' => $value['department_id'], 'name' => $separate.$value['name'],'description'=>$value['description']);
				$array = array_merge($array, getSubDepartment($value['department_id'], $department, $separate, 1));
			}
		}
	}else{
		foreach($department AS $value) {
			if ($department_id == $value['parent_id']) {
				$array[] = array('department_id' => $value['department_id'], 'name' => $separate.$value['name'],'description'=>$value['description']);
				$array = array_merge($array, getSubDepartment($value['department_id'], $department, $separate.'--'));
			}
		}
	}
	return $array;
}

//包括自己所在部门
function getSubDepartment2($department_id, $department, $first=0) {
	$array = array();
	$m_department =  M('role_department');
	if($first == 1){
		$depart = $m_department->where('department_id = %d', session('department_id'))->find();
		$array[] = array('department_id'=>$depart['department_id'],'name'=>$depart['name'], 'description'=>$depart['description']);
	}
	foreach($department AS $value) {
		if ($department_id == $value['parent_id']) {
			$array[] = array('department_id' => $value['department_id'], 'name' => $separate.$value['name'],'description'=>$value['description']);
			$array = array_merge($array, getSubDepartment2($value['department_id'], $department, '--'));
		}
	}
	return $array;
}

function getSubDepartmentTreeCode($department_id, $first=0) {
	$string = "";
	$department_list = M('roleDepartment')->where('parent_id = %d', $department_id)->select();
	$position_list = M('position')->where('department_id = %d', $department_id)->select();

	if ($department_list || $position_list) {
		if ($first) {
			$string = '<ul id="browser" class="filetree">';
		} else {
			$string = "<ul>";
		}
		

		foreach($position_list AS $value) {
			$string .= "<li><span rel='".$value['position_id']."' class='file'>".$value['name']." &nbsp; <span class='control' id='control_file".$value['position_id']."'><a class='position_edit' rel=".$value['position_id']." href='javascript:void(0)'>编辑</a> &nbsp; <a class='position_delete' rel=".$value['position_id']." href='javascript:void(0)'>删除</a> </span> </span></li>";
		}
		foreach($department_list AS $value) {
			if($first){
				$string .= "<li><span rel='".$value['department_id']."' class='folder'>".$value['name']." &nbsp; <span class='control' id='control_folder".$value['department_id']."'><a class='department_edit' rel=".$value['department_id']." href='javascript:void(0)'>编辑</a> &nbsp; <a class='department_delete' rel=".$value['department_id']." href='javascript:void(0)'>删除</a> </span></span>".getSubDepartmentTreeCode($value['department_id'])."</li>";
			} else {
				$string .= "<li class='closed'><span rel='".$value['department_id']."' class='folder'>".$value['name']." &nbsp; <span class='control' id='control_folder".$value['department_id']."'><a class='department_edit' rel=".$value['department_id']." href='javascript:void(0)'>编辑</a> &nbsp; <a class='department_delete' rel=".$value['department_id']." href='javascript:void(0)'>删除</a> </span></span>".getSubDepartmentTreeCode($value['department_id'])."</li>";
			}
			
		}
		$string .= "</ul>";
	} 

	return $string;
}
function getSubPositionTreeCode($position_id, $first=0) {
	$string = "";
	$position_list = M('position')->where('parent_id = %d', $position_id)->select();

	if ($position_list) {
		if ($first) {
			$string = '<ul id="browser" class="filetree">';
		} else {
			$string = "<ul>";
		}
		foreach($position_list AS $value) {
			$department_name = M('RoleDepartment')->where('department_id = %d', $value['department_id'])->getField('name');
			if($first){
				$string .= "<li><span rel='".$value['position_id']."' class='file'>".$value['name']." - $department_name"." &nbsp; <span class='control' id='control_file".$value['position_id']."'><a class='position_edit' rel=".$value['position_id']." href='javascript:void(0)'>编辑</a> &nbsp; <a class='permission' rel=".$value['position_id']." href='javascript:void(0)'>授权</a> &nbsp; <a class='position_delete' rel=".$value['position_id']." href='javascript:void(0)'>删除</a> </span></span>".getSubPositionTreeCode($value['position_id'])."</li>";
			} else {
				$string .= "<li class='closed'><span rel='".$value['position_id']."' class='file'>".$value['name']."-$department_name"." &nbsp; <span class='control' id='control_file".$value['position_id']."'><a class='position_edit' rel=".$value['position_id']." href='javascript:void(0)'>编辑</a> &nbsp; <a class='permission' rel=".$value['position_id']." href='javascript:void(0)'>授权</a> &nbsp; <a class='position_delete' rel=".$value['position_id']." href='javascript:void(0)'>删除</a> </span></span>".getSubPositionTreeCode($value['position_id'])."</li>";
			}
			
		}
		$string .= "</ul>";
	} 

	return $string;
}

function getSubRoleId($self = true){
	$all_role = M('role')->where('user_id <> 0')->select();
	$below_role = getSubRole(session('role_id'), $all_role);
	$below_ids = array();
	if ($self) {
		$below_ids[] = session('role_id');
	}
	foreach ($below_role as $key=>$value) {
		$below_ids[] = $value['role_id'];
	}
	return $below_ids;
}

//原获取职位列表方法
function getSubRole($role_id, $role_list, $separate) {
	$d_role = D('RoleView');
	$array = array();
	
	if($d_role->where('role.role_id = %d', $role_id)->find()){
		$position_id = $d_role->where('role.role_id = %d', $role_id)->getField('position_id');
	}else{
		$position_id  = 0;
	}
	$role_list2 = $d_role->where('position.parent_id = %d', $position_id)->select();
	foreach($role_list2 AS $value) {
		$array[] = array('role_id' => $value['role_id'],'user_id' => $value['user_id'], 'parent_id' => $value['parent_id'], 'name' => $separate . $value['department_name'] . ' | ' . $value['role_name']);
		$son_role = getSubRole($value['role_id'], $role_list, $separate.'--');
		if(!empty($son_role)){
			$array = array_merge($array, $son_role);
		}
	}

	return $array;
}

function getSubPosition($position_id, $position, $separate) {
	$array = array();
	foreach($position AS $key=> $value) {
		if ($position_id == $value['parent_id']) {
			$m_department = M('RoleDepartment');
			$department_name = $m_department->where('department_id = %d', $value['department_id'])->getField('name');
			$array[] = array('position_id' => $value['position_id'], 'name' => $separate . $department_name . ' | ' . $value['name'],'description'=>$value['description']);
			$array = array_merge($array, getSubPosition($value['position_id'], $position, $separate.' -- '));
		}
	}
	return $array;
}

function getSubDepartmentByRole($role_id = 0){
	if($role_id <= 0) $role_id = session('role_id');
	$department_id = M('Role')->where('role_id = %d', $role_id)->getField('department_id');
	//未完成方法
}
//通过部门id获取该部门员工
function getRoleByDepartmentId($department_id){
	$id_array = array($department_id);
	$departments = M('roleDepartment')->select();
	$roleList = D('RoleView')->where('position.department_id = %d and role.role_id in (%s)', $department_id, implode(',', getSubRoleId()))->select();
	foreach($departments AS $value) {
		if ($department_id == $value['parent_id']) {
			$id_array[] = $value['department_id'];
			$role_list = getRoleByDepartmentId($value['department_id']);
			if(!empty($role_list)){
				$roleList = array_merge($roleList, $role_list);
			}
		}
	}
	return $roleList;
}
/**
 * Warning提示信息
 * @param string $type 提示类型 默认支持success, error, info
 * @param string $msg 提示信息
 * @param string $url 跳转的URL地址
 * @return void
 */
function alert($type='info', $msg='', $url='') {
    //多行URL地址支持
    $url        = str_replace(array("\n", "\r"), '', $url);
	$alert = unserialize(stripslashes(cookie('alert')));
    if (!empty($msg)) {
        $alert[$type][] = $msg;
		cookie('alert', serialize($alert));
	}
    if (!empty($url)) {
		if (!headers_sent()) {
			// redirect
			header('Location: ' . $url);
			exit();
		} else {
			$str    = "<meta http-equiv='Refresh' content='0;URL={$url}'>";
			exit($str);
		}
	}

	return $alert;
}

function parseAlert() {
	$alert = unserialize(stripslashes(cookie('alert')));
	cookie('alert', null);

	return $alert;
}

function getUserByRoleId($role_id){
	$role = D('RoleView')->where('role.role_id = %d', $role_id)->find();
	return $role;
}

function sendRequest($url, $params = array() , $headers = array()) {
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
	if (!empty($params)) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	}
	if (!empty($headers)) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$txt = curl_exec($ch);
	if (curl_errno($ch)) {
		$return = array(0, '连接服务器出错', -1);
	} else {
		$return = json_decode($txt, true);
		if (!$return) {
			$return = array(0, '服务器返回数据异常', -1);
		}
	}

	return $return;
}

//生成评论提醒标题
function createCommentAlertInfo($module,$module_id){
	$author = D('RoleView')->where('role.role_id = %d', session('role_id'))->find();
	if($module == 'log'){
		$log = M('log')->where('log_id = %d', $module_id)->find();
		$title = '您的标题为 《'.$log['subject'].'》的日志，受到了'.$author['user_name'].'['.$author['department_name'].'-'.$author['role_name'].']的评价!';
	}elseif($module == 'task'){
		$task = M('task')->where('task_id = %d', $module_id)->find();
		$title = '您的主题为 《'.$task['subject'].'》的任务，受到了'.$author['user_name'].'['.$author['department_name'].'-'.$author['role_name'].']的评价!';
	}
	return $title;
}

//$sysMessage=0 为系统消息
function sendMessage($id,$content,$sysMessage=0,$weixin = 0){
	if(!$id) return false;
	if(!$content) return false;
	$m_message = M('message');
	if($sysMessage == 0) $data['from_role_id'] = session('role_id');
	$data['to_role_id'] = $id;
	$data['content'] = $content;
	$data['read_time'] = 0;
	$data['send_time'] = time();
	return $m_message->add($data);
}

/*
	功能:发送邮件
	参数说明：  $to_role_id 收件人role_id
				$title 邮件主题
				$content 邮件内容
				$author 作者
*/
function sysSendEmail($to_role_id,$title,$content,$author){
	C(F('smtp'),'smtp');
	if(!$content) return false;
	if(!$to_role_id) return false;
	if(!$author) $author=C('defaultinfo.name')."系统管理员";
	import('@.ORG.Mail');
	$to_user = D('RoleView')->where('role.role_id = %d', $to_role_id)->find();
	if(!is_email($to_user['email'])) return false;
	return SendMail($to_user['email'],$title,$content,$author);
}
function userSendEmail($address,$title,$content,$author=false){
	C(F('smtp'),'smtp');
	if(!$address) return false;
	if(!$content) return false;
	$content = preg_replace('/\\\\/','', $content);
	$userid = session('user_id');
    $user = M('user')->where(array('user_id'=>$userid))->find();
	if($author===true) $author=C('defaultinfo.name').'-'.$user['name'];
	else $author=C('defaultinfo.name')."-匿名";
	import('@.ORG.Mail');
	if(!is_email($address)) return false;
	return SendMail($address,$title,$content,$author);
}
function bsendemail($address,$title,$content,$file=array(),$author=false){
	if(!$address) return false;
	if(!$content) return false;
	$content = eregi_replace("[\]",'',$content);
	$userid = session('user_id');
	$user = M('user')->where(array('user_id'=>$userid))->find();
	if($author===true) $author=C('defaultinfo.name').'-'.$user['name'];
	else $author=C('defaultinfo.name')."-wukong";
	C(F('smtp'),'smtp');
	import('@.ORG.Mail');
	$mail= new PHPMailer(true);
	try {
		$mail->IsSMTP();
		$mail->CharSet=C('MAIL_CHARSET');
		$mail->AddAddress($address);
		$mail->Body=$content;
		$mail->From= C('MAIL_ADDRESS');
		$mail->FromName=$author;
		$mail->Subject=$title;
		$mail->Host=C('MAIL_SMTP');
		$mail->SMTPAuth=C('MAIL_AUTH');
		$mail->Username=C('MAIL_LOGINNAME');
		$mail->Password=C('MAIL_PASSWORD');  
		$mail->IsHTML(C('MAIL_HTML'));
		$mail->MsgHTML($content);
		 ////对邮件正文进行重新编码，保证中文内容不乱码 如果正文引用该图片 就不会以附件形式存在 而是在正文中
		if(!empty($file)){
			foreach($file as $k=>$v){
				$mail->AddAttachment(ltrim($v,'/'));
			}
		}

		//$mail->AddAttachment($content); //上传附件内容
		return($mail->Send());
	} catch (phpmailerException $e) {
	 // echo $e->errorMessage(); //Pretty error messages from PHPMailer
	} catch (Exception $e) {
	  //echo $e->getMessage(); //Boring error messages from anything else!
	}
}

function sysSendSms($to_role_id,$content){

	if(!$content) return false;
	if(!$to_role_id) return false;
	if(!$title) $title="系统通知";
	if(!$author) $author=C('defaultinfo.name')."系统管理员";

	$to_user = D('RoleView')->where('role.role_id = %d', $to_role_id)->find();
	if(!is_email($to_user['email'])) return 100;
	return sendSMS($to_user['telephone'],$content,'sign_sysname');
}

function isMobile(){

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $mobile_agents = Array("240x320","acer","acoon","acs-","abacho","ahong","airness","alcatel","amoi","android","anywhereyougo.com","applewebkit/525","applewebkit/532","asus","audio","au-mic","avantogo","becker","benq","bilbo","bird","blackberry","blazer","bleu","cdm-","compal","coolpad","danger","dbtel","dopod","elaine","eric","etouch","fly ","fly_","fly-","go.web","goodaccess","gradiente","grundig","haier","hedy","hitachi","htc","huawei","hutchison","inno","ipad","ipaq","ipod","jbrowser","kddi","kgt","kwc","lenovo","lg ","lg2","lg3","lg4","lg5","lg7","lg8","lg9","lg-","lge-","lge9","longcos","maemo","mercator","meridian","micromax","midp","mini","mitsu","mmm","mmp","mobi","mot-","moto","nec-","netfront","newgen","nexian","nf-browser","nintendo","nitro","nokia","nook","novarra","obigo","palm","panasonic","pantech","philips","phone","pg-","playstation","pocket","pt-","qc-","qtek","rover","sagem","sama","samu","sanyo","samsung","sch-","scooter","sec-","sendo","sgh-","sharp","siemens","sie-","softbank","sony","spice","sprint","spv","symbian","tablet","talkabout","tcl-","teleca","telit","tianyu","tim-","toshiba","tsm","up.browser","utec","utstar","verykool","virgin","vk-","voda","voxtel","vx","wap","wellco","wig browser","wii","windows ce","wireless","xda","xde","zte");

    $is_mobile = false;

    foreach ($mobile_agents as $device) {
        if (stristr($user_agent, $device)) {
            $is_mobile = true;
            break;
        }
    }

    return $is_mobile;
}

function is_utf8($liehuo_net) 
{
	if (preg_match("/^([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}/",$liehuo_net) == true || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}$/",$liehuo_net) == true || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){2,}/",$liehuo_net) == true) 
	{
		return true; 
	}
	else 
	{ 
		return false; 
	}
}

//验重二维数组排序  $arr 数组 $keys比较的键值
function array_sort($arr,$keys,$type='asc'){ 
	$keysvalue = $new_array = array();
	foreach ($arr as $k=>$v){
		$keysvalue[$k] = $v[$keys];
	}
	if($type == 'asc'){
		asort($keysvalue);
	}else{
		arsort($keysvalue);
	}
	reset($keysvalue);
	$i = 0;
	foreach ($keysvalue as $k=>$v){
		if($i < 8 && $arr[$k][search] > 0){
			$new_array[] = $arr[$k]['value'];
			$i++;
		}
		
	}
	return $new_array; 
}
//自定义字段html输出     $field为特殊验重字段   $d_module=($ModuelView) 
function field_list_html($type="add",$module="",$d_module=array()){
	$field_list = M('Fields')->where('model = "'.$module.'"')->order('order_id')->select();
	foreach($field_list as $k=>$v){
		if(trim($v['input_tips'])){
			$input_tips = ' &nbsp; <span style="color:#005580;">(注: '.$v['input_tips'].')</span>';
		}else{
			$input_tips = '';
		}
		if('add' == $type){
			$value = $v['default_value'];
		} elseif ('edit' == $type && !empty($d_module)) {
			$value = $d_module[$v['field']] ? $d_module[$v['field']] : '';
		}
		switch ($v['form_type']) {
			case 'textarea' :
				$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><textarea  rows="6" class="span6" id="'.$v['field'].'" name="'.$v['field'].'" >'.$value.'</textarea> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
				break;
			case 'box' :
				$setting_str = '$setting='.$v['setting'].';';
				eval($setting_str);
				$field_list[$k]['setting'] = $setting;
				if ($setting['type'] == 'select') {
					$str = '';
					$str .= "<option value=''>--请选择--</option>";
					foreach ($setting['data'] as $v2) {
						$str .= "<option value='$v2'";
						$str .= $d_module[$v['field']] == $v2 ? 'selected="selected"':'';
						$str .= ">$v2</option>";
					}
					$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><select id="'.$v['field'].'" name="'.$v['field'].'">'.$str.'</select> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
					break;
				} elseif ($setting['type'] == 'radio') {
					$str = '';
                            $i = '';
					foreach ($setting['data'] as $v2) {
						$str .= " &nbsp; <input type='radio' name='".$v['field']."' id='".$v['field'].$i."' value='$v2'";
                        $str .= $d_module[$v['field']] == $v2 ? 'checked="checked"':'';
                        $str .= "/>&nbsp; $v2";
                        $i++;
					}
					$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td>'.$str.'  <span id="'.$v['field'].'Tip" style="color:red;"></span>&nbsp; '.$input_tips.'</td> </tr>';
					break;
				} elseif ($setting['type'] == 'checkbox') {
					$str = '';
					$i = '';
					foreach ($setting['data'] as $v2) {
						$str .= " &nbsp; <input type='checkbox' name='".$v['field']."[]' id='".$v['field'].$i."' value='$v2'";
						if(strstr($d_module[$v['field']],$v2)){
							$str .= 'checked="checked"';
						}
                        $str .= '/>&nbsp;' .$v2;
						$i++;
					}
					$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td>'.$str.' <span id="'.$v['field'].'Tip" style="color:red;"></span>&nbsp; '.$input_tips.'</td></tr>';
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
				<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><textarea name="'.$v['field'].'" id="'.$v['field'].'" style="width: 800px; height: 350px;">'.$value.'</textarea> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
				break;
			case 'datetime' :
				$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><input  onFocus="WdatePicker({dateFmt:\'yyyy-MM-dd\'})" name="'.$v['field'].'" class="span2" id="'.$v['field'].'" type="text" value="'.pregtime($value).'"/> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
				break;
			case 'number' :
				if($d_module['customer_id']){
					$customer_id = intval($d_module['customer_id']);
				}else{
					$customer_id = intval($_GET['customer_id']);
				}
				
				if($customer_id){
					$customer = M('customer')->where('customer_id = %d', $customer_id)->find();
					$contacts = M('contacts')->where('contacts_id = %d', $customer['contacts_id'])->find();
				}
				if ($v['field'] == 'customer_id') {
					if($customer_id){
						$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="hidden" name="'.$v['field'].'" id="customer_id" value="'.$customer['customer_id'].'"/><input  type="text" name="customer_name" id="customer_name" value="'.$customer['name'].'"/> <a target="_blank" href="'.U('customer/add').'">新建客户</a></td></tr>';
					}else{
						$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="hidden" name="'.$v['field'].'" id="customer_id"/><input  type="text" name="customer_name" id="customer_name"> <a target="_blank" href="'.U('customer/add').'">新建客户</a></td></tr>';
					}
				}elseif($v['field'] == 'contacts_id'){
					if($customer_id){
						$field_list[$k]['html'] = '<tr><td width="15%" class="tdleft">联系人(单击选择)</td>
						<td width="35%"><input type="hidden" name="contacts_id" id="contacts_id" value="'.$contacts['contacts_id'].'"/><input  type="text" name="contacts_name" id="contacts_name" value="'.$contacts['name'].'"/></td></tr>';
					}else{
						$field_list[$k]['html'] = '<tr><td width="15%" class="tdleft">联系人(单击选择)</td>
						<td width="35%"><input type="hidden" name="contacts_id" id="contacts_id"/><input  type="text" name="contacts_name" id="contacts_name"/></td></tr>';
					}
					
				}else{
					$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="text"  id="'.$v['field'].'" name="'.$v['field'].'" maxlength="'.$v['maxlength'].'" value="'.$value.'"/> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
				}
				
				break;
			case 'floatnumber' :
				$value = $value > 0 ? $value : ''; 
				$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="text" id="'.$v['field'].'" name="'.$v['field'].'" value="'.$value.'"/> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
				break;
			case 'address':
				if('add' == $type){
					$defaultinfo = unserialize(M('Config')->where('name = "defaultinfo"')->getField('value'));
					$state = $defaultinfo['state'];
					$city = $defaultinfo['city'];
				}else{
					$address_array = explode(chr(10),$value);
					$state = $address_array[0];
					$city = $address_array[1];
					$street = $address_array[2];
				}
				$field_list[$k]['html'] = '<script type="text/javascript">
				$(function(){
					new PCAS("'.$v['field'].'[\'state\']","'.$v['field'].'[\'city\']","'.$state.'","'.$city.'");
				});
				</script><tr>
					<td class="tdleft">'.$v['name'].':</td>
					<td><select name="'.$v['field'].'[\'state\']" class="input-medium"></select> 
					<select name="'.$v['field'].'[\'city\']" class="input-medium"></select>
					<input type="text" name="'.$v['field'].'[\'street\']" placeholder="街道信息" class="input-large" value="'.$street.'"></td>						
				</tr>';
				break;
			case 'p_box':
					$str = '';
					$category = M('product_category');
					$category_list = $category->select();
					$categoryList = getSubCategory(0, $category_list, '');
					foreach ($categoryList as $v2) {
						$checked = '';
						if($v2['category_id'] == $value){
							$checked = 'selected="selected"';
						}
						$str .= "<option $checked value=".$v2['category_id'].">".$v2['name']."</option>";
						
					}
					$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><select id="'.$v['field'].'" name="'.$v['field'].'">'.$str.'</select> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';

				break;
			case 'b_box':
					$status = M('BusinessStatus')->order('order_id')->select();
					$str = '';
					foreach ($status as $v2) {
						$str .= "<option value='".$v2['status_id']."'>".$v2['name']."</option>";
					}
					$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td><select id="'.$v['field'].'" name="'.$v['field'].'">'.$str.'</select> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
				break;
			default: 
				if ($v['field'] == 'create_time' || $v['field'] == 'update_time') {
					break;
				}else{
					$customer_id = intval($_GET['customer_id']);
					if($v['field'] == 'name' && $customer_id) $value=M('customer')->where('customer_id = %d', $customer_id)->getField('name');
					$field_list[$k]['html'] = '<tr><td class="tdleft" width="15%">'.$v['name'].'</td><td width="35%"><input type="text" id="'.$v['field'].'" name="'.$v['field'].'" maxlength="'.$v['maxlength'].'" value="'.$value.'"/> &nbsp;  <span id="'.$v['field'].'Tip" style="color:red;"></span>'.$input_tips.'</td></tr>';
				}
				break;
		}
	}
	return $field_list;
}

/*
	返回码说明 短信函数返回1发送成功  0进入审核阶段 -4手机号码不正确
*/
//单条短信
//发送到目标手机号码 $telphone手机号码 $message短信内容
function sendSMS($telphone, $message,  $sign_name="sign_name", $sendtime=''){
	// $sms = M('Config')->where('name = "sms"')->getField('value');
	// $sms = unserialize($sms);
	$sms = F('sms');
	//短信接口用户名 $uid
	$uid = $sms['uid'];
	//短信接口密码 $passwd
	$passwd = $sms['passwd'];
	$message = urlencode(iconv("UTF-8", "GBK//IGNORE", $message.'【'.$sms[$sign_name].'】'));
	$telphone = iconv("UTF-8", "GBK//IGNORE", $telphone);
	$gateway = "http://sms.3g6.com.cn/WS/Send.aspx?CorpID=$uid&Pwd=$passwd&Mobile=$telphone&Content=$message&Cell=&SendTime=$sendtime";
	$result = file_get_contents($gateway);
	return $result;
}
function sendtestSMS($uid, $uname, $telphone){
	$uid = trim($uid);
	$passwd = trim($uname);
	$message = "今天下午来开会!【CRM管理员】";
	$message = urlencode(iconv("UTF-8", "GBK//IGNORE", $message));
	$telphone = iconv("UTF-8", "GBK//IGNORE", $telphone);
	$gateway = "http://sms.3g6.com.cn/WS/Send.aspx?CorpID=$uid&Pwd=$passwd&Mobile=$telphone&Content=$message&Cell=&SendTime=";
	$result = file_get_contents($gateway);
	return $result;
}

//多条短信 最多600条
//发送到目标手机号码字符串 用","隔开 $telphone手机号码 $message短信内容 
function sendGroupSMS($telphone, $message, $sign_name="sign_name",$sendtime=''){
	$sms = F('sms');
	$uid = $sms['uid'];
	$passwd = $sms['passwd'];
	if($sms['sign_name']) $message = $message.'【'.$sms[$sign_name].'】';
	$telphone = iconv("UTF-8", "GBK//IGNORE", $telphone);
	$post_data = array();
	$post_data['Mobile'] = $telphone;
	$post_data['Content'] = $message;
	$post_data['SendTime'] = $sendtime;
	$post_data['CorpID'] = $uid;
	$post_data['Pwd'] = $passwd;
	$url='http://sms.3g6.com.cn/WS/BatchSend.aspx';
	$o="";
	foreach ($post_data as $k=>$v)
	{
		$o.= "$k=".urlencode(iconv("UTF-8", "GBK//IGNORE",$v))."&";
	}
	$post_data=substr($o,0,-1);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	$result = curl_exec($ch);
	if(substr($result,0,1) == 1) $result = 1;
	return $result;
} 

function getSmsNum(){
	//M('Config')->where('name = "sms"')->getField('value');
	//$sms = unserialize($sms);
	$sms = F('sms');
	//短信接口用户名 $uid
	$uid = $sms['uid'];
	//短信接口密码 $passwd
	$passwd = $sms['passwd'];
	$gateway = "http://sms.3g6.com.cn/WS/SelSum.aspx?CorpID=$uid&Pwd=$passwd";
	$result = file_get_contents($gateway);
	return $result;
}
//判断目录是否可写
function check_dir_iswritable($dir_path){
  $dir_path=str_replace('\\','/',$dir_path);
    $is_writale=1;
  if(!is_dir($dir_path)){
    $is_writale=0;
    return $is_writale;
  }else{
   $file_hd=@fopen($dir_path.'/test.txt','w');
   if(!$file_hd){
    @fclose($file_hd);
    @unlink($dir_path.'/test.txt');
    $is_writale=0;
    return $is_writale;
   }
   $dir_hd=opendir($dir_path);
   while(false!==($file=readdir($dir_hd))){
    if ($file != "." && $file != "..") {
     if(is_file($dir_path.'/'.$file)){
      //文件不可写，直接返回
      if(!is_writable($dir_path.'/'.$file)){
       return 0;
      } 
     }else{
      $file_hd2=@fopen($dir_path.'/'.$file.'/test.txt','w');
      if(!$file_hd2){
       @fclose($file_hd2);
       @unlink($dir_path.'/'.$file.'/test.txt');
       $is_writale=0;
       return $is_writale;
      }
      //递归
      $is_writale=check_dir_iswritable($dir_path.'/'.$file);
     }
    }
   }
  }
  return $is_writale;
}

function is_email($email)
{
	return strlen($email) > 8 && preg_match("/^[-_+.[:alnum:]]+@((([[:alnum:]]|[[:alnum:]][[:alnum:]-]*[[:alnum:]])\.)+([a-z]{2,4})|(([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))$/i", $email);
}
function is_phone($phone)
{
	return strlen(trim($phone)) == 11 && preg_match("/^1[3|5|8][0-9]{9}$/i", trim($phone));
}
function pregtime($timestamp){
	if($timestamp){
		return date('Y-m-d',$timestamp);
	}else{
		return '';
	}
}
/* 
方便数组调试 默认开启die
$arr    : 传入参数
$offset : 0 die ，1 关闭die
 */
function println($arr , $offset = 0){
	echo '<pre>';print_r($arr);echo '</pre>';
	if($offset == 0){
		die;
	}
}

function getContactsRQ($contacts_id,$width=200,$height=200){
	// $tempDir = './Public/qr_images/'; 
	// include(APP_PATH."Lib/ORG/qrlib.php");

	// if(!is_file($tempDir.'con_'.$contacts['contacts_id'].'.png')){
		// $contacts = M('Contacts')->where('contacts_id = %d', $contacts_id)->find();
		// $customer_id = M('RContactsCustomer')->where('contacts_id = %d',$contacts_id)->getField('customer_id');
		// $contacts['customer'] = M('Customer')->where('customer_id = %d', $customer_id)->getField('name');
		

		
		// $codeContents  = 'BEGIN:VCARD'."\n"; 
		// $codeContents .= 'VERSION:2.1'."\n"; 
		// $codeContents .= $contacts['name'] ? ("FN:".$contacts['name']."\n") : "";
		// $codeContents .= $contacts['telephone'] ? ("TEL;WORK;VOICE".$contacts['telephone']."\n") : "";
		// $codeContents .= $contacts['email'] ? ("EMAIL:".$contacts['email']."\n") : "";
		// $codeContents .= $contacts['customer'] ? ("ORG:".$contacts['customer']."\n") : "";	
		// $codeContents .= $contacts['post'] ? ("TITLE:".$contacts['post']."\n") : "";
		// $codeContents .= $contacts['address'] ? ("ADR:".$contacts['address']."\n") : "";
		// $codeContents .= "END:VCARD";
		
		// QRcode::png($codeContents, $tempDir.'con_'.$contacts['contacts_id'].'.png', QR_ECLEVEL_L, 3); 
	// }
	// return $tempDir.'con_'.$contacts['contacts_id'].'.png';
	
	$contacts = M('Contacts')->where('contacts_id = %d', $contacts_id)->find();
	$customer_id = M('RContactsCustomer')->where('contacts_id = %d',$contacts_id)->getField('customer_id');
	$contacts['customer'] = M('Customer')->where('customer_id = %d', $customer_id)->getField('name');
	$qrOpt = array();
	$qrOpt['chl'] = "BEGIN:VCARD\nVERSION:3.0\n";
	$qrOpt['chl'] .= $contacts['name'] ? ("FN:".$contacts['name']."\n") : "";
	$qrOpt['chl'] .= $contacts['telephone'] ? ("TEL:".$contacts['telephone']."\n") : "";
	$qrOpt['chl'] .= $contacts['email'] ? ("EMAIL:".$contacts['email']."\n") : "";
	$qrOpt['chl'] .= $contacts['customer'] ? ("ORG:".$contacts['customer']."\n") : "";	
	$qrOpt['chl'] .= $contacts['post'] ? ("TITLE:".$contacts['post']."\n") : "";
	$qrOpt['chl'] .= $contacts['address'] ? ("ADR:".$contacts['address']."\n") : "";
	$qrOpt['chl'] .= "END:VCARD";
	
	$qrOpt['chs'] = $width."x".$height;
	$qrOpt['cht'] = "qr";
	$qrOpt['chld'] = "|1";
	$qrOpt['choe'] = "UTF-8";
	$link = 'http://chart.googleapis.com/chart?'.http_build_query($qrOpt);
	return $link;
}
