$(document).ready(function () {
    /************** ON PAGE EDITIG ******************/
    if (typeof userGroups === 'undefined') {
	return;
    }
    if (checkAccess('on_page_editor', userGroups)) {
	initContenteditableContextMenus();
    }


    if (checkAccess('on_page_editor', userGroups)) {
	/************** INIT EVENTS ON UI ******************/
	//initDropdownMenus('body div.dropdown'); // edit product params and keywords
	/************** HTML EDITOR AND IMAGE EDITOR ******************/
	/*
	 $('body').bind("contextmenu", function (event) {
	 var
	 $target = $(event.target),
	 contextActionActive = false,
	 tagName = $target.prop("tagName")
	 ;
	 if (settings.inCms && !$target.hasClass("croppable")) {
	 return;
	 }
	 if (checkAccess('updatePageParam', userActions) && tagName !== 'IMG') {
	 var $HTMLeditor = false;
	 var oldhtml;
	 var cancel = false;
	 if ($target.data('field') && $target.data('node_id')) {
	 $HTMLeditor = $target;
	 } else {
	 $HTMLeditor = $target.parents('DIV[data-node_id][data-field]').first();
	 }
	 
	 if ($HTMLeditor.length == 1) {
	 contextActionActive = true; // avoid double activation
	 oldhtml = $HTMLeditor.html();
	 if (typeof $HTMLeditor.attr('contenteditable') === 'undefined') {
	 initHTMLeditorInputTimer($HTMLeditor);
	 }
	 ;
	 
	 $HTMLeditor.attr('contenteditable', true);
	 $HTMLeditor.trigger('focus');
	 event.preventDefault();
	 $HTMLeditor.bind('focusout keypress', function (e) {
	 
	 var HTMLeditorMenu = $('.HTMLeditorMenu').length ? true : false;
	 switch (e.type) {
	 case 'keypress':
	 switch (e.keyCode) {
	 case 27:
	 $HTMLeditor.html(oldhtml);
	 cancel = true;
	 $HTMLeditor.trigger('input');//send old html to server
	 break;
	 case 13:
	 if ($HTMLeditor[0].tagName !== 'DIV' && !e.shiftKey) { // klappt nicht bei a -> öffnet link!
	 e.preventDefault() // do not type <br>, store value
	 } else {
	 return;
	 }
	 break;
	 default:
	 return; //all other keytrokes while typing
	 break;
	 }
	 break;
	 case'focusout':
	 if (HTMLeditorMenu) {
	 cancel = true;
	 } else {
	 cancel = false;
	 }
	 break;
	 }
	 if (!cancel) {
	 $HTMLeditor.unbind('focusout keypress');
	 $HTMLeditor.attr('contenteditable', false);
	 if (rebuiltHTML) {
	 var $newEditor = $($.parseHTML(rebuiltHTML, '', false));
	 $HTMLeditor.html('');
	 $HTMLeditor.append($newEditor.children());
	 rebuiltHTML = false;
	 }
	 }
	 });
	 }
	 }
	 if (checkAccess('updateMediaCrop', userActions) && !contextActionActive) {
	 var $cnv_image = false;
	 if ($target.prop('tagName') == 'IMG') {
	 $cnv_image = $target;
	 } else {
	 $cnv_image = $target.find('img').first();
	 if ($cnv_image.length === 0) {
	 $cnv_image = $(event.target).parent().parent().find('img').first();
	 }
	 }
	 if ($cnv_image.prop('tagName') !== 'IMG') {
	 var $background_container = $(event.target).parent().find('[style*="background-image"]');
	 if ($background_container.length) {
	 event.preventDefault();
	 runImageEditor({
	 domObj: $background_container,
	 is_backgroundimage: true,
	 src: $background_container.css('background-image').replace('url(', '').replace(')', '').replace(/\"/gi, ""),
	 display_width: $background_container.width(),
	 display_height: $background_container.height(),
	 offset_left: $background_container.offset().left,
	 offset_top: $background_container.offset().top
	 });
	 }
	 } else if ($cnv_image.length > 0 && $cnv_image.attr('src')) {
	 if ($cnv_image.hasClass('noedit')) {
	 return;
	 }
	 if ($cnv_image.attr('src').match(/\/var_?[a-z]*\/formats\/([^\/]*)\/([0-9]*)\/([0-9]*)\//)) {
	 relX = event.pageX - $cnv_image.offset().left; // checking click-position
	 relY = event.pageY - $cnv_image.offset().top;
	 if (relX > 0 && relX < $cnv_image.width() && relY > 0 && relY < $cnv_image.height()) {
	 event.preventDefault();
	 runImageEditor({
	 domObj: $cnv_image,
	 is_backgroundimage: false,
	 src: $cnv_image.attr('src'),
	 display_width: $cnv_image.width(),
	 display_height: $cnv_image.height(),
	 offset_left: $cnv_image.offset().left,
	 offset_top: $cnv_image.offset().top
	 });
	 }
	 }
	 }
	 }
	 }); */
    }
});