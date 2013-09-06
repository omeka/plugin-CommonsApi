<?php

class CommonsApi_Importer
{
    public $data = array();
    public $status = array();
    public $hasErrors = false;
    public $has_container_id;
    public $site;
    public $site_url;
    public $db;
    public $key;

    public function __construct($data)
    {
        if(! is_array($data)) {
            $data = json_decode($data, true);
        }
        $this->site['items'] = array();
        $this->site['collections'] = array();
        $this->db = get_db();

        $this->data = $data;
        if($this->setSite()) {
            $this->processSite();
        }

        $has_container = $this->db->getTable('RecordRelationsProperty')->findByVocabAndPropertyName(SIOC, 'has_container');
        $this->has_container_id = $has_container->id;
    }

    public function processSite()
    {
        if(!is_dir(PLUGIN_DIR . '/Sites/views/public/images/' . $this->site->id)) {
            mkdir(PLUGIN_DIR . '/Sites/views/public/images/' . $this->site->id);
        }

        if(!empty($_FILES['logo']['name'])) {
            $fileName = $this->site->id  .  '/' . $_FILES['logo']['name'];
            $filePath = PLUGIN_DIR . '/Sites/views/public/images/' . $fileName;
            if(!move_uploaded_file($_FILES['logo']['tmp_name'], $filePath)) {
                _log('Could not save the file to ' . $filePath);
                $this->status[] = array('status'=>'error', 'messages'=>'Could not save the file to ' . $filePath );
            }
            $this->site->branding['logo'] = WEB_ROOT . '/plugins/Sites/views/public/images/' . $fileName;
        }

        if(!empty($_FILES['banner']['name'])) {
            $fileName = $this->site->id . '/' . $_FILES['banner']['name'];
            $filePath = PLUGIN_DIR . '/Sites/views/public/images/' . $fileName;
            if(!move_uploaded_file($_FILES['banner']['tmp_name'], $filePath)) {
                _log('Could not save the file to ' . $filePath);
                $this->status[] = array('status'=>'error', 'messages'=>'Could not save the file to ' . $filePath );
            }
            $this->site->branding['banner'] = WEB_ROOT . '/plugins/Sites/views/public/images/' . $fileName;
        }

        $this->site->last_import = Zend_Date::now()->toString('yyyy-MM-dd HH:mm:ss');
        $data = $this->preprocessSiteCss($this->data['site']);
        foreach($data as $field=>$value) {
            $this->site->$field = $value;
        }
        $this->site->save();
    }


    public function processItem($data)
    {
        $siteItem = $this->db->getTable('SiteItem')->findBySiteIdAndOrigId($this->site->id, $data['orig_id']);

        if($siteItem) {
            $item = $siteItem->findItem();
            $this->updateItem($item, $data);
        } else {
            $item = $this->importItem($data);
            $siteItem = new SiteItem();
            $siteItem->item_id = $item->id;
        }

        if(!empty($data['files'])) {
            $this->processItemFiles($item, $data['files']);
        }
        if(!empty($data['tags'])) {
            $this->processItemTags($item, $data['tags']);
        }

        $siteItem->site_id = $this->site->id;
        $siteItem->orig_id = $data['orig_id'];
        $siteItem->item_id = $item->id;
        $siteItem->url = $data['url'];
        $siteItem->license = $data['license'];
        try {
            $siteItem->save();
            $this->status['items'][$siteItem->orig_id] = array('status'=>'ok', 'commons_id'=>$item->id, 'status_message'=>'OK');
        } catch(Exception $e) {
            _log($e);
            $this->hasErrors = true;
            $this->status['items'][$siteItem->orig_id] = array('status'=>'error', 'commons_id'=>$item->id, 'status_message'=>$e->getMessage());
        }

        //update or add to collection information via RecordRelations
        $has_container = $this->db->getTable('RecordRelationsProperty')->findByVocabAndPropertyName(SIOC, 'has_container');

        if(isset($data['collection'])) {
            //collections are imported before items, so this should already exist
            $siteCollection = $this->db->getTable('SiteContext_Collection')->findBySiteIdAndOrigId($this->site->id,$data['collection']);
            $this->buildRelation($siteItem, $siteCollection);
        }

        //build relations to exhibit data
        //exhibits are imported before items, so they should already exist.

        if(isset($data['exhibitPages'])) {
            foreach($data['exhibitPages'] as $pageId) {
                $pageContext = $this->db->getTable('SiteContext_ExhibitSectionPage')->findBySiteIdAndOrigId($this->site->id,$pageId);
                $this->buildRelation($siteItem, $pageContext);

                $sectionId = $pageContext->site_section_id;
                $sectionContext = $this->db->getTable('SiteContext_ExhibitSection')->findBySiteIdAndOrigId($this->site->id,$sectionId);
                $this->buildRelation($siteItem, $sectionContext);

                $exhibitId = $sectionContext->site_exhibit_id;
                $exhibitContext = $this->db->getTable('SiteContext_Exhibit')->findBySiteIdAndOrigId($this->site->id, $exhibitId);
                $this->buildRelation($siteItem, $exhibitContext);
            }
        }
    }

    public function processContext($data, $context)
    {
        $contextRecord = $this->db->getTable('SiteContext_' . $context)->findBySiteIdAndOrigId($this->site->id, $data['orig_id']);
        if(!$contextRecord) {
            $class = 'SiteContext_' . $context;
            $contextRecord = new $class();
        }

        $contextRecord->site_id = $this->site->id;
        foreach($data as $key=>$value) {
            $contextRecord->$key = $value;
        }
        $contextRecord->last_update = Zend_Date::now()->toString('yyyy-MM-dd HH:mm:ss');
        try {
            $contextRecord->save();
            $this->status[$context . 's'][$contextRecord->id] = array('status'=>'ok', 'commons_id'=>$contextRecord->id, 'status_message'=>'OK');
        } catch(Exception $e) {
            $this->status[$context . 's'][$contextRecord->id] = array('status'=>'fail', 'commons_id'=>$contextRecord->id, 'status_message'=>$e->getMessage());
        }

        return $contextRecord;
    }

    public function buildRelation($item, $contextRecord, $options = null)
    {
        //default is to build around an item. otherwise a full $options array
        //can come in, and $item and $contextRecord are ignored

        if(!$options) {
            $options = array(
                'subject_record_type' => 'SiteItem',
                'subject_id' => $item->id,
                'object_record_type' => get_class($contextRecord),
                'object_id' => $contextRecord->id,
                'property_id' => $this->has_container_id,
                'user_id' => 1,
                'public' => true
            );
        }

        //use record relations here, so we can keep the history if a site
        //changes the collection an item is in

        //check if relation already exists
        $relation = $this->db->getTable('RecordRelationsRelation')->findOne($options);
        if(!$relation) {
            $relation = new RecordRelationsRelation();
            $relation->setProps($options);
            $relation->save();
        }
    }

    public function processItemFiles($item, $filesData)
    {
        //check if files have already been imported
        $fileTable = $this->db->getTable('File');
        foreach($filesData as $index=>$fileName) {
            $select = $fileTable->getSelectForCount();
            $select->where('original_filename = ?', $fileName );
            $count = $this->db->fetchOne($select);
            if($count != 0) {
                unset($filesData[$index]);
            }
        }
        $transferStrategy = 'Url';
        $options = array( );
        try {
            insert_files_for_item($item, $transferStrategy, $filesData, $options);
        } catch (Exception $e) {
            _log($e);
            $this->status[] = array('status'=>'error', 'item'=>$item->id, 'error'=>$e);
        }
    }

    public function processItemTags($item, $tags)
    {
        $item->addTags($tags);
        //maybe make core return tags when added?
        $tags = $item->getTags();

        $usesTag = $this->db->getTable('RecordRelationsProperty')->findByVocabAndPropertyName('http://ns.omeka-commons.org/', 'usesTag');
        //build the relations using omeka:usesTag property
        foreach($tags as $tag) {
            $options = array(
                'subject_record_type' => 'Site',
                'subject_id' => $this->site->id,
                'object_record_type' => 'Tag',
                'object_id' => $tag->id,
                'property_id' => $usesTag->id,
                'user_id' => 1
            );
            $this->buildRelation(null, null, $options);
        }
    }

    public function importItem($data)
    {
        $itemMetadata = $data;
        unset($itemMetadata['tags']);
        $itemElementTexts = $this->processItemElements($data);
        $itemMetadata['public'] = true;

        try {
            $item = insert_item($itemMetadata, $itemElementTexts);
        } catch (Exception $e) {
            _log($e);
            $this->status[] = array('status'=>'error', 'error'=>$e);
        }
        return $item;
    }

    public function updateItem($item, $data)
    {
        $itemMetadata = $data;
        $itemMetadata['overwriteElementTexts'] = true;
        unset($itemMetadata['tags']);
        $itemMetadata['public'] = true;
        $itemElementTexts = $this->processItemElements($data);
        try {
            update_item($item, $itemMetadata, $itemElementTexts);
        } catch (Exception $e) {
            _log($e);
            $this->status[] = array('status'=>'error', 'error'=>$e);
        }


    }

    public function processItemElements($data)
    {
        //process ItemTypes and ItemType Metadata to make sure they all exist first
        $newElementTexts = array();
        foreach($data['elementTexts'] as $elSet=>$elTexts) {
            if(strpos($elSet, 'Item Type Metadata') !== false) {
               $itemType = $this->processItemType($elSet);
               $newElementTexts['Item Type Metadata'] = $elTexts;
               $this->processItemTypeElements($itemType, $elTexts);
            } else {
                $newElementTexts[$elSet] = $elTexts;
            }
        }

        return $newElementTexts;
        //@TODO: prefix custom elements somewhere
    }

    public function processItemType($data)
    {

        //data might be a string if we're doing a pull from site, array if a push
        if(is_string($data)) {
            $itemTypeData = $this->parseSiteItemTypeData($data);
        }

        $itemTypesTable = $this->db->getTable('ItemType');
        $itemType = $itemTypesTable->findByName($itemTypeData['name']);

        if(!$itemType) {
            $itemType = $this->importItemType($itemTypeData);
        } else {

            if($itemType->name != $itemTypeData['name']) {
                $itemType->name = $itemTypeData['name'];
            }

            if( $itemType->description != $itemTypeData['description'] ) {
                $itemType->description = $itemTypeData['description'];
            }
            $itemType->save();
        }

        return $itemType;

    }

    public function processItemTypeElements($itemType, $data)
    {
        //make sure the elements exist and are updated
        foreach($data as $elName=>$elData) {
            if(! $itemType->hasElement($elName)) {
                $elData['name'] = $elName;

                $elementArray = array(array('name'=>$elData['name']));
                try {
                    $itemType->addElements($elementArray);
                } catch (Exception $e) {
                    _log($e);
                    $this->addError(array('item'=>$item->id, 'error'=>$e));
                }
            }
        }

        //@TODO: updating elements
    }

    public function importItemType($itemTypeData)
    {
        $itemType = new ItemType();
        $itemType->name = $itemTypeData['name'];
        if(isset($itemTypeData['description'])) {
            $itemType->description = $itemTypeData['description'];
        }
        $itemType->save();
        return $itemType;
    }

    public function parseSiteItemTypeData($name, $description = null)
    {
        $returnArray = array();

        //remove the 'Item Type Metadata' if it's there from how Commons exports
        $offset = strpos($name, ' Item Type Metadata');
        if($offset !== false) {
            $name = substr($name, 0, $offset);
        }
        $returnArray['name'] = $this->site->url . '/customItemTypes/' . $name;
        if($description) {
            $returnArray['description'] = $description;
        }
        return $returnArray;
    }

    public function setSite()
    {
        $sites = get_db()->getTable('Site')->findBy(array('url'=> $this->data['site_url']), 1);
        if(empty($sites)) {
            $this->status['status'] = 'error';
            $this->status['messages'] = 'Invalid Site URL';
            $this->hasErrors = true;
            _log("Site " . $this->data['site_url'] . " does not exist.");
            return false;
        }

        $site = $sites[0];

        if(is_null($site->added)) {
            $this->status['status'] = 'error';
            $this->status['messages'] = 'Site not yet approved. Check back later';
            $this->hasErrors = true;
            _log("Site " . $this->data['site_url'] . " not yet approved.");
            return false;
        }

        //check that the keys match!
        if($this->data['api_key'] != $site->api_key) {
            $this->status['status'] = 'error';
            $this->status['messages'] = 'Invalid key';
            $this->hasErrors = true;
            _log("Site " . $this->data['site_url'] . " has a bad key: " . $this->data['api_key']);
            return false;
        }

        $this->site = $site;
        return true;
    }

    public function preprocessSiteCss($data)
    {
        $css = "h1 {color: " . $data['commons_title_color'] .  "; }";
        $data['css'] = $css;
        unset($data['commons_title_color']);
        return $data;
    }
}