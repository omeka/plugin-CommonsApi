<?php
class CommonsApiAclAssertion implements Zend_Acl_Assert_Interface
{
    public function assert(Zend_Acl $acl,
            Zend_Acl_Role_Interface $role = null,
            Zend_Acl_Resource_Interface $resource = null,
            $privilege = null)
    {
        $db = get_db();
        $data = json_decode($_POST['data'], true);
        $key = $data['api_key'];
        $siteUrl = $data['site_url'];
        
        $site = $db->getTable('Site')->findByUrlKey($siteUrl, $key);
        if($site) {
            return true;
        }
        return false;
    }    
}