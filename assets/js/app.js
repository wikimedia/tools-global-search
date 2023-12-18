const $ = require('jquery');

$(() => {
    $('form').on('submit', e => {
        // Prevent queries gone wild...
        if ($('#searchQuery').val() === '.*' && !$('#titlePattern').val()) {
            e.preventDefault();
            alert(titleRequiredMsg);
            $('#titlePattern').focus();
            return;
        }
        $(e.target).find('input').prop('readonly', true);
        $(e.target).find('button').prop('disabled', true);
    });
    // Re-enable on pagehide, i.e. if user returned to page via browser history.
    $(window).on('pagehide', () => {
        $('form input').prop('readonly', false);
        $('form button').prop('disabled', false);
        $('#regexCheckbox').trigger('change');
    });

    $('.btn-reset-form').on('click', e => {
        $('.results').hide();
        $('input').val('').prop('checked', false);
        $('#searchQuery').focus();
        $(e.target).remove();
        $('#regexCheckbox').trigger('change');
        history.pushState({}, document.title, window.location.pathname);
    });

    if (!$('#searchQuery').val()) {
        $('#searchQuery').focus();
    }

    $('#regexCheckbox').on('change', e => {
        $('.form-group--ingorecase').toggleClass('hidden', !e.target.checked);
    });

    $('[data-toggle="tooltip"]').tooltip();

    $('.preset-link').on('click', e => {
        e.preventDefault();

        switch (e.target.dataset.value) {
            case 'js':
                $('#namespaceIds').val('2,4,8');
                $('#titlePattern').val('(Gadgets-definition|.*\\.(js|css|json))');
                break;
            case 'lua':
                $('#namespaceIds').val('828');
                $('#titlePattern').val('');
                break;
            case 'subject':
                $('#namespaceIds').val('0,2,4,6,8,10,12,14');
                $('#titlePattern').val('');
                break;
            case 'talk':
                $('#namespaceIds').val('1,3,5,7,9,11,13,15');
                $('#titlePattern').val('');
                break;
            case 'title-only':
                $('#searchQuery').val('.*');
                $('#regexCheckbox').prop('checked', true)
                    .trigger('change');
                if (!$('#titlePattern').val()) {
                    $('#titlePattern').focus();
                }
                break;
        }
    });
});
