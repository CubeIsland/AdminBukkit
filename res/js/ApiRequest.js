var __APIREQUESTS_ENABLED = true;

function ApiRequest(controller, action)
{
    if (!__APIREQUESTS_ENABLED)
    {
        throw "ApiRequest is disabled!";
    }
    if (!controller || !action)
    {
        throw "Controller and action are required!";
    }
    var $this = this;
    var $controller = controller;
    var $action = (!!action ? action : '');
    var $url = BASE_PATH + '/proxy.php/' + $controller + '/' + $action;
    var $data = null;
    var $sync = false;
    var $method = 'GET';
    var $ignoreFirstFail = false;
    var $lastFailed = false;
    var $broken = false;

    var $onSuccess = null;
    var $onFailure = null;
    var $onBeforeSend = function(){
        $.mobile.showPageLoadingMsg();
    };
    var $onComplete = function(){
        $.mobile.hidePageLoadingMsg();
    };

    function onError(jqXHR, textStatus, thrownError)
    {
        if (!__APIREQUESTS_ENABLED)
        {
            return;
        }
        var errCode = $.trim(jqXHR.responseText);
        // only process errors with a response
        if (errCode)
        {
            errCode = errCode.split(',');
            var major = parseInt(errCode[0]);
            var minor = 0;
            if (errCode.length > 1)
            {
                minor = parseInt(errCode[1]);
            }
            switch (major)
            {
                case -1:
                    alert(AdminBukkit.t('generic', 'An unknown error occured.\nPlease inform an administrator about this error'));
                    break;
                case 0:
                    if (!$ignoreFirstFail || ($ignoreFirstFail && $lastFailed))
                    {
                        __APIREQUESTS_ENABLED = false;
                        if (confirm(AdminBukkit.t('generic', 'The server isn\'t reachable (anymore).\nCheck your server info and the server.\n\nBack to the main page?')))
                        {
                            AdminBukkit.redirectTo(BASE_PATH);
                        }
                    }
                    else
                    {
                        $lastFailed = true;
                    }
                    break;
                case 1:
                    alert(AdminBukkit.t('generic', 'An invalid path was entered!\nPlease inform an administrator about this error'));
                    break;
                case 2:
                    alert(AdminBukkit.t('generic', 'The given API password was invalid!\nPlease check your server data.'));
                    AdminBukkit.redirectTo(BASE_PATH + '/index.php/server/view', AdminBukkit.t('generic', 'Check your data or relog.'));
                    // @todo find a better method to redirect (message removal could be handled by the widget)
                    break;
                case 3:
                    // execute onFailure if set
                    if (typeof $onFailure == 'function')
                    {
                        try
                        {
                            $onFailure(minor);
                        }
                        catch (e)
                        {
                            // @todo handle this different
                            alert(e);
                        }
                    }
                    break;
                case 4:
                    alert(AdminBukkit.t('generic', 'The requested action is not (yet) implemented!\nUpgrading your API-plugins to a newer version could solve the problem.'));
                    $broken = true;
                    break;
                case 5:
                    alert(AdminBukkit.t('generic', 'The requested API controller does not exist!\nUpgrading your API-plugins to a newer version could solve the problem.'));
                    $broken = true;
                    break;
                case 6:
                    alert(AdminBukkit.t('generic', 'The requested API is disabled!.'));
                    $broken = true;
                    break;
                default:
                    //debug
                    alert('Failed: ' + jqXHR.responseText);
                    $broken = true;
                    break;
            }
        }
    }

    function onSuccess(data, textStatus, jqXHR)
    {
        $lastFailed = false;
        if (typeof $onSuccess == 'function')
        {
            $onSuccess(data, textStatus, jqXHR);
        }
    }

    this.onSuccess = function(callback)
    {
        if (typeof callback == 'function' || callback == null)
        {
            $onSuccess = callback;
        }
    }

    this.onFailure = function(callback)
    {
        if (typeof callback == 'function' || callback == null)
        {
            $onFailure = callback;
        }
    }

    this.onBeforeSend = function(callback)
    {
        if (typeof callback == 'function' || callback == null)
        {
            $onBeforeSend = callback;
        }
    }

    this.onComplete = function(callback)
    {
        if (typeof callback == 'function' || callback == null)
        {
            $onComplete = callback;
        }
    }

    this.sync = function(state)
    {
        if (typeof state !== 'undefined')
        {
            $sync = (state ? true : false);
            return $this;
        }
        else
        {
            return $sync;
        }
    }

    this.method = function(method)
    {
        if (typeof method !== 'undefined')
        {
            $method = method;
            return $this;
        }
        else
        {
            return $method;
        }
    }

    this.url = function()
    {
        return $url;
    }

    this.data = function(data)
    {
        if (typeof data !== 'undefined')
        {
            $data = data;
            return $this;
        }
        else
        {
            return $data;
        }
    }

    this.ignoreFirstFail = function(state)
    {
        if (typeof state !== 'undefined')
        {
            $ignoreFirstFail = (state ? true : false);
            return $this;
        }
        else
        {
            return $ignoreFirstFail;
        }
    }

    this.lastFailed = function()
    {
        return $lastFailed;
    }

    this.isBroken = function()
    {
        return $broken;
    }

    this.execute = function(data)
    {
        if (!__APIREQUESTS_ENABLED)
        {
            return null;
        }
        if ($broken)
        {
            return null;
        }
        var requestData = $data;
        if (data && data instanceof Object)
        {
            requestData = data;
        }

        var options = {
            url: $url,
            type: $method,
            data: requestData,
            async: !$sync,
            success: onSuccess,
            error: onError,
            beforeSend: $onBeforeSend,
            complete: $onComplete
        };
        if (!options.async)
        {
            options.timeout = 2;
        }

        return $.ajax(options);
    }
}