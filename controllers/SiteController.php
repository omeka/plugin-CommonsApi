<?php

class CommonsApi_SiteController extends Omeka_Controller_AbstractActionController
{

    public function updateAction()
    {

        $data = $_POST['data'];
        if(!isset($data['api_key']) || !$data['api_key']) {
            $response = array('status'=>'ERROR', 'message'=>'You must have a valid api key.');
            $this->_helper->json($response);
        }
        $sites = get_db()->getTable('Site')->findBy(array('api_key'=>$data['api_key']));
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

        if(!empty($_FILES['logo']['name'])) {
            $fileName = $site->id  .  '/' . $_FILES['logo']['name'];
            $filePath = PLUGIN_DIR . '/Sites/views/shared/images/' . $fileName;
            if(!file_exists(SITES_PLUGIN_DIR . '/views/shared/images/' . $site->id)) {
                mkdir(SITES_PLUGIN_DIR . '/views/shared/images/' . $site->id, 0755);
            }
            if(!move_uploaded_file($_FILES['logo']['tmp_name'], $filePath)) {
                $this->status[] = array('status'=>'ERROR', 'message'=>'Could not save the file to ' . $filePath );
            }
            $site->commons_settings['logo'] = $_FILES['logo']['name'];
        }
        foreach($data as $key=>$value) {
            debug($key . ' ' . $value);
            $site->$key = $value;
        }
        try {
            $site->save(true);
            $response = array('status'=>'OK', 'message'=>'Your site information has been updated');
        } catch(Exception $e) {
            $response = array('status'=>'ERROR', 'message'=>$e->getMessage());
        }
        $this->_helper->json($response);
    }

    public function applyAction()
    {
        $data = $_POST['data'];
        $sites = $this->_helper->db->getTable('Site')->findBy(array('url'=>$data['url']));
        if(!empty($sites)) {
            //check if an api key has not been assigned. if not, it means not approved
            //if so, it means they haven't entered it yet, since the commons plugin uses update if key is set
            $site = $sites[0];
            if($site->date_approved) {
                $response = array('status'=>'EXISTS', 'message'=>'Your site has been approved. You should have received instructions for entering your API key.');
                $this->_helper->json($response);
            } else {
                $response = array('status'=>'EXISTS', 'message'=>'Your site is still awaiting approval.');
                $this->_helper->json($response);
            }
        }
        $site = new Site();
        foreach($data as $key=>$value) {
            $site->$key = $value;
        }
        $salt = substr(md5(mt_rand()), 0, 16);
        $site->api_key = sha1($salt . $site->url . microtime() );

        if(! $this->createSiteAdminUser($site) ) {
            $response = array('status'=>'WARNING', 'message'=>'A user with that email exists. Log in to the Commons with that account to connect the site with the user after the site has been approved');
        } else {
            $response = array('status'=>'OK', 'message'=>'Check your email for info about the next steps');
        }
        $site->save();
        $this->_helper->json($response);
    }

    protected function createSiteAdminUser($site)
    {
        //check to see if a user with that email address already exists
        //this could happen when the same person manages several site
        //contributing to Commons
        //give a message back that that user-email exists
        //let them be the owner of a site with a management tool in Commons
        //there, they should have a means to claim ownership of the site
        //based on the key sent back?

        $user = $this->_helper->getDb()->getTable('User')->findByEmail($site->admin_email);
        if(!empty($user)) {
            return false;
        }

        $user = new User();
        $user->role = 'site-admin';
        $user->name = $site->admin_name;
        $username = $site->admin_email;
        $username = str_replace('@', 'AT', $username);
        $username = str_replace('.', 'DOT', $username);
        $user->username = $username;
        $user->email = $site->admin_email;
        $user->active = 1;
        $user->save();
        $activation = UsersActivations::factory($user);
        $activation->save();
        $site->owner_id = $user->id;
    }
}