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
        if(!is_dir(PLUGIN_DIR . '/Sites/views/shared/images/' . $this->site->id)) {
            mkdir(PLUGIN_DIR . '/Sites/views/shared/images/' . $this->site->id);
        }

        if(!empty($_FILES['logo']['name'])) {
            $fileName = $this->site->id  .  '/' . $_FILES['logo']['name'];
            $filePath = PLUGIN_DIR . '/Sites/views/shared/images/' . $fileName;
            if(!move_uploaded_file($_FILES['logo']['tmp_name'], $filePath)) {
                _log('Could not save the file to ' . $filePath);
                $this->status[] = array('status'=>'error', 'messages'=>'Could not save the file to ' . $filePath );
            }
            $settings = json_decode($site->commons_settings, true);
            $settings['logo'] = $_FILES['logo']['name'];
            $site->commons_settings = json_encode($settings);
        }

        $this->site->last_import = Zend_Date::now()->toString('yyyy-MM-dd HH:mm:ss');
        $this->site->save();
    }


    public function processItem($data)
    {
        $siteItem = $this->db->getTable('SiteItem')->findBySiteIdAndOrigId($this->site->id, $data['orig_id']);

        if($siteItem) {
            $item = $siteItem->findItem();
            $item->setOwner($this->site->getOwner());
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
        
        debug('done processItem');

        //build relations to exhibit data
        //exhibits are imported before items, so they should already exist.
        // we punted on Exhibits for first release

        /*
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
        */
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
            $this->status[$context . 's'][$contextRecord->orig_id] = array('status'=>'ok', 'commons_id'=>$contextRecord->id, 'status_message'=>'OK');
        } catch(Exception $e) {
            $this->status[$context . 's'][$contextRecord->orig_id] = array('status'=>'fail', 'commons_id'=>$contextRecord->id, 'status_message'=>$e->getMessage());
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
        $options = array();
        try {
            insert_files_for_item($item, $transferStrategy, $filesData, $options);
        } catch (Exception $e) {
            _log($e);
            $this->status['files'][] = array('status'=>'error', 'commons_item_id'=>$item->id, 'status_message'=>$e->getMessage());
        }
    }

    public function processItemTags($item, $tags)
    {
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
        debug('importItem');
        $itemMetadata = $data;
        
        $itemMetadata['public'] = true;
        
        if(!empty($itemMetadata['itemTypeName'])) {
            $itemTypeOrigName = $itemMetadata['itemTypeName'];
            $itemTypeCommonsName = $this->site->url . '/customItemTypes/' . Inflector::underscore($itemTypeOrigName);
            $itemType = get_db()->getTable('ItemType')->findByName($itemTypeCommonsName);
            $this->processItemTypeElements($itemType, $data);
            $itemMetadata['item_type_name'] = $itemTypeCommonsName;
        }
        $itemElementTexts = $this->processItemElements($data);
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
        $itemMetadata['public'] = true;
        if(!empty($itemMetadata['itemTypeName'])) {
            $itemTypeOrigName = $itemMetadata['itemTypeName'];
            $itemTypeCommonsName = $this->site->url . '/customItemTypes/' . Inflector::underscore($itemTypeOrigName);            
            //$this->processItemTypeElements($itemType, $data);
            
            $itemMetadata['item_type_name'] = $itemTypeCommonsName;
        } 
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
        debug('processItemElements');
        //process ItemTypes and ItemType Metadata to make sure they all exist first
        $newElementTexts = array();
        foreach($data['elementTexts'] as $elSet=>$elTexts) {
            if(strpos($elSet, 'Item Type Metadata') !== false) {
               $itemType = $this->processItemType($elSet);
               
               $this->processItemTypeElements($itemType, $elTexts);
               $newElementTexts['Item Type Metadata'] = $elTexts;
            } else {
                $newElementTexts[$elSet] = $elTexts;
            }
        }
        return $newElementTexts;
    }

    /**
     * Makes sure the item type exists in the database for importing the item
     * @param unknown_type $data
     */
    public function processItemType($itemTypeMetadataLongName)
    {
        $offset = strpos($itemTypeMetadataLongName, ' Item Type Metadata');
        if($offset !== false) {
            $itemTypeOrigName = substr($itemTypeMetadataLongName, 0, $offset);
        }        
debug('processItemType');
        $itemTypeCommonsName = $this->site->url . '/customItemTypes/' . Inflector::underscore($itemTypeOrigName);
        //data might be a string if we're doing a pull from site, array if a push
        $itemTypesTable = $this->db->getTable('ItemType');
        $itemType = $itemTypesTable->findByName($itemTypeCommonsName);

        if(!$itemType) {
            $itemType = new ItemType();
            $itemType->name = $itemTypeCommonsName;
            $itemType->save();
        } 
        return $itemType;
    }

    /**
     * Makes sure that the imported Item Type Elements all exist in the database
     * before trying to add them to the item
     * @param unknown_type $itemType
     * @param unknown_type $data
     */
    public function processItemTypeElements($itemType, $data)
    {
        //make sure the elements exist and are updated
        $elementsArray = array();
        $elementTable = get_db()->getTable('Element');
        foreach($data as $elName=>$elData) {
            if(! $itemType->hasElement($elName)) {
                $el = $elementTable->findByElementSetNameAndElementName('Item Type Metadata', $elName);
                if($el) {
                    $elementsArray[] = $el;
                } else {
                    $elementsArray[] = array('name'=>$elName);
                }
            }
        }
        try {
            $itemType->addElements($elementsArray);
            $itemType->save();
        } catch (Exception $e) {
            _log($e);
            $this->addError(array('item'=>$item->id, 'error'=>$e));
        }
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

        if(is_null($site->date_approved)) {
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

}