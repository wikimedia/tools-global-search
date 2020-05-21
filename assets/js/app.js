const $ = require('jquery');

$(() => {
    $('form').on('submit', e => {
        $(e.target).find('input').prop('readonly', true);
        $(e.target).find('button').prop('disabled', true);
    });

    $('.btn-reset-form').on('click', e => {
        $('.results').hide();
        $('input').val('').prop('checked', false);
        $('#searchQuery').focus();
        $(e.target).remove();
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
            case 'subject':
                $('#namespaceIds').val('0,2,4,6,8,10,12,14');
                $('#titlePattern').val('');
                break;
            case 'talk':
                $('#namespaceIds').val('1,3,5,7,9,11,13,15');
                $('#titlePattern').val('');
                break;
        }
    });
});
