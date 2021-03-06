<?php
    class AccessControlledController extends Controller
    {
        /**
         * @var User the currently logged in user
         */
        protected $user;

        public function init()
        {
            parent::init();

            $this->user = User::getCurrent();
        }
        
        public function filters()
        {
            return array(
                'accessControl',
            );
        }

        public function accessRules()
        {
            return array(
                array(
                    'allow',
                    'users' => array('@'),
                ),
                array(
                    'deny',
                ),
            );
        }
    }
?>
