<?php

class CommonsApi_Test_AppTestCase extends Omeka_Test_AppTestCase
{
    public function setUp()
    {
        parent::setUp();
        $pluginHelper = new Omeka_Test_Helper_Plugin;
        $pluginHelper->setUp('RecordRelations');
        $pluginHelper->setUp('Installations');
        $pluginHelper->setUp('CommonsApi');
        $this->_setUpVocabs();
        $this->_setUpData();
        $this->_authenticateUser($this->_getDefaultUser());

    }

    public function tearDown()
    {
        parent::tearDown();
    }
    
    private function _setUpVocabs()
    {
        $vocabs = include PLUGIN_DIR . '/RecordRelations/formal_vocabularies.php';
        record_relations_install_properties($vocabs);
        $prop = get_db()->getTable('RecordRelationsProperty')->findByVocabAndPropertyName(SIOC, 'has_container');
        if(empty($prop)) {
            $propData = array(
                'namespace_prefix' => 'sioc',
                'namespace_uri' => SIOC,
                'properties' => array(
                    array(
                        'local_part' => 'has_container',
                        'label' => 'Has container',
                        'description' => 'The Container to which this Item belongs.'
                    )
                )
            );
            record_relations_install_properties(array($propData));
        }
    }
    
    private function _setUpData()
    {
        $collection = new InstallationCollection();
        $collection->url = "http://example.org/test/collection";
        $collection->title = "Test Collection Title";
        $collection->orig_id = 200;
        $collection->installation_id = 1;
        $collection->save();
        
        $item = new Item();
        $item->save();
     
        $instItem = new InstallationItem();
        $instItem->installation_id = 1;
        $instItem->item_id = $item->id;
        $instItem->orig_id = 100;
        $instItem->setRelationData(array('object_id'=>$collection->id));
        $instItem->save();
     //   */
    }
}