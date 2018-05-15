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
            url: '/scripts/' + ins_type + '.php',
            type: 'POST',
            data: {
                id: ins_id
            }
        });
    });

    $('.trim-instance-new select').change(function () {
        $('.trim-instance-new input[name=type]').val($(this).find("option:selected").attr('value'));
    });

    $('.trim-instance-main-list li').click(function (event) {
        if (event.target.localName === 'li') window.location.href = $(this).attr('data-href');
    });

    // restoring
    $('.trim-instance-list.restore ul.archive li').on('click', function () {
        $('.trim-instance-list.restore ul.archive li').each(function () {
            $(this).css('background-color', 'transparent');
            $(this).find('span.fa').addClass('hide');
        });
        $(this).css('background-color', 'greenyellow');
        $(this).find('span.fa').removeClass('hide');
        $('.restore.btn').attr('data-sourceid', $(this).data('id'));
        $('.restore.btn').attr('data-backup', $(this).find('.file').text());
        if (($('.restore.btn').attr('data-sourceid')) && ($('.restore.btn').attr('data-id'))) {
            $('.restore.btn').prop('disabled', false);
        }
    });

    $('.trim-instance-list.restore ul.destination li').click(function () {
        $('.trim-instance-list.restore ul.destination li').each(function () {
            $(this).css('background-color', 'transparent');
            $(this).find('.buttons a').addClass('hide');
        });
        $(this).css('background-color', 'greenyellow');
        $(this).find('.buttons a').removeClass('hide');
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
            $(this).parent().find('.buttons a').addClass('hide');
        });
    }

    $('.trim-instance-list.clone ul.source li').on('click', function () {
        var id = $(this).data('id');
        clonecolors('.trim-instance-list.clone ul.source li');
        clonecolors('.trim-instance-list.clone ul.destination li');
        $(this).css('background-color', 'greenyellow');
        $(this).find('.buttons a').removeClass('hide');
        $('.clone.btn').attr('data-sourceid', id);
        $('.clone.btn').attr('data-sourcename', $(this).data('name'));
        $('.clone.btn').attr('data-id', '');
        $('.clone.btn').attr('data-name', '');
        $('.trim-instance-list.clone ul.destination').removeClass('hide');
        $('.trim-instance-list.clone ul.destination li').each(function () {
            $(this).removeClass('hide');
            if ($(this).data('id') == id) $(this).addClass('hide');
        });
        if (($('.clone.btn').attr('data-sourceid')) && ($('.clone.btn').attr('data-id'))) {
            $('.clone.btn').prop('disabled', false);
        }
    });

    $('.trim-instance-list.clone ul.destination li').on('click', function () {
        clonecolors('.trim-instance-list.clone ul.destination li');
        $(this).css('background-color', 'greenyellow');
        $(this).find('.buttons a').removeClass('hide');
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

    $('#trimModal').on('show.bs.modal', function (event) {
        var ansi = {
            '\\[36m': '<span class="cyan">',
            '\\[33m': '<span class="orange">',
            '\\[31m': '<span class="red">',
            '\x1B\\[0m': '</span>'
        };

        var button = $(event.relatedTarget);
        var ins_id = button.data('id');
        var ins_name = button.data('name');
        var ins_type = button.data('type');
        var ins_sourceid = button.data('sourceid');
        var ins_sourcename = button.data('sourcename');
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
        }

        $.ajax({
            url: '/scripts/' + ins_type + '.php',
            type: 'POST',
            data: {
                id: ins_id,
                source: ins_sourceid,
                backup: ins_backup
            }
        }).done(function (log) {
            for (var key in ansi) {
                log = log.replace(new RegExp(key, 'g'), ansi[key]);
            }
            modal.find('.log').append(log);
            modal.find('.log').append('\n<span class="cyan">Done!</span>');
            modal.find('.modal-footer button').show();
        });
    });

});
