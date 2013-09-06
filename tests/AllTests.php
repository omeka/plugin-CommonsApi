<?php

require_once (PLUGIN_DIR . '/CommonsApi/tests/CommonsApi_Test_AppTestCase.php');

class CommonsApi_AllTests extends PHPUnit_Framework_TestSuite
{
    public static function suite()
    {
        $suite = new CommonsApi_AllTests('CommonsApi Tests');
        $testCollector = new PHPUnit_Runner_IncludePathTestCollector(
          array(dirname(__FILE__) . '/integration')
        );
        $suite->addTestFiles($testCollector->collectTests());
        return $suite;
    }
}