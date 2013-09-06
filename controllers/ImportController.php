<?php

class CommonsApi_ImportController extends Omeka_Controller_AbstractActionController
{
    public $importer;

    public function init()
    {
        $this->importer = new CommonsApi_Importer($_POST['data']);
        if($this->importer->hasErrors) {
            $this->_helper->json($this->importer->status);
            die();
        }
    }

    public function privatizeCollectionAction()
    {
        if(isset($this->data['privatizeCollection'])) {
            $params = array(
                'site_id' => $this->importer->site->id,
                'orig_id' => $this->importer->data['privatizeCollection'],
            );
            $collections = get_db()->getTable('SiteCollection')->findBy($params);
            $collection = $collections[0];
            $collection->public = false;
            $collection->save();
        }
    }

    public function privatizeItemAction()
    {
        $data = json_decode($_POST['data'], true);        
        if(isset($data['privatizeItem'])) {
            $params = array(
                'site_id' => $this->importer->site->id,
                'orig_id' => $this->importer->data['privatizeItem'],

            );
            $items = get_db()->getTable('SiteItem')->findItemsBy($params);        
            $item = $items[0];
            $item->public = false;
            $item->save();     
        }
        $responseArray = $this->importer->status;
        $response = json_encode($responseArray);        
        $this->_helper->json($response);        
    }

    public function indexAction()
    {
        
        $data = json_decode($_POST['data'], true);
        
        if(!$this->importer->hasErrors) {

            if(isset($data['collections'])) {
                foreach($data['collections'] as $collectionData) {
                    try {
                        $this->importer->processContext($collectionData, 'Collection');
                    } catch (Exception $e) {
                        _log($e);
                    }
                }
            }

            if(isset($data['exhibits'])) {
                foreach($data['exhibits'] as $index=>$exhibitData) {
                    try {
                        $this->importer->processContext($data['exhibits'][$index]['exhibit'], 'Exhibit');
                        $this->importer->processContext($data['exhibits'][$index]['section'], 'ExhibitSection');
                        $this->importer->processContext($data['exhibits'][$index]['page'], 'ExhibitSectionPage');
                    } catch (Exception $e) {
                        _log($e);
                    }
                }
            }

            if(!empty($data['items'])) {
                foreach($data['items'] as $item) {
                    try {
                        $this->importer->processItem($item);
                    } catch (Exception $e) {
                        _log($e);
                    }
                }
            }

        }
        $responseArray = $this->importer->status;
        $response = json_encode($responseArray);

        $this->_helper->json($response);
    }
}