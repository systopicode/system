
var rebuiltHTML = false;
function handleHTMLeditorServerResponse(response, element) {
	rebuiltHTML = response.html;

}

function HTMLeditorRightClick(clicktype, event, elements) {
	var tag = $(event.target).prop('tagName');
	var format = event.target.dataset.format;
	switch (clicktype) {
		case "image":
			var subs = [];
			imageFormats['original'] = 'original';
			$.each(imageFormats, function (index, el) {
				if (el.cms_display !== 'none') {
					subs.push({
						title: index,
						func: 'changeHTMLeditorImage',
						className: 'imageformats',
						selected: format === index ? true : false,
						param: {
							target: event.target,
							imageFormat: index
						}
					});
				}
			});

			new contextMenuWW(
					{
						formats: {
							title: 'Formats',
							className: 'fa-light fa-image',
							active: ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes($(event.target).data('mimetype')) ? true : false,
							subs: subs
						},
						edit: {
							title: 'Edit',
							className: 'fa-light fa-crop-simple',
							func: 'formatImage',
							param: {
								target: event.target
							}
						},
						insertAnchor: {
							active: $(event.target).parent().prop('tagName') === 'A' ? false : true,
							title: 'Insert Link',
							className: 'fa-light fa-link',
							func: 'createHTMLeditorLink',
							param: {
								target: event.target
							}
						},
						removeanchor: {
							active: $(event.target).parent().prop('tagName') === 'A' ? true : false,
							title: 'remove Link',
							className: 'fa-light fa-link-slash',
							func: 'removeAnchor',
							param: {
								target: event.target
							}
						},
						editanchor: {
							active: $(event.target).parent().prop('tagName') === 'A' ? true : false,
							title: 'Edit Link',
							className: 'fa-light fa-pen-to-square',
							selected: $(event.target).parent().prop('tagName') === 'A' ? true : false,
							func: 'createHTMLeditorLink',
							param: {
								target: event.target
							}
						},
						clear: {
							title: 'Remove from Page',
							className: 'fa-light fa-trash',
							func: 'removeImageFromHTMLeditor',
							param: {
								target: event.target
							}
						}
					}, event
					);
			break;
		case "video":
			new contextMenuWW(
					{
						edit: {
							title: 'Edit',
							className: 'fa-light fa-pen-to-square',
							func: 'formatImage',
							param: {
								target: event.target
							}
						},
						controls: {
							title: 'Toggle Controls',
							className: 'fa-light fa-light fa-ellipsis',
							func: event.target.getAttribute('controls') ? 'removeAttribute' : 'setAttribute',
							param: {
								attr: 'controls',
								val: 'controls',
								target: event.target
							}
						},
						playsinline: {
							title: 'Toggle Playsinline',
							className: 'fa-light fa-frame',
							func: event.target.getAttribute('playsinline') ? 'removeAttribute' : 'setAttribute',
							param: {
								attr: 'playsinline',
								val: 'playsinline',
								target: event.target
							}
						},
						autoplay: {
							title: 'Toggle Autoplay',
							className: 'fa-light fa-play',
							func: event.target.getAttribute('autoplay') ? 'removeAttribute' : 'setAttribute',
							param: {
								attr: 'autoplay',
								val: 'autoplay',
								target: event.target
							}
						},
						muted: {
							title: event.target.getAttribute('muted') ? 'Set Volumne on' : 'Mute Video',
							className: event.target.getAttribute('muted') ? 'fa-light fa-volume-slash' : 'fa-light fa-volume',
							func: event.target.getAttribute('muted') ? 'removeAttribute' : 'setAttribute',
							param: {
								attr: 'muted',
								val: 'muted',
								target: event.target
							}
						},
						loop: {
							title: 'Toggle Loop',
							className: 'fa-light fa-arrows-repeat',
							func: event.target.getAttribute('loop') ? 'removeAttribute' : 'setAttribute',
							param: {
								attr: 'loop',
								val: 'loop',
								target: event.target
							}
						},
						clear: {
							title: 'Remove from Page',
							className: 'fa-light fa-trash',
							func: 'removeImageFromHTMLeditor',
							param: {
								target: event.target
							}
						}
					}, event
					);
			break;
		case "iframe":
			var subs = [];
			new contextMenuWW(
					{

						edit: {
							title: 'Edit',
							className: 'fa-light fa-pen-to-square',
							func: 'formatImage',
							param: {
								target: event.target
							}
						},

						clear: {
							title: 'Remove from Page',
							className: 'fa-light fa-trash',
							func: 'removeImageFromHTMLeditor',
							param: {
								target: event.target
							}
						}
					}, event
					);
			break;
		case "selectedTable":
			new contextMenuWW(
					{
						rowMenu: {
							title: '',
							className: 'fa-light fa-border-center-h',
							subs: {
								duplicate: {
									title: 'duplicate Row',
									func: 'editRows',
									param: {
										elements: elements,
										action: "duplicate"
									}
								},
								insertBefore: {
									title: 'insert before',
									func: 'editRows',
									param: {
										elements: elements,
										action: "insertBefore"
									}
								},
								insertAfter: {
									title: 'insert after',
									func: 'editRows',
									param: {
										elements: elements,
										action: "insertAfter"
									}
								},
								remove: {
									title: 'remove',
									func: 'editRows',
									param: {
										elements: elements,
										action: "remove"
									}
								},
							}
						},
						columnMenu: {
							title: '',
							className: 'fa-light fa-border-center-v',
							subs: {
								insertBefore: {
									title: 'insert before',
									func: 'editColumns',
									param: {
										elements: elements,
										action: "insertBefore"
									}
								},
								insertAfter: {
									title: 'insert after',
									func: 'editColumns',
									param: {
										elements: elements,
										action: "insertAfter"
									}
								},
								remove: {
									title: 'remove',
									func: 'editColumns',
									param: {
										elements: elements,
										action: "remove"
									}
								},
							}
						},

						removeTable: {
							title: 'Remove Table',
							className: 'fa-light fa-trash',
							func: 'removeTable',
							param: {
								elements: elements,
							}

						}
					}, event, elements
					);
			break;
		case "selectedBlock":
			new contextMenuWW(
					{
						headlines: {
							title: '',
							className: 'fa-light fa-heading',
							selected: ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'].includes(tag) ? true : false,
							subs: {
								h1: {
									title: 'Headline 1',
									selected: ['H1'].includes(tag) ? true : false,
									func: 'formatHTMLeditor',
									param: {
										element: "formatBlock",
										tag: "h1"
									}
								},
								h2: {
									title: 'Headline 2',
									func: 'formatHTMLeditor',
									selected: ['H2'].includes(tag) ? true : false,
									param: {
										element: "formatBlock",
										tag: "h2"
									}
								},
								h3: {
									title: 'Headline 3',
									func: 'formatHTMLeditor',
									selected: ['H3'].includes(tag) ? true : false,
									param: {
										element: "formatBlock",
										tag: "h3"
									}
								},
								h4: {
									title: 'Headline 4',
									func: 'formatHTMLeditor',
									selected: ['H4'].includes(tag) ? true : false,
									param: {
										element: "formatBlock",
										tag: "h4"
									}
								},
								h5: {
									title: 'Headline 5',
									func: 'formatHTMLeditor',
									selected: ['H5'].includes(tag) ? true : false,
									param: {
										element: "formatBlock",
										tag: "h5"
									}
								},
								h6: {
									title: 'Headline 6',
									func: 'formatHTMLeditor',
									selected: ['H6'].includes(tag) ? true : false,
									param: {
										element: "formatBlock",
										tag: "h6"
									}
								}
							}
						},
						paragraph: {
							title: 'Paragraph',
							className: 'fa-light fa-paragraph',
							selected: ['P'].includes(tag) ? true : false,
							nodeName: 'P',
							func: 'formatHTMLeditor',
							param: {
								element: "formatBlock",
								tag: "p"
							}
						},
						insertAnchor: {
							title: 'Insert Link',
							className: 'fa-light fa-link',
							func: 'createHTMLeditorLink',
							param: {
								target: event.target
							}
						},
						insertList: {
							title: '',
							className: 'fa-light fa-list-radio',
							selected: ['UL', 'OL', 'LI'].includes(tag) ? true : false,
							subs: {
								ul: {
									title: 'Unorder List (Bullet Points)',
									func: 'formatHTMLeditor',
									selected: $(event.target).parent().prop('tagName') === 'UL' || ['UL'].includes(tag) ? true : false,
									param: {
										element: "insertUnorderedList"
									}
								},
								ol: {
									title: 'Orderd List (Numbered)',
									func: 'formatHTMLeditor',
									selected: $(event.target).parent().prop('tagName') === 'OL' || ['OL'].includes(tag) ? true : false,

									param: {
										element: "insertorderedlist"
									}
								}

							}
						},
						insertTable: {
							title: 'Insert Table',
							className: 'fa-light fa-table',
							nodeName: 'TABLE',
							func: 'insertTable',
							param: {
								target: event.target
							}
						},
						htmlMode: {
							title: 'HTML Mode',
							className: 'fa-light fa-brackets-curly',
							func: 'HTMLeditorHTMLMode',
							param: {
								target: event.target
							}
						},
						clear: {
							title: 'Clear Format',
							className: 'fa-light fa-text-slash',
							func: 'formatHTMLeditor',
							param: {
								element: "removeformat"
							}
						}
					}, event, elements
					);
			break;
		case "selectedWord":
			new contextMenuWW(
					{
						bold: {
							title: 'Bold',
							className: 'fa-light fa-bold',
							nodeName: 'B',
							func: 'formatHTMLeditor',
							param: {
								element: "bold"
							}
						},
						italic: {
							title: 'Italic',
							className: 'fa-light fa-italic',
							func: 'formatHTMLeditor',
							param: {
								element: "italic"
							}
						},
						underlined: {
							title: 'Underlined',
							className: 'fa-light fa-underline',
							func: 'formatHTMLeditor',
							param: {
								element: "underline"
							}
						},
						headlines: {
							title: '',
							className: 'fa-light fa-heading',
							subs: {
								h1: {
									title: 'Headline 1',
									func: 'formatHTMLeditor',
									param: {
										element: "formatBlock",
										tag: "h1"
									}
								},
								h2: {
									title: 'Headline 2',
									func: 'formatHTMLeditor',
									param: {
										element: "formatBlock",
										tag: "h2"
									}
								},
								h3: {
									title: 'Headline 3',
									func: 'formatHTMLeditor',
									param: {
										element: "formatBlock",
										tag: "h3"
									}
								},
								h4: {
									title: 'Headline 4',
									func: 'formatHTMLeditor',
									param: {
										element: "formatBlock",
										tag: "h4"
									}
								},
								h5: {
									title: 'Headline 5',
									func: 'formatHTMLeditor',
									param: {
										element: "formatBlock",
										tag: "h5"
									}
								},
								h6: {
									title: 'Headline 6',
									func: 'formatHTMLeditor',
									param: {
										element: "formatBlock",
										tag: "h6"
									}
								}
							}
						},
						paragraph: {
							title: 'Paragraph',
							className: 'fa-light fa-paragraph',
							nodeName: 'P',
							func: 'formatHTMLeditor',
							param: {
								element: "formatBlock",
								tag: "p"
							}
						},
						insertAnchor: {
							title: 'Insert Link',
							className: 'fa-light fa-link',
							func: 'createHTMLeditorLink',
							param: {
								target: event.target
							}
						},
						insertList: {
							title: '',
							className: 'fa-light fa-list-radio',
							selected: ['UL', 'OL', 'LI'].includes(tag) ? true : false,
							subs: {
								ul: {
									title: 'Unorder List (Bullet Points)',
									func: 'formatHTMLeditor',
									selected: $(event.target).parent().prop('tagName') === 'UL' || ['UL'].includes(tag) ? true : false,
									param: {
										element: "insertUnorderedList"
									}
								},
								ol: {
									title: 'Orderd List (Numbered)',
									func: 'formatHTMLeditor',
									selected: $(event.target).parent().prop('tagName') === 'OL' || ['OL'].includes(tag) ? true : false,

									param: {
										element: "insertorderedlist"
									}
								}

							}
						},
						htmlMode: {
							title: 'HTML Mode',
							className: 'fa-light fa-brackets-curly',
							func: 'HTMLeditorHTMLMode',
							param: {
								target: event.target
							}
						},
						clear: {
							title: 'Clear Format',
							className: 'fa-light fa-text-slash',
							func: 'formatHTMLeditor',
							param: {
								element: "removeformat"
							}
						}
					}, event
					);
		case "selectedHeadlineWord":
			new contextMenuWW(
					{
						bold: {
							title: 'Bold',
							className: 'fa-light fa-bold',
							nodeName: 'B',
							func: 'formatHTMLeditor',
							param: {
								element: "bold"
							}
						},
						italic: {
							title: 'Italic',
							className: 'fa-light fa-italic',
							func: 'formatHTMLeditor',
							param: {
								element: "italic"
							}
						},
						underlined: {
							title: 'Underlined',
							className: 'fa-light fa-underline',
							func: 'formatHTMLeditor',
							param: {
								element: "underline"
							}
						},

						insertAnchor: {
							title: 'Insert Link',
							className: 'fa-light fa-link',
							func: 'createHTMLeditorLink',
							param: {
								target: event.target
							}
						},

						clear: {
							title: 'Clear Format',
							className: 'fa-light fa-text-slash',
							func: 'formatHTMLeditor',
							param: {
								element: "removeformat"
							}
						}
					}, event
					);
			break;
		case "notselected":
			new contextMenuWW(
					{
						insertAnchor: {
							title: 'Insert Link',
							className: 'fa-light fa-link',
							func: 'createHTMLeditorLink',
							param: {
								target: event.target
							}
						},
						insertList: {
							title: '',
							className: 'fa-light fa-list-radio',
							selected: ['UL', 'OL', 'LI'].includes(tag) ? true : false,
							subs: {
								ul: {
									title: 'Unorder List (Bullet Points)',
									func: 'formatHTMLeditor',
									selected: $(event.target).parent().prop('tagName') === 'UL' || ['UL'].includes(tag) ? true : false,
									param: {
										element: "insertUnorderedList"
									}
								},
								ol: {
									title: 'Orderd List (Numbered)',
									func: 'formatHTMLeditor',
									selected: $(event.target).parent().prop('tagName') === 'OL' || ['OL'].includes(tag) ? true : false,

									param: {
										element: "insertorderedlist"
									}
								}

							}
						},
						insertTable: {
							title: 'Insert Table',
							className: 'fa-light fa-table',
							nodeName: 'TABLE',
							func: 'insertTable',
							param: {
								target: event.target
							}
						},
						htmlMode: {
							title: 'HTML Mode',
							className: 'fa-light fa-brackets-curly',
							func: 'HTMLeditorHTMLMode',
							param: {
								target: event.target
							}
						}
					}, event
					);
			break;
		case "anchor":
			new contextMenuWW(
					{
						removeanchor: {
							title: 'remove Link',
							className: 'fa-light fa-link-slash',
							func: 'removeAnchor',
							param: {
								target: event.target
							}
						},
						editanchor: {
							title: 'Edit Link',
							className: 'fa-light fa-pen-to-square',
							func: 'createHTMLeditorLink',
							param: {
								target: event.target
							}
						},
						clear: {
							title: 'Clear Format',
							className: 'fa-light fa-text-slash',
							func: 'formatHTMLeditor',
							param: {
								element: "removeformat"
							}
						}
					}, event, obj
					);
			break;
	}
	return false;
}
