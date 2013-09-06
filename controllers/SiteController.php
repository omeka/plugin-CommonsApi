<?php

class CommonsApi_SiteController extends Omeka_Controller_AbstractActionController
{

    public function updateAction()
    {
        $data = $_POST['data'];
        debug(print_r($data, true));
        $sites = get_db()->getTable('Site')->findBy(array('key'=>$data['api_key']));
        if(empty($sites)) {
            $response = array('status'=>'ERROR', 'message'=>'The api key does not exist.');
            $this->_helper->json($response);
            die();
        } else {
            $site = $sites[0];
            if(is_null($site->date_approved)) {
                $response = array('status'=>'EXISTS', 'message'=>'Your site is still awaiting approval.');
                $this->_helper->json($response);
                die();
            }
        }       
        $response = array('status'=>'OK', 'message'=>'Your site information has been updated');
        $this->_helper->json($response);
    }
    
    public function applyAction()
    {
        $data = $_POST['data'];
        debug(print_r($data, true));
        $site = new Site();
        
        foreach($data as $key=>$value) {
            $site->$key = $value;
        }
        $salt = substr(md5(mt_rand()), 0, 16);
        $site->api_key = sha1($salt . $site->url . microtime() );

        //@TODO: figure out when to create the site's user
        $site->owner_id = 1;
        $site->save();
        $response = array('status'=>'OK', 'message'=>'Check your email for info about the next steps');
        $this->_helper->json($response);
    }
}