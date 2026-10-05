function url2mediaParams(url) {
	var params = url.match(/\/var_?[a-z]*\/formats\/([^\/]*)\/([0-9]*)\/([0-9]*)\//);
	return {
		media_id: parseInt(' ' + params[2] + params[3]),
		media_crop_format: params[1]
	}
}
function runImageEditor(iParams) {
	var shiftKeyPressed = false;
	var media_params = url2mediaParams(iParams.src);
	$.ajax({
		url: settings.siteRoot + 'cms/pages/getImageEditor',
		type: "GET",
		data: {
			media_id: media_params.media_id,
			image_format: media_params.media_crop_format,
			width: iParams.display_width,
			height: iParams.display_height,
			ajax: 1
		},
		dataType: "json",
		success: function (response) {
			if (response.message && response.message.error) {
				p(response.message.error);
				return;
			}
			var
					data = response.data,
					mode = 'idle',
					mousedown_x,
					mousedown_y,
					dragThreshold = 1, // min pixelmove to start drag
					drag_image_mousedown, mousedragoffset_x, mousedragoffset_y,
					css,
					imageEditor = $(response.html),
					editorTargeted,
					b = 10, // active border size
					x, y, w, h, w_e, n_s, cursor, flex,
					origin_x, origin_y,
					crop_box_w, crop_box_h, crop_box_w_new, crop_box_h_new,
					x_dir, y_dir,
					move // true on move else scale
					;
			imageEditor.css({
				left: iParams.offset_left,
				top: iParams.offset_top
			});
			iParams.domObj.css({
				visibility: 'hidden'
//				opacity: 0.1
			});
			$('body').append(imageEditor);

			var drag_image = imageEditor.children('div.dragimage');
			var crop_image = imageEditor.find('img.cropimage');
			var flex_handle = imageEditor.find('div.flexHandle');
			var crop_box = crop_image.parent();
			drag_image.children('img').css({opacity: 0.1});
			$('body').bind('mousemove mouseover mousedown mouseup mouseleave keyup keydown', function (event) {
				event.preventDefault();
				editorTargeted = $(event.target).parents('div.imageEditor').length;
				if (event.ctrlKey && event.type === 'keydown') {
					if ($(".imageEditorRaster").length === 0 && $(".imageEditor").length === 1) {
						$raster = $("<div class='imageEditorRaster' style='height:" + $(document).outerHeight() + "px;'></div>");

						for (var i = 0; i < 150; i++) {
							$raster.append("<div data-index='" + i + "'></div>");
						}
						$("body").append($raster);
					} else {
						$("div.imageEditorRaster").remove();
					}
				}
				x = (event.pageX - drag_image.offset().left);
				y = (event.pageY - drag_image.offset().top);
				w = drag_image.width();
				h = drag_image.height();

				var crop_w = data.display_scale * crop_box_w;
				var crop_h = data.display_scale * crop_box_h;
				var crop_l = -parseInt(drag_image.css('margin-left'));
				var crop_t = -parseInt(drag_image.css('margin-top'));

				/*p({
				 crop_w: crop_w,
				 crop_h: crop_h,
				 crop_l: crop_l,
				 crop_t: crop_t
				 });*/
				w_e = (x < b) ? 'w' : ((x < (w - b)) ? '' : 'e');
				n_s = (y < b) ? 'n' : ((y < (h - b)) ? '' : 's');
				cursor = (n_s + w_e) ? n_s + w_e + '-resize' : 'move';
				drag_image.css({cursor: cursor});

				//	c();
				// p(shiftKeyPressed);

				switch (event.type) {
					case 'keyup':
					case 'keydown':
						shiftKeyPressed = event.shiftKey;
						break;
				}
				switch (mode + '.' + event.type) {
					case 'idle.mousedown':
						crop_box_w = crop_box.width();
						crop_box_h = crop_box.height();
						if ($(event.target).parents('div.aspectRatioSelector').length) {
							crop_box_h_new = crop_box_w / event.target.dataset.aspectratio;
							flex_handle.css({
								top: crop_box_h_new
							});
							crop_box.css({
								height: crop_box_h_new
							});
							break;
						}
						mousedown_x = event.pageX;
						mousedown_y = event.pageY;
						drag_image_mousedown = {
							left: parseInt(drag_image.css('margin-left')),
							top: parseInt(drag_image.css('margin-top')),
							width: parseInt(drag_image.css('width')),
							height: parseInt(drag_image.css('height'))
						}
						origin_x = (x < b) ? 1 : ((x < (w - b)) ? 0.5 : 0);
						origin_y = (y < b) ? 1 : ((y < (h - b)) ? 0.5 : 0);
						aoo_x = 0 //absolute origin offset
						x_dir = (x < b) ? -1 : ((x < (w - b)) ? 0 : 1);
						y_dir = (y < b) ? -1 : ((y < (h - b)) ? 0 : 1);

						mode = 'down';
						move = cursor === 'move';
						if (!editorTargeted) {
							imageEditor.remove();
							$("div.imageEditorRaster").remove();
							iParams.domObj.css({visibility: 'visible'});
						}
						break;
					case 'down.mouseup': // click to confirm or cancel
						$('body').unbind('mousemove mouseover mousedown mouseup mouseleave keyup keydown');
						if (editorTargeted) { // update image
							var src = iParams.src.split("?")[0]
							drag_image.css({cursor: 'wait'});
							if (iParams.is_backgroundimage) {
								$("div.imageEditorRaster").remove();
								imageEditor.remove();
								iParams.domObj.css({visibility: 'visible'});
							} else {
								iParams.domObj.bind('load', function () {
									iParams.domObj.unbind('load');
									imageEditor.remove();
									$("div.imageEditorRaster").remove();
									iParams.domObj.css({visibility: 'visible'});
								});
							}
							// Saved through the CMS (login, rights), then the format is
							// made again: ?rebuild passes the existing file in .htaccess
							// on to media.php, which only renders.
							$.ajax({
								url: settings.siteRoot + 'cms/pages/updateMediaCrop',
								type: "GET",
								data: {
									media_id: media_params.media_id,
									image_format: media_params.media_crop_format,
									width: crop_w / w / data.display_scale,
									height: crop_box.height() / h,
									left: (crop_l + crop_w / 2 / data.display_scale) / w,
									top: (crop_t + crop_h / 2 / data.display_scale) / h,
									ajax: 1
								}
							}).always(function () {
								var updatesrc = src + '?rebuild=' + Date.now();
								if (iParams.is_backgroundimage) {
									iParams.domObj.css('background-image', 'url(' + updatesrc + ')');
								} else {
									iParams.domObj[0].src = updatesrc;
								}
							});

						} else { // do nothing
							imageEditor.remove();
							$("div.imageEditorRaster").remove();
							iParams.domObj.css({visibility: 'visible'});
						}

					case 'drag.mouseup':
					//update image
					case 'flex.mouseup':
					case 'down.mouseleave':
						mode = 'idle';
						break;
					case 'down.mousemove':
						mousedragoffset_x = event.pageX - mousedown_x;
						mousedragoffset_y = event.pageY - mousedown_y;
						if (mousedragoffset_x * mousedragoffset_x + mousedragoffset_y * mousedragoffset_y > dragThreshold * dragThreshold) {
							mode = $(event.target).hasClass('flexHandle') ? 'flex' : 'drag';
							p(mode);
						}
						break;
					case 'flex.mousemove':
						move_y = event.pageY - mousedown_y;
						flex_handle.css({
							top: crop_box_h + move_y
						});
						crop_box.css({
							height: crop_box_h + move_y
						});
						break;
					case 'drag.mousemove':
						//jp('origin_x:' + origin_x);
						move_x = event.pageX - mousedown_x;
						move_y = event.pageY - mousedown_y;
						if (shiftKeyPressed) {
							if (Math.abs(move_x) > Math.abs(move_y)) {
								move_y = 0;
							} else {
								move_x = 0;
							}
						}
						move_x_rel = move_x / drag_image_mousedown.width;
						move_y_rel = move_y / drag_image_mousedown.height;
						move_rel = x_dir * move_x_rel + y_dir * move_y_rel;


						css = move ? {
							marginLeft: drag_image_mousedown.left + move_x,
							marginTop: drag_image_mousedown.top + move_y
						} : {
							marginLeft: drag_image_mousedown.left - move_x * x_dir * origin_x,
							marginTop: drag_image_mousedown.top - move_x * origin_y,
							width: drag_image_mousedown.width * (1 + x_dir * move_x_rel + y_dir * move_y_rel),
							height: drag_image_mousedown.height * (1 + x_dir * move_x_rel + y_dir * move_y_rel)
						};
						drag_image.css(css);
						crop_image.css(css);

						break;
					case 'idle.mouseup':
						break;
				}
				//jp(mode + '.' + event.type);
				//jp(x + '.' + w);
				//jp( drag_image_mousedown.left + event.pageX - mousedown_x);
			});
			//imageEditor.contentEditable = true;
//				//var range = document.createRange();
//				//range.selectNodeContents(imageEditor.children('img')[0]);
			imageEditor.bind('click', function (event) {
				event.preventDefault();
			});
		}
	});
}
;
function handleImageEditorEvents() {

}
