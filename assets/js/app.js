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
});
