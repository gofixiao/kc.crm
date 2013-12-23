<?php 
	class ContactsViewModel extends ViewModel {
	   public $viewFields = array(
		'contacts'=>array('contacts_id','name','telephone','department','email','post','_type'=>'LEFT'),
		'RContactsCustomer'=>array('customer_id','_on'=>'RContactsCustomer.contacts_id=contacts.contacts_id','_type'=>'LEFT'),
		'Customer'=>array('name'=>'customer_name', '_on'=>'RContactsCustomer.customer_id=Customer.customer_id')
	   );
	} 