<?php 
    class KnowledgeModel extends Model{

        protected $_validate = array(
			array('title', '', '标题已经存在！', 0, 'unique', 1),
			array('title', 'require', '请填写标题'),
			array('category_id', 'require', '请选择产品类别', 0, ''),
		);
	}