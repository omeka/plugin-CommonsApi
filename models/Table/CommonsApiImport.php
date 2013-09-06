<?php

class CommonsApiImportTable extends Omeka_Db_Table
{

    public function findMostRecent($siteUrl)
    {
        $db = $this->getDb();
        $select = $this->getSelect();
        $alias = $this->getTableAlias;
        $siteAlias = $db->getTable('Site')->getTableAlias();
        $select->join(array($siteAlias=>$db->Site), "$alias.site_id = $siteAlias.id", array() );
        $select->where("$siteAlias.url = ?", $siteUrl);
        $select->order("$alias.id DESC");
        return $this->fetchObject($select);
    }


    public function recordFromData($data)
    {
        $data['status'] = unserialize($data['status']);
        $class = $this->_target;
        $obj = new $class($this->_db);
        $obj->setArray($data);
        return $obj;
    }

}