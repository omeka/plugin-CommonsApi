<?php


class CommonsApiImport extends Omeka_Record_AbstractRecord
{
    public $id;
    public $site_id;
    public $time;
    public $status;


    protected function beforeSave()
    {
        $this->status = serialize($this->status);
    }
}