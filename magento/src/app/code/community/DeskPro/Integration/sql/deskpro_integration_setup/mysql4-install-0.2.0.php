<?php

$installer = $this;
$installer->startSetup();
$installer->run("
   CREATE TABLE IF NOT EXISTS `{$installer->getTable('deskpro_integration/loginkey')}` (
	  `loginkey_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `customer_id` int(10) unsigned NOT NULL,
	  `loginkey` varchar(32) NOT NULL,
	  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  PRIMARY KEY (`loginkey_id`),
	  UNIQUE KEY `customer_id` (`customer_id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");
$installer->endSetup();