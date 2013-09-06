<?php
$elTextsSerialized = 'a:3:{s:11:"Dublin Core";a:2:{s:5:"Title";a:1:{i:0;s:11:"Custom Item";}s:7:"Subject";a:3:{i:0;s:29:"Book &amp; cassette favorites";i:1;s:4:"book";i:2;s:4:"cata";}}s:6:"Zotero";a:0:{}s:35:"Custom item type Item Type Metadata";a:2:{s:8:"Location";a:1:{i:0;s:3:"loc";}s:16:"Lesson Plan Text";a:1:{i:0;s:5:"teach";}}}';
$elTexts = unserialize($elTextsSerialized);

foreach($elTexts as $set=>$elements) {
  foreach($elements as $element=>$texts) {
    foreach($texts as $index=>$text) {
      $elTexts[$set][$element][$index] = array('text'=>$text, 'html'=>false);
    }

  }
}
$test_data = array(
    'key' => '123key',
    'installation_url' =>  'http://example.com/installation',
    'installation' => array(
                    'title' => 'Installation Title',
                    'admin_email' => 'admin@example.com',
                    'description' => 'Installation Description',
                    'import_url' => 'http://example.com/import_url',
                    'copyright_info' => 'CC-BY',
                    'author_info' => 'Installation Author Info'
                    ),
    'collections' => array(
                        array(
                            'title' => 'Collection Title',
                            'description' => 'Collection Description',
                            'orig_id' => 3,
                            'url' => 'http://example.com/collection'
                        ),
                        array(
                        
                        )
                    ),
                    
    'exhibits' => array(
                        array(
                            'title'=> 'Exhibit1 Title',
                            'description' => 'Exhibit1 Description',
                            'url' => 'http://example.com/exhibit1',
                            'orig_id' => 5
                        ),
                        array(
                        
                        )
                    ),
                    
    'items' => array(
                        array(
                            'orig_id' => 9,
                            'url' => 'http://example.com/item9',
                            'collection_orig_id' => 3,
                            'exhibit_orig_ids' => array(5),
                            'itemTypeName' => '',
                            'elementTexts' => $elTexts,
                            'citation' => "",
                            'files' => array(),
                            'tags' => array(),
                        ),
                        array(
                        
                        )
                    )

);

return $test_data;