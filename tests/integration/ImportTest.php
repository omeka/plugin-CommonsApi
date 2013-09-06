<?php
require_once(PLUGIN_DIR . '/CommonsApi/libraries/CommonsApi/Importer.php');
class ImportTest extends CommonsApi_Test_AppTestCase
{
    
    public function testImportInstallation()
    {
        $test_data = include(PLUGIN_DIR . '/CommonsApi/tests/test_data.php');
        $importer = new CommonsApi_Importer($test_data);
        $importer->processInstallation($test_data['installation']);

        $db = get_db();
        $installations = $db->getTable('Installation')->findBy(array('key'=>'123key'));
        $this->assertEquals(1, count($installations));
        $installation = $installations[0];
        $this->assertEquals('123key', $installation->key);
    }
    
    public function testImportCollection()
    {
        $test_data = include(PLUGIN_DIR . '/CommonsApi/tests/test_data.php');
        $importer = new CommonsApi_Importer($test_data);
        $importer->processCollection($test_data['collections'][0]);
        $db = get_db();
        $collections = $db->getTable('InstallationCollection')->findBy(array('installation_id'=>1, 'orig_id'=>10));
        $this->assertEquals(0, count($collections));
        
        $collections = $db->getTable('InstallationCollection')->findBy(array('installation_id'=>1, 'orig_id'=>3));
        $this->assertEquals(1, count($collections));
                
        $collection = $collections[0];
        $this->assertEquals($collection->installation_id, 1);
    }
    
    public function testImportExhibit()
    {
        $db = get_db();
        $test_data = include(PLUGIN_DIR . '/CommonsApi/tests/test_data.php');
        $importer = new CommonsApi_Importer($test_data);
        $importer->processExhibit($test_data['exhibits'][0]);
        $exhibits = $db->getTable('InstallationExhibit')->findBy(array('installation_id'=>1, 'orig_id'=>10));
        $this->assertTrue(count($exhibits) == 1);
        $exhibit = $exhibits[0];
        $this->assertEquals($exhibit->installation_id, 1);
    }
    
    public function testImportInstallationItem()
    {
        $db = get_db();
        $test_data = include(PLUGIN_DIR . '/CommonsApi/tests/test_data.php');
        $importer = new CommonsApi_Importer($test_data);
        $importer->processCollection($test_data['collections'][0]);
        $importer->processExhibit($test_data['exhibits'][0]);
        $importer->processItem($test_data['items'][0]);
        $installationItems = $db->getTable('InstallationExhibit')->findBy(array('installation_id'=>1, 'orig_id'=>9));
                
        $this->assertEquals(1, count($installationItems));
        

        $instItems = $db->getTable('InstallationItem')->findBy(array('orig_id' => 9));
        $instItem = $instItems[0];
        $item = $db->getTable('Item')->find($instItem->item_id);
        $this->assertFalse(empty($item) );
        
    }
    
    public function testImportItemFile()
    {
        
        $db = get_db();
        $test_data = include(PLUGIN_DIR . '/CommonsApi/tests/test_data.php');
        $importer = new CommonsApi_Importer($test_data);
        $importer->processCollection($test_data['collections'][0]);
        $importer->processExhibit($test_data['exhibits'][0]);
        $importer->processItem($test_data['items'][0]);
        $fileData = array(
            'item_orig_id' => 9,
            'url' => 'http://localhost/omeka/archive/files/df84e69a483f363097d235959d015c87.png'
        );
        $importer->processFile($fileData);
        
        $item = $db->getTable('InstallationItem')->findItemBy(array('orig_id' => 9));
        $files = $db->getTable('File')->findAll();
        $this->assertEquals('Item', get_class($item));

 
        $this->assertEquals(1, count($item->Files));
        
        
    }
    // */
    
}