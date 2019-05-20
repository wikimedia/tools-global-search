const $ = require('jquery');

$(() => {
    $('form').on('submit', e => {
        $(e.target).find('input').prop('readonly', true);
        $(e.target).find('button').prop('disabled', true);
    });
});
