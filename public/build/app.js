(self["webpackChunkglobal_search"] = self["webpackChunkglobal_search"] || []).push([["app"],{

/***/ "./assets/js/app.js":
/*!**************************!*\
  !*** ./assets/js/app.js ***!
  \**************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

__webpack_require__(/*! core-js/modules/es.array.find.js */ "./node_modules/core-js/modules/es.array.find.js");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
$(function () {
  $('form').on('submit', function (e) {
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
  $(window).on('pagehide', function () {
    $('form input').prop('readonly', false);
    $('form button').prop('disabled', false);
    $('#regexRadio').trigger('change');
  });
  $('.btn-reset-form').on('click', function (e) {
    $('.results').hide();
    $('input').val('').prop('checked', false);
    $('input[name="mode"][value="plain"]').prop('checked', true);
    $('#searchQuery').focus();
    $(e.target).remove();
    $('#regexRadio').trigger('change');
    history.pushState({}, document.title, window.location.pathname);
  });
  if (!$('#searchQuery').val()) {
    $('#searchQuery').focus();
  }
  $('input[name="mode"]').on('change', function (e) {
    $('.form-group--ingorecase').toggleClass('hidden', e.target.value !== 'regex');
    $('.form-group--title-pattern').toggleClass('hidden', e.target.value === 'cirrus');
  });
  $('[data-toggle="tooltip"]').tooltip();
  $('.preset-link').on('click', function (e) {
    e.preventDefault();
    switch (e.target.dataset.value) {
      case 'js':
        $('#namespaceIds').val('2,4,8');
        $('#titlePattern').val('(Gadgets-definition|.*\\.(js|css|json))');
        $('#searchQuery').focus();
        break;
      case 'lua':
        $('#namespaceIds').val('828');
        $('#titlePattern').val('');
        $('#searchQuery').focus();
        break;
      case 'subject':
        $('#namespaceIds').val('0,2,4,6,8,10,12,14');
        $('#titlePattern').val('');
        $('#searchQuery').focus();
        break;
      case 'talk':
        $('#namespaceIds').val('1,3,5,7,9,11,13,15');
        $('#titlePattern').val('');
        $('#searchQuery').focus();
        break;
      case 'title-only':
        $('#searchQuery').val('.*');
        $('#regexRadio').prop('checked', true).trigger('change');
        if (!$('#titlePattern').val()) {
          $('#titlePattern').focus();
        }
        break;
    }
  });
});

/***/ }),

/***/ "./assets/css/app.css":
/*!****************************!*\
  !*** ./assets/css/app.css ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/rtl.css":
/*!****************************!*\
  !*** ./assets/css/rtl.css ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

},
/******/ __webpack_require__ => { // webpackRuntimeModules
/******/ var __webpack_exec__ = (moduleId) => (__webpack_require__(__webpack_require__.s = moduleId))
/******/ __webpack_require__.O(0, ["vendors-node_modules_bootstrap_dist_js_bootstrap_js-node_modules_core-js_modules_es_array_fin-f4becb"], () => (__webpack_exec__("./node_modules/jquery/dist/jquery.js"), __webpack_exec__("./node_modules/bootstrap/dist/js/bootstrap.js"), __webpack_exec__("./assets/js/app.js"), __webpack_exec__("./node_modules/bootstrap/dist/css/bootstrap.css"), __webpack_exec__("./assets/css/app.css"), __webpack_exec__("./assets/css/rtl.css")));
/******/ var __webpack_exports__ = __webpack_require__.O();
/******/ }
]);
//# sourceMappingURL=data:application/json;charset=utf-8;base64,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QUMsRUFBRSxDQUFDO1FBQzFCSixDQUFDLENBQUMsY0FBYyxDQUFDLENBQUNRLEtBQUssQ0FBQyxDQUFDO1FBQ3pCO01BQ0osS0FBSyxZQUFZO1FBQ2JSLENBQUMsQ0FBQyxjQUFjLENBQUMsQ0FBQ0ksR0FBRyxDQUFDLElBQUksQ0FBQztRQUMzQkosQ0FBQyxDQUFDLGFBQWEsQ0FBQyxDQUFDVyxJQUFJLENBQUMsU0FBUyxFQUFFLElBQUksQ0FBQyxDQUNqQ0UsT0FBTyxDQUFDLFFBQVEsQ0FBQztRQUN0QixJQUFJLENBQUNiLENBQUMsQ0FBQyxlQUFlLENBQUMsQ0FBQ0ksR0FBRyxDQUFDLENBQUMsRUFBRTtVQUMzQkosQ0FBQyxDQUFDLGVBQWUsQ0FBQyxDQUFDUSxLQUFLLENBQUMsQ0FBQztRQUM5QjtRQUNBO0lBQ1I7RUFDSixDQUFDLENBQUM7QUFDTixDQUFDLENBQUM7Ozs7Ozs7Ozs7OztBQzVFRjs7Ozs7Ozs7Ozs7OztBQ0FBIiwic291cmNlcyI6WyJ3ZWJwYWNrOi8vZ2xvYmFsLXNlYXJjaC8uL2Fzc2V0cy9qcy9hcHAuanMiLCJ3ZWJwYWNrOi8vZ2xvYmFsLXNlYXJjaC8uL2Fzc2V0cy9jc3MvYXBwLmNzcz8wYzEwIiwid2VicGFjazovL2dsb2JhbC1zZWFyY2gvLi9hc3NldHMvY3NzL3J0bC5jc3M/MGM4OSJdLCJzb3VyY2VzQ29udGVudCI6WyJjb25zdCAkID0gcmVxdWlyZSgnanF1ZXJ5Jyk7XG5cbiQoKCkgPT4ge1xuICAgICQoJ2Zvcm0nKS5vbignc3VibWl0JywgZSA9PiB7XG4gICAgICAgIC8vIFByZXZlbnQgcXVlcmllcyBnb25lIHdpbGQuLi5cbiAgICAgICAgaWYgKCQoJyNzZWFyY2hRdWVyeScpLnZhbCgpID09PSAnLionICYmICEkKCcjdGl0bGVQYXR0ZXJuJykudmFsKCkpIHtcbiAgICAgICAgICAgIGUucHJldmVudERlZmF1bHQoKTtcbiAgICAgICAgICAgIGFsZXJ0KHRpdGxlUmVxdWlyZWRNc2cpO1xuICAgICAgICAgICAgJCgnI3RpdGxlUGF0dGVybicpLmZvY3VzKCk7XG4gICAgICAgICAgICByZXR1cm47XG4gICAgICAgIH1cbiAgICAgICAgJChlLnRhcmdldCkuZmluZCgnaW5wdXQnKS5wcm9wKCdyZWFkb25seScsIHRydWUpO1xuICAgICAgICAkKGUudGFyZ2V0KS5maW5kKCdidXR0b24nKS5wcm9wKCdkaXNhYmxlZCcsIHRydWUpO1xuICAgIH0pO1xuICAgIC8vIFJlLWVuYWJsZSBvbiBwYWdlaGlkZSwgaS5lLiBpZiB1c2VyIHJldHVybmVkIHRvIHBhZ2UgdmlhIGJyb3dzZXIgaGlzdG9yeS5cbiAgICAkKHdpbmRvdykub24oJ3BhZ2VoaWRlJywgKCkgPT4ge1xuICAgICAgICAkKCdmb3JtIGlucHV0JykucHJvcCgncmVhZG9ubHknLCBmYWxzZSk7XG4gICAgICAgICQoJ2Zvcm0gYnV0dG9uJykucHJvcCgnZGlzYWJsZWQnLCBmYWxzZSk7XG4gICAgICAgICQoJyNyZWdleFJhZGlvJykudHJpZ2dlcignY2hhbmdlJyk7XG4gICAgfSk7XG5cbiAgICAkKCcuYnRuLXJlc2V0LWZvcm0nKS5vbignY2xpY2snLCBlID0+IHtcbiAgICAgICAgJCgnLnJlc3VsdHMnKS5oaWRlKCk7XG4gICAgICAgICQoJ2lucHV0JykudmFsKCcnKS5wcm9wKCdjaGVja2VkJywgZmFsc2UpO1xuICAgICAgICAkKCdpbnB1dFtuYW1lPVwibW9kZVwiXVt2YWx1ZT1cInBsYWluXCJdJykucHJvcCgnY2hlY2tlZCcsIHRydWUpO1xuICAgICAgICAkKCcjc2VhcmNoUXVlcnknKS5mb2N1cygpO1xuICAgICAgICAkKGUudGFyZ2V0KS5yZW1vdmUoKTtcbiAgICAgICAgJCgnI3JlZ2V4UmFkaW8nKS50cmlnZ2VyKCdjaGFuZ2UnKTtcbiAgICAgICAgaGlzdG9yeS5wdXNoU3RhdGUoe30sIGRvY3VtZW50LnRpdGxlLCB3aW5kb3cubG9jYXRpb24ucGF0aG5hbWUpO1xuICAgIH0pO1xuXG4gICAgaWYgKCEkKCcjc2VhcmNoUXVlcnknKS52YWwoKSkge1xuICAgICAgICAkKCcjc2VhcmNoUXVlcnknKS5mb2N1cygpO1xuICAgIH1cblxuICAgICQoJ2lucHV0W25hbWU9XCJtb2RlXCJdJykub24oJ2NoYW5nZScsIChlKSA9PiB7XG4gICAgICAgICQoJy5mb3JtLWdyb3VwLS1pbmdvcmVjYXNlJykudG9nZ2xlQ2xhc3MoJ2hpZGRlbicsIGUudGFyZ2V0LnZhbHVlICE9PSAncmVnZXgnICk7XG4gICAgICAgICQoJy5mb3JtLWdyb3VwLS10aXRsZS1wYXR0ZXJuJykudG9nZ2xlQ2xhc3MoJ2hpZGRlbicsIGUudGFyZ2V0LnZhbHVlID09PSAnY2lycnVzJyk7XG4gICAgfSk7XG5cbiAgICAkKCdbZGF0YS10b2dnbGU9XCJ0b29sdGlwXCJdJykudG9vbHRpcCgpO1xuXG4gICAgJCgnLnByZXNldC1saW5rJykub24oJ2NsaWNrJywgZSA9PiB7XG4gICAgICAgIGUucHJldmVudERlZmF1bHQoKTtcblxuICAgICAgICBzd2l0Y2ggKGUudGFyZ2V0LmRhdGFzZXQudmFsdWUpIHtcbiAgICAgICAgICAgIGNhc2UgJ2pzJzpcbiAgICAgICAgICAgICAgICAkKCcjbmFtZXNwYWNlSWRzJykudmFsKCcyLDQsOCcpO1xuICAgICAgICAgICAgICAgICQoJyN0aXRsZVBhdHRlcm4nKS52YWwoJyhHYWRnZXRzLWRlZmluaXRpb258LipcXFxcLihqc3xjc3N8anNvbikpJyk7XG4gICAgICAgICAgICAgICAgJCgnI3NlYXJjaFF1ZXJ5JykuZm9jdXMoKTtcbiAgICAgICAgICAgICAgICBicmVhaztcbiAgICAgICAgICAgIGNhc2UgJ2x1YSc6XG4gICAgICAgICAgICAgICAgJCgnI25hbWVzcGFjZUlkcycpLnZhbCgnODI4Jyk7XG4gICAgICAgICAgICAgICAgJCgnI3RpdGxlUGF0dGVybicpLnZhbCgnJyk7XG4gICAgICAgICAgICAgICAgJCgnI3NlYXJjaFF1ZXJ5JykuZm9jdXMoKTtcbiAgICAgICAgICAgICAgICBicmVhaztcbiAgICAgICAgICAgIGNhc2UgJ3N1YmplY3QnOlxuICAgICAgICAgICAgICAgICQoJyNuYW1lc3BhY2VJZHMnKS52YWwoJzAsMiw0LDYsOCwxMCwxMiwxNCcpO1xuICAgICAgICAgICAgICAgICQoJyN0aXRsZVBhdHRlcm4nKS52YWwoJycpO1xuICAgICAgICAgICAgICAgICQoJyNzZWFyY2hRdWVyeScpLmZvY3VzKCk7XG4gICAgICAgICAgICAgICAgYnJlYWs7XG4gICAgICAgICAgICBjYXNlICd0YWxrJzpcbiAgICAgICAgICAgICAgICAkKCcjbmFtZXNwYWNlSWRzJykudmFsKCcxLDMsNSw3LDksMTEsMTMsMTUnKTtcbiAgICAgICAgICAgICAgICAkKCcjdGl0bGVQYXR0ZXJuJykudmFsKCcnKTtcbiAgICAgICAgICAgICAgICAkKCcjc2VhcmNoUXVlcnknKS5mb2N1cygpO1xuICAgICAgICAgICAgICAgIGJyZWFrO1xuICAgICAgICAgICAgY2FzZSAndGl0bGUtb25seSc6XG4gICAgICAgICAgICAgICAgJCgnI3NlYXJjaFF1ZXJ5JykudmFsKCcuKicpO1xuICAgICAgICAgICAgICAgICQoJyNyZWdleFJhZGlvJykucHJvcCgnY2hlY2tlZCcsIHRydWUpXG4gICAgICAgICAgICAgICAgICAgIC50cmlnZ2VyKCdjaGFuZ2UnKTtcbiAgICAgICAgICAgICAgICBpZiAoISQoJyN0aXRsZVBhdHRlcm4nKS52YWwoKSkge1xuICAgICAgICAgICAgICAgICAgICAkKCcjdGl0bGVQYXR0ZXJuJykuZm9jdXMoKTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgYnJlYWs7XG4gICAgICAgIH1cbiAgICB9KTtcbn0pO1xuIiwiLy8gZXh0cmFjdGVkIGJ5IG1pbmktY3NzLWV4dHJhY3QtcGx1Z2luXG5leHBvcnQge307IiwiLy8gZXh0cmFjdGVkIGJ5IG1pbmktY3NzLWV4dHJhY3QtcGx1Z2luXG5leHBvcnQge307Il0sIm5hbWVzIjpbIiQiLCJyZXF1aXJlIiwib24iLCJlIiwidmFsIiwicHJldmVudERlZmF1bHQiLCJhbGVydCIsInRpdGxlUmVxdWlyZWRNc2ciLCJmb2N1cyIsInRhcmdldCIsImZpbmQiLCJwcm9wIiwid2luZG93IiwidHJpZ2dlciIsImhpZGUiLCJyZW1vdmUiLCJoaXN0b3J5IiwicHVzaFN0YXRlIiwiZG9jdW1lbnQiLCJ0aXRsZSIsImxvY2F0aW9uIiwicGF0aG5hbWUiLCJ0b2dnbGVDbGFzcyIsInZhbHVlIiwidG9vbHRpcCIsImRhdGFzZXQiXSwic291cmNlUm9vdCI6IiJ9