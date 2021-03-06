<div id="player_primary_info">
    <div id="player_head">
        <img alt="" src="">
    </div>
    <div id="player_names">
        <div id="player_displayname">
            <a href="#"><?php echo Yii::t('generic', 'Loading...') ?></a>
        </div>
        <div id="player_name">
            <span><?php echo Yii::t('generic', 'Loading...') ?></span>
        </div>
        <div class="ui-grid-a" id="position_box">
            <div class="ui-block-a">
                <div>X: <span id="player_pos0"><?php echo Yii::t('generic', 'Loading...') ?></span></div>
                <div>Z: <span id="player_pos2"><?php echo Yii::t('generic', 'Loading...') ?></span></div>
            </div>
            <div class="ui-block-b">
                <div>Y: <span id="player_pos1"><?php echo Yii::t('generic', 'Loading...') ?></span></div>
                <div><?php echo Yii::t('player', 'Direction') ?>: <span id="player_orientation"><?php echo Yii::t('generic', 'Loading...') ?></span></div>
            </div>
        </div>
    </div>
    <div class="clear"></div>
</div>
<div class="ui-grid-a">
    <div class="ui-block-a">
        <div id="player_health">
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
            <span class="heart"><span></span></span>
        </div>
    </div>
    <div class="ui-block-b">
        <div id="player_armor">
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
            <span class="chestplate"><span></span></span>
        </div>
    </div>
</div>
<div data-role="controlgroup">
    <a href="" id="player_world" data-role="button"><?php echo Yii::t('player', 'World') ?>: <span id="player_world_name"><?php echo Yii::t('generic', 'Loading...') ?></span></a>
    <a id="ban_ip" href="#" data-role="button"><?php echo Yii::t('player', 'IP Address') ?>: <span id="player_ip"><?php echo Yii::t('generic', 'Loading...') ?></span></a>
    <a href="<?php echo $this->createUrl('player/utils') ?>" data-role="button" data-rel="dialog"><?php echo Yii::t('player', 'Utilities') ?></a>
</div>
<script type="text/javascript" src="<?php echo $this->createUrl('javascript/translation', array('cat' => 'serverutils')) ?>"></script>
<script type="text/javascript" src="<?php echo Yii::app()->baseUrl ?>/res/js/serverutils.js"></script>
<script type="text/javascript">
    (function(){
        var player = null;
        var playerIntervalID = null;
        var succeeded = false;
        var request = new ApiRequest('player', 'info');
        var requestData = {
            player: player,
            format:'json'
        }
        request.ignoreFirstFail(true);
        request.onSuccess(refreshData);
        request.onFailure(function(error){
            switch(error)
            {
                case 1:
                case 2:
                    if (succeeded)
                    {
                        clearInterval(playerIntervalID);
                        $('#player_toolbar_button').unbind('click').click(function(){
                            alert('<?php echo Yii::t('player', 'Function no longer available, the player left.\nPlease reload the page to try again.') ?>');
                            return false;
                        });
                        alert('<?php echo Yii::t('player', 'Player left the game.\nThe data wont be refreshed anymore.') ?>');
                    }
                    else
                    {
                        AdminBukkit.redirectTo('<?php echo $this->createUrl('player/list') ?>?_message=' + AdminBukkit.urlencode('<?php echo Yii::t('player', 'Player left the game.') ?>'));
                    }
            }
        });

        function refreshData(data)
        {
            succeeded = true;
            $('#player_name span:first').text(data.name);
            $('#player_displayname a:first').html(AdminBukkit.parseColors(data.displayName));
            var hearts = Math.floor(data.health / 2);
            $('#player_health').attr('title', data.health);
            $('#player_health span.heart span').removeClass('full');
            $('#player_health span.heart span').removeClass('half');
            $('#player_health span.heart:lt(' + hearts + ') span').addClass('full');
            if (data.health % 2 == 1)
            {
                $('#player_health span.heart:eq(' + hearts + ') span').addClass('half');
            }
            var armorDelta = 10 - Math.floor(data.armor / 2);
            $('#player_armor').attr('title', data.armor);
            $('#player_armor span.chestplate span').removeClass('full');
            $('#player_armor span.chestplate span').removeClass('half');
            $('#player_armor span.chestplate:gt(' + (armorDelta - 1) + ') span').addClass('full');
            if (data.armor % 2 == 1)
            {
                $('#player_armor span.chestplate:eq(' + (armorDelta - 1) + ') span').addClass('half');
            }
            $('#player_world').attr('href', '<?php $this->createUrl('world/view') ?>?world=' + data.world);
            $('#player_world_name').text(data.world);
            for (var index in data.blockPosition)
            {
                var elem = $('#player_pos' + index);
                elem.text(data.blockPosition[index]);
                elem.attr('title', data.position[index]);
            }

            $('#player_orientation').text(data.orientation.cardinalDirection);
            $('#player_ip').text(data.ip);
        }

        $('#player_view').bind('pagebeforeshow', function(){
            player = AdminBukkit.Registry.get('player.name');
            if (!player) {
                AdminBukkit.redirectTo(BASE_PATH + '/players');
            }
            requestData.player = player;
            $('div#player_view div.ui-header h1.ui-title').text(player);
            $('div#player_head img').first().attr('src', '<?php echo $this->createUrl('player/head') ?>?size=80&player=' + player);

            request.data(requestData);
            request.execute();
            playerIntervalID = setInterval(request.execute, 10000);
        })
        .bind('pagebeforehide', function(){
            if (playerIntervalID)
            {
                clearInterval(playerIntervalID);
            }
        }).bind('pagecreate', function(){
            $('#toolbar_player_view_refresh').click(function(){
                request.execute();
                return false;
            });

            $('#player_displayname').click(function(){
                var displayname = prompt('<?php echo Yii::t('player', 'Enter the new display name:') ?>', '');
                if (!displayname)
                {
                    return false;
                }
                var displayNameRequest = new ApiRequest('player', 'displayname');
                displayNameRequest.onSuccess(function(){
                    alert('<?php echo Yii::t('player', 'The display name has been successfully changed!') ?>');
                    request.execute();
                });
                displayNameRequest.onFailure(function(error){
                    switch (error)
                    {
                        case 1:
                            alert('<?php echo Yii::t('player', 'There was no player given!') ?>');
                            break;
                        case 2:
                            alert('<?php echo Yii::t('player', 'The given player was not found!') ?>');
                            break;
                        case 3:
                            alert('<?php echo Yii::t('player', 'There was no new display name given!') ?>');
                            break;
                    }
                });
                displayNameRequest.execute({
                    player: player,
                    displayname: displayname
                });
            });

            $('#ban_ip').click(function(){
                if (ban_ip($('#player_ip').text(), true))
                {
                    if (player_kick(player, true))
                    {
                        history.back();
                    }
                }
                return false;
            });
        });
    })();
</script>
    