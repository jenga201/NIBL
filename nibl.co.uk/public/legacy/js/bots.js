(function ($) {
    'use strict';

    function singleCommand(row) {
        return '/msg ' + row.attr('data-botname') + ' xdcc send #' + row.attr('data-botpack');
    }

    function showPopup(msg) {
        $('#popuptext').html(msg);
        $('#popup').dialog({
            buttons: {
                'Clear': function () {
                    $(this).dialog('close');
                    $('input[name=batch]:checked').prop('checked', false);
                }
            }
        });
        $('#popuptext').select();
    }

    function commandFor(row) {
        var packs = $('input[name=batch]:checked').map(function () {
            var tr = $(this).closest('.botlistitem');
            return { botname: tr.attr('data-botname'), botpack: tr.attr('data-botpack') };
        }).get();

        if (packs.length === 0) {
            return singleCommand(row);
        }

        var byBot = {};
        $.each(packs, function (_, pack) {
            (byBot[pack.botname] = byBot[pack.botname] || []).push(pack.botpack);
        });

        var msg = '';
        $.each(byBot, function (botname, packs) {
            msg += '/msg ' + botname + ' xdcc batch ' + packs.join(',') + '\n';
        });

        return msg;
    }

    $(document).on('click', '.packnumber, .filesize, .filename', function (event) {
        if ($(event.target).closest('a').length) {
            return;
        }

        var msg = commandFor($(this).closest('.botlistitem'));
        if (msg.length > 0) {
            showPopup(msg);
        }
    });
}(jQuery));
