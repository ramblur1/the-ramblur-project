<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Browser extends CI_Controller {

    // DESKTOP UNSUPPORTED BROWSER (currently only IE < 10)
    public function index() {

        $head['referrer'] = 'browser';
        $this->load->model('user');
        $head['loggedIn'] = $this->user->authCheck() !== false;

        $data = array();
        $data['device'] = 'desktop';

        $this->load->view('generic-header', $head);
        $this->load->view('browser', $data);
        $this->load->view('generic-footer');        

    }

    // MOBILE PHONES WITH OLD OS versions.  Currently:
    //  - Android less than 5
    //  - iOS less than 5
    //  - Windows IE less than 10
    public function mobile() {

        $data = array();
        $data['buttonText'] = 'ramblur';

        $this->load->library('session');

        // A THIRD PARTY OR THIRD PARTY PRIVATE URL
        if($this->session->onwardTP){

            // remind the Forum controller that it's a TPP
            $this->session->thirdparty = true;

            $head['referrer'] = 'browser-'.$this->uri->segment(3);
            $data['device'] = $this->uri->segment(3);

            $tpUrl = $this->session->onwardUrl;

            // private URLs have the extra 'private' level to add to the URL
            // NB: no single posts for private forums
            if($tpUrl[1]==='private') {

                $data['tpUrl'] = $tpUrl[1].'/'.$tpUrl[2].'/'.$tpUrl[3];
                $data['buttonText'] = 'forum';

            } else {

                $data['buttonText'] = 'forum';
                $data['tpUrl'] = $tpUrl[1].'/'.$tpUrl[2];
                
                // could be to a single post
                if(isset($tpUrl[3])) {

                    $data['tpUrl'] .= '/'.$tpUrl[3];
                    $data['buttonText'] = 'post';

                }

            }

        // MOBILE HOME URL OR FORUM
        } else {

            $head['referrer'] = 'browser';
            $data['device'] = 'mobile';
            $data['url'] = $this->config->item('base_url');
            
            // ADD URLs for NORMAL RAMBLUR FORUMS & SINGLE POSTS
            // onwardUrl set from Forum controller if not the 'index' page
            if(isset($this->session->onwardUrl)){

                $url = $this->session->onwardUrl;
                $data['buttonText'] = 'forum';
                $data['url'] .= $url[1].'/'.$url[2];

                // could be to a single post
                if(isset($url[3])) {

                    $data['url'] .= '/'.$url[3];
                    $data['buttonText'] = 'post';

                }

            }

        }

        $this->load->view('generic-header', $head);
        $this->load->view('browser', $data);
        $this->load->view('generic-footer');

        if(isset($this->session->onwardUrl)) {
            $this->session->unset_userdata('onwardUrl');
        }
        if(isset($this->session->onwardTP)) {
            $this->session->unset_userdata('onwardTP');
        }

    }

}
