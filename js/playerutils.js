var playerutilsLang = new PlayerutilsLang();

/**
 * @todo add item names
 */
var items = {
    
}

function player_kick(player, sync)
{
    var $result = false;
    if (confirm(playerutilsLang.kick_confirm))
    {
        var reason = prompt(playerutilsLang.kick_reason, '');
        if (!reason)
        {
            reason = '';
        }
        var request = new ApiRequest('player', 'kick');
        request.onSuccess(function(){
            alert(playerutilsLang.kick_success);
            $result = true;
        });
        request.sync(!!sync);
        request.execute({player: player, reason: reason});
    }
    return $result;
}

function player_kill(player)
{
    if (confirm(playerutilsLang.kill_confirm))
    {
        var request = new ApiRequest('player', 'kill');
        request.onSuccess(function(){
            alert(playerutilsLang.kill_success);
        });
        request.execute({player: player});
    }
}

function player_burn(player)
{
    var duration = prompt(playerutilsLang.burn_duration, '5');
    if (!duration)
    {
        return;
    }
    duration = duration.replace(/\s/g, '');
    if (!duration.match(/^\d+$/))
    {
        alert(playerutilsLang.burn_nonumber);
        return;
    }
    var request = new ApiRequest('player', 'burn');
    request.onSuccess(function(){
        alert(playerutilsLang.burn_success);
    });
    request.execute({player: player, duration: duration});
}

function player_heal(player)
{
    if (confirm(playerutilsLang.heal_confirm))
    {
        var request = new ApiRequest('player', 'heal');
        request.onSuccess(function(){
            alert(playerutilsLang.heal_success);
        });
        request.execute({player: player});
    }
}

function player_tell(player)
{
    var message = prompt(playerutilsLang.tell_message, '');
    if (!message)
    {
        return;
    }
    var request = new ApiRequest('player', 'tell');
    request.onSuccess(function(){
        alert(playerutilsLang.tell_success);
    });
    request.execute({player: player, message: message.substr(0, 100)});
}

function player_clearinv(player)
{
    if (confirm(playerutilsLang.clearinv_confirm))
    {
        var request = new ApiRequest('player', 'clearinventory');
        request.onSuccess(function(){
            alert(playerutilsLang.clearinv_success);
        });
        request.execute({player: player});
    }
}

/**
 * @todo add item names
 */
function player_give(player)
{
    var item = prompt(playerutilsLang.give_item, '');
    if (!item)
    {
        return;
    }
    item = item.replace(/\s/g, '');
    if (!item.match(/^\d+(:\d+)?$/))
    {
        alert(playerutilsLang.give_formatfail);
        return;
    }
    var data = 0;
    if (item.indexOf(':') > -1)
    {
        data = item.substr(item.indexOf(':') + 1);
    }
    var amount = prompt(playerutilsLang.give_amount, '64');
    if (!amount)
    {
        amount = '64';
    }
    amount = amount.replace(/\s/g, '');
    var request = new ApiRequest('player', 'give');
    request.onSuccess(function(){
        alert(playerutilsLang.give_success);
    });
    request.execute({player: player, itemid: item, data: data, amount: amount});
}

function player_teleport(player)
{
    var target = prompt(playerutilsLang.teleport_target, '');
    if (!target)
    {
        return;
    }
    var data = new Object();
    data.player = player;
    target = target.replace(/\s/g, '');
    if (target.match(/^\-?\d+(\.\d+)?,\-?\d+(\.\d+)?,\-?\d+(\.\d+)?(,\d+(\.\d+)?)?$/))
    {
        data.location = target;
    }
    else if (target.match(/^[\w\d\.]+$/))
    {
        data.targetplayer = target;
    }
    else
    {
        alert(playerutilsLang.teleport_invalidtarget);
        return;
    }
    var request = new ApiRequest('player', 'teleport');
    request.onSuccess(function(){
        alert(playerutilsLang.teleport_success);
    });
    request.execute(data);
}