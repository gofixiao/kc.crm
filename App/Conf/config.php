<?php
return array(
	//'SHOW_PAGE_TRACE'=>true,
	'URL_MODEL'=>0,
	'URL_CASE_INSENSITIVE' =>true,
	'TMPL_ACTION_ERROR' => 'Public:message', 
	'TMPL_ACTION_SUCCESS' => 'Public:message',
	'TMPL_EXCEPTION_FILE'=>'./App/Tpl/Public/exception.html',
	'DEFAULT_TIMEZONE' => 'PRC',
	'LOAD_EXT_CONFIG' => 'db,version',
	'LOG_LEVEL'  =>'EMERG',
);
?>