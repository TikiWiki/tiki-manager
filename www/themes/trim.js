const BASE_URL = document.getElementsByTagName('base')[0].href;

$(document).ready(function () {
    $('.trim-instance-main-list .lock a').click(function () {
        var fa = $(this).find('span');
        var ins_id = $(this).data('id');
        var ins_type = 'unlock';

        if (fa.hasClass('fa-lock')) {
            fa.removeClass('fa-lock');
            fa.addClass('fa-unlock');
        } else {
            fa.removeClass('fa-unlock');
            fa.addClass('fa-lock');
            ins_type = 'lock';
        }

        $.ajax({
            url: BASE_URL + '/scripts/' + ins_type + '.php',
            type: 'POST',
            data: {
                id: ins_id
            }
        });
    });

    if ($('.trim-instance-new').length) {
        $('.trim-instance-new select').change(function () {
            $('.trim-instance-new input[name=type]').val($(this).find("option:selected").attr('value'));
        });

        $('#db_prefix').on('input', function() {
            let val = $(this).val();
            let user = '';
            let database_name = '';

            if (val) {
                user = val + '_user';
                database_name = val + '_db';
            }

            $('#db_user_preview').val(user);
            $('#db_name_preview').val(database_name);
        })
    }

    $('.trim-instance-main-list li').click(function (event) {
        if (event.target.localName === 'li') {
            window.location.href = $(this).attr('data-href');
        }
    });

    // restoring
    $('.trim-instance-list.restore ul.archive li').on('click', function () {
        $('.trim-instance-list.restore ul.archive li').each(function () {
            $(this).css('background-color', 'transparent');
        });
        $(this).css('background-color', 'palegreen');
        $('.restore.btn').attr('data-sourceid', $(this).data('id'));
        $('.restore.btn').attr('data-backup', $(this).find('.file').text());
        if (($('.restore.btn').attr('data-sourceid')) && ($('.restore.btn').attr('data-id'))) {
            $('.restore.btn').prop('disabled', false);
        }
    });

    $('.trim-instance-list.restore ul.destination li').click(function () {
        $('.trim-instance-list.restore ul.destination li').each(function () {
            $(this).css('background-color', 'transparent');
        });
        $(this).css('background-color', 'palegreen');
        $('.restore.btn').attr('data-id', $(this).data('id'));
        $('.restore.btn').attr('data-name', $(this).data('name'));
        if (($('.restore.btn').attr('data-sourceid')) && ($('.restore.btn').attr('data-id'))) {
            $('.restore.btn').prop('disabled', false);
        }
    });


    // cloning
    function clonecolors(selector) {
        $(selector).each(function () {
            $(this).css('background-color', 'transparent');
        });
    }

    $('.trim-instance-list.clone ul.source li').on('click', function () {
        var id = $(this).data('id');
        clonecolors('.trim-instance-list.clone ul.source li');
        clonecolors('.trim-instance-list.clone ul.destination li');
        $(this).css('background-color', 'palegreen');
        $('.clone.btn').attr('data-sourceid', id);
        $('.clone.btn').attr('data-sourcename', $(this).data('name'));
        $('.clone.btn').attr('data-id', '');
        $('.clone.btn').attr('data-name', '');
        $('.clone.btn').attr('disabled', true);
        $('.trim-instance-list.clone ul.destination').removeClass('hide');
        $('.trim-instance-list.clone ul.destination li').each(function () {
            $(this).removeClass('hide');
            if ($(this).data('id') == id) {
                $(this).addClass('hide');
            }
        });
    });

    $('.trim-instance-list.clone ul.destination li').on('click', function () {
        clonecolors('.trim-instance-list.clone ul.destination li');
        $(this).css('background-color', 'palegreen');
        $('.clone.btn').attr('data-id', $(this).data('id'));
        $('.clone.btn').attr('data-name', $(this).data('name'));
        if (($('.clone.btn').attr('data-sourceid')) && ($('.clone.btn').attr('data-id'))) {
            $('.clone.btn').prop('disabled', false);
        }
    });


    // deleting
    $('#deleteInstance').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var ins_id = button.data('id');
        var ins_name = button.data('name');
        var modal = $(this);
        modal.find('.modal-body').text("Do you really want to delete : " + ins_name);
        modal.find('.instance').val(ins_id);
        modal.find('.delete').click(function () {
            modal.find('form').submit();
        });
    });

    $('#deleteBackup').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var ins_id = button.data('id');
        var ins_name = button.data('name');
        var modal = $(this);
        modal.find('.modal-body').text("Do you really want to delete : " + ins_name);
        modal.find('.instance').val(ins_id);
        modal.find('.filename').val(ins_name);
        modal.find('.delete').click(function () {
            modal.find('form').submit();
        });
    });


    // modals
    $('.trim-instance-list li .url a').click(function (event) {
        event.stopPropagation();
    });

    $('#trimModal').on('hidden.bs.modal', function (event) {
        window.location.reload();
    });

    $('#createModal').on('hidden.bs.modal', function (event) {
        window.location = window.location.href;
    });

    $('#trimModal').on('show.bs.modal', function (event) {
        var ansi = {
            '\\[36m': '<span class="cyan">',
            '\\[33m': '<span class="orange">',
            '\\[31m': '<span class="red">',
            '\\[0m': '</span>'
        };

        var replaceable_ascii = {
            27: ''
        };

        var button = $(event.relatedTarget);
        var ins_id = button.data('id');
        var ins_name = button.data('name');
        var ins_type = button.data('type');
        var ins_branch = button.data('branch');
        var ins_sourceid = button.data('sourceid');
        var ins_sourcename = button.data('sourcename');
        var ins_branch = button.data('branch');
        var ins_backup = button.data('backup');
        var modal = $(this);

        modal.find('.modal-footer button').hide();
        if (ins_type == 'backup') {
            modal.find('h4').text('Backup instance');
            modal.find('.log').html('<span class="cyan">Performing backup for ' + ins_name + '...</span>');
        } else if (ins_type == 'update') {
            modal.find('h4').text('Update instance');
            modal.find('.log').html('<span class="cyan">Updating ' + ins_name + '...</span>');
        } else if (ins_type == 'fix') {
            modal.find('h4').text('Fix instance');
            modal.find('.log').html('<span class="cyan">Fixing permissions for ' + ins_name + '...</span>');
        } else if (ins_type == 'watch') {
            modal.find('h4').text('Watch instance');
            modal.find('.log').html('<span class="cyan">Checking ' + ins_name + ' for anomalies...</span>');
        } else if (ins_type == 'restore') {
            modal.find('h4').text('Restore instance');
            modal.find('.log').html('<span class="cyan">Restoring on ' + ins_name + '...</span>');
        } else if (ins_type == 'clone') {
            modal.find('h4').text('Clone instance');
            modal.find('.log').html('<span class="cyan">Cloning ' + ins_name + '...</span>');
        } else if (ins_type == 'upgrade') {
            modal.find('h4').text('Upgrade instance');
            modal.find('.log').html('<span class="cyan">Upgrading ' + ins_name + '...</span>');
        } else if (ins_type == 'cloneupgrade') {
            modal.find('h4').text('Clone and Upgrade instance');
            modal.find('.log').html('<span class="cyan">Cloning ' + ins_name + '...</span>');
        }

        var loading = "<i id=\"loading-icon\" class=\"fa fa-circle-o-notch fa-spin fa-fw cyan\"></i>\n" +
            "<span class=\"sr-only\">Loading...</span>";
        modal.find('.log').append(loading);

        var last_response_len = false;
        $.ajax({
            url: BASE_URL + '/scripts/' + ins_type + '.php',
            xhrFields: {
                onprogress: function (e) {
                    var this_response, response = e.currentTarget.response;
                    if (last_response_len === false) {
                        this_response = response;
                        last_response_len = response.length;
                    } else {
                        this_response = response.substring(last_response_len);
                        last_response_len = response.length;
                    }
                    log = this_response.trim();
                    var parsed_log = '';

                    for (var i = 0; i < log.length; i++) {
                        var char = log[i];

                        if (replaceable_ascii[log.charCodeAt(i)] !== undefined) {
                            char = replaceable_ascii[log.charCodeAt(i)];
                        }

                        parsed_log += char;
                    }

                    for (var key in ansi) {
                        parsed_log = parsed_log.replace(new RegExp(key, 'g'), ansi[key]);
                    }

                    modal.find('#loading-icon').before(parsed_log);
                    $(".log").scrollTop($(".log")[0].scrollHeight);

                }
            },
            type: 'POST',
            data: {
                id: ins_id,
                source: ins_sourceid,
                branch: ins_branch,
                backup: ins_backup
            }
        }).done(function (log) {
            modal.find('#loading-icon').hide();
            modal.find('.log').append('\n<span class="cyan">Done!</span>');
            modal.find('.modal-footer button').show();
        }).fail(function (response) {
            modal.find('.log').append('\n<span class="red">Something went wrong while performing this operation!</span>')
                .append('<span class="red">Status code: ' + response.status + ' (' + response.statusText + ')</span>')
                .append('<span>' + response.responseText + '</span>');
        });
    });

    // upgrade
    $('.trim-instance-list.upgrade ul.source li').on('click', function () {
        $('#loading-icon-branch').removeClass('hide');
        var id = $(this).data('id');
        $("#instance").val(id);
        $("#form-upgrade").submit();
    });

    $('.trim-instance-list.upgrade .branch').on('change', function () {
        $('.upgrade.btn').attr('data-sourceid', $("#instance").val());
        $('.upgrade.btn').attr('data-branch', this.value);
        if (!this.value || !$('.upgrade.btn').attr('data-sourceid')) {
            $('.upgrade.btn').attr('disabled', true);
        } else {
            $('.upgrade.btn').attr('disabled', false);
        }
    });

    // clone and upgrade
    function verifySubmitCloneUpgrade() {
        if (!$('.cloneupgrade.btn').attr('data-sourceid') ||
            !$('.cloneupgrade.btn').attr('data-id') ||
            !$('.cloneupgrade.btn').attr('data-branch')) {
            $('.cloneupgrade.btn').attr('disabled', true);
        } else {
            $('.cloneupgrade.btn').attr('disabled', false);
        }
    }

    $('.trim-instance-list.cloneupgrade ul.source li').on('click', function () {
        var id = $(this).data('id');
        clonecolors('.trim-instance-list.cloneupgrade ul.source li');
        $(this).css('background-color', 'palegreen');
        $('.cloneupgrade.btn').attr('data-sourceid', id);
        $('.cloneupgrade.btn').attr('data-sourcename', $(this).data('name'));
        $('.trim-instance-list.cloneupgrade ul.destination').removeClass('hide');
        $('.trim-instance-list.cloneupgrade ul.destination li').each(function () {
            $(this).removeClass('hide');
            if ($(this).data('id') == id) {
                $(this).addClass('hide');
            }
        });
        verifySubmitCloneUpgrade();
    });

    $('.trim-instance-list.cloneupgrade ul.destination li').on('click', function () {
        clonecolors('.trim-instance-list.cloneupgrade ul.destination li');
        $(this).css('background-color', 'palegreen');
        $('.cloneupgrade.btn').attr('data-id', $(this).data('id'));
        $('.cloneupgrade.btn').attr('data-name', $(this).data('name'));
        verifySubmitCloneUpgrade();
    });

    $('.trim-instance-list.cloneupgrade .branch').on('change', function () {
        $('.cloneupgrade.btn').attr('data-branch', this.value);
        verifySubmitCloneUpgrade();
    });

    $(".chosen-select").chosen();
});
