<?php
define('COMMONSAPI_PLUGIN_DIR', dirname(__FILE__));
require_once PLUGIN_DIR . '/RecordRelations/models/RelatableRecord.php';

class CommonsApiPlugin extends Omeka_Plugin_AbstractPlugin
{

    protected $_hooks = array('install', 'uninstall', 'define_acl');

    public function hookInstall()
    {
        $db = get_db();
        $sql = "
            CREATE TABLE `$db->CommonsApiImport` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `site_id` INT UNSIGNED DEFAULT NULL ,
            `time` INT UNSIGNED NOT NULL ,
            `status` TEXT DEFAULT NULL ,
            `last_update` text COLLATE utf8_unicode_ci NOT NULL,
            `last_update_status` text COLLATE utf8_unicode_ci NOT NULL,
            INDEX ( `site_id` )
            ) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;
        ";

        $db->query($sql);
    }

    public function hookUninstall()
    {
        $db = get_db();
        $sql = "DROP TABLE IF EXISTS `$db->CommonsApiImport`";
        $db->query($sql);
    }


    public function hookDefineAcl($args)
    {
        require_once(COMMONSAPI_PLUGIN_DIR . '/CommonsApiAclAssertion.php');
        $acl = $args['acl'];
        $acl->addResource('CommonsApi_Import');
        $acl->allow(null, 'CommonsApi_Import', 'index', new CommonsApiAclAssertion);
    }

}