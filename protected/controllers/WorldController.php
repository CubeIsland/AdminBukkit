<?php
    class WorldController extends AccessControlledController
    {
        public $defaultAction = 'list';

        public function actionList()
        {
            $this->id = 'world_list';
            $this->title = Yii::t('world', 'Worldlist');
            $this->backButton = new BackToolbarButton();
            $this->utilButton = new ToolbarButton('world_list_refresh', Yii::t('generic', 'Refresh'));

            $this->render('list', array('server' => $this->user->getCurrentServer()));
        }

        public function actionView($world)
        {
            $this->id = 'world_view';
            $this->title = $world;
            $this->backButton = new BackToolbarButton();
            $this->utilButton = new ToolbarButton('world_view_refresh', Yii::t('generic', 'Refresh'));

            $this->render('view', array(
                'server' => $this->user->getCurrentServer(),
                'world' => $world
            ));
        }

        public function actionUtils($world)
        {
            $this->id = 'world_utils';
            $this->title = $world;

            $this->render('utils', array(
                'server' => $this->user->getCurrentServer(),
                'world' => $world
            ));
        }

        public function actionAdd()
        {
            $this->render('add', array('server' => $this->user->getCurrentServer()));
        }
    }
?>
