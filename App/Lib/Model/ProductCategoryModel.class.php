<?php 
    class ProductCategoryModel extends Model{

        protected $_validate = array(
			array('name', '', '该类名已经存在!',0, 'unique', 1),
			array('parent_id', 'require', '请选择父类', 0, ''),
		);

	}

