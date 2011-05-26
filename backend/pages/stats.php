<?php
    $lang = Lang::instance('stats');
    $page = new Page('stats');
    $toolbar = new Toolbar($lang['stats']);
    $toolbar->setBack(Lang::instance('generic')->get('btn_home'), './');
    $page->addSubtemplate('toolbar', $toolbar);
    $tpl = new Template('pages/stats');
    $stats = array();
    $stats['user'] = Statistics::getValues('user.*');
    $stats['api_success'] = Statistics::getValues('api.succeeded.*');
    $fails = Statistics::getValues('api.failed.*');
    $failcount = 0;
    foreach ($fails as $fail)
    {
        $failcount += $fail['value'];
    }
    $stats['api_fails'] = $failcount;
    $tpl->assign('stats', $stats);
    $page->setContent($tpl);
    
    $design->setContentTpl($page);
?>