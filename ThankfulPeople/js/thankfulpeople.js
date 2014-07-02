$(document).ready(function(){
	//// http://plugins.learningjquery.com/expander/index.html#options
	//$('div.ThankedByBox').expander({
	//	slicePoint: 200,
	//	expandText: gdn.definition('ExpandThankList'),
	//	userCollapse: false,
	//	userCollapseText: gdn.definition('CollapseThankList')
	//});
	//$('div.ThankedByBox span.details > a:last').addClass('Last');
	//
	//$('span.Thank > a, span.UnThank > a').live('click', function(){
	//	var box, url = this.href, parent = $(this).parent()
	//	var item = $(this).parents('ul.MessageList > li'); // TODO: add ul.DataList to collection
	//	$(this).after('<span class="TinyProgress">&#160;</span>');
	//
	//	$.ajax({
	//		type: "POST",
	//		url: url,
	//		data: 'DeliveryType=DATA&DeliveryMethod=JSON',
	//		dataType: 'json',
	//		error: function(XMLHttpRequest, textStatus, errorThrown) {
	//			$.popup({}, XMLHttpRequest.responseText);
	//		},
	//		success: function(Data) {
	//			parent.fadeOut('fast');
	//			box = item.find('div.ThankedByBox').first();
	//			if (box.length == 0) { // Nobody say thanks for this message, create an empty box and insert it after message (AfterCommentBody event)
	//				box = $('<div>', {'class':'ThankedByBox'});
	//				item.find('div.Message').after(box);
	//			}
	//			box.html(Data.NewThankedByBox);
	//			if (typeof $.fn.effect == 'function') box.effect("highlight", {}, "slow");
	//		},
	//		complete: function(){
	//			$('.TinyProgress', item).remove();
	//		}
	//	});
	//	return false;

	/**
	 * Updates the amount of received thanks displayed for a given object.
	 *
	 * @param string ObjectSignature The unique identifier of the object.
	 * @param int Value The total amount of thanks received by the object.
	 */
	function UpdateObjectThanksCount(ObjectSignature, Value) {
		var Text = gdn.definition('ThankfulPeople_ThanksWidget_ObjectThanksCount', 'Received thanks:') + ' ' + Value;
		$('#' + ObjectSignature).find('.ReceivedThanks > span').text(Text);
	}

	$('.SayThanks').delegate('form', 'submit', function(event){
		event.stopPropagation();
		var $Form = $(this);

		// Disable
		var $SubmitButton = $Form.find(':submit').first();
		var $SubmitButtonLabel = $SubmitButton.val();
		$SubmitButton
			.attr('disabled', true)
			.val(gdn.definition('ThankfulPeople_ThanksModule_Thanking', 'Thanking...'));

		var PostValues = $Form.serialize();
		PostValues  += '&DeliveryMethod=JSON&DeliveryType=DATA';

		$.ajax({
			type: 'post',
			url: $Form.attr('action'),
			global: false,
			data: PostValues,
			dataType: 'json',
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				 $('div.Popup').remove();
				 // XMLHttpRequest.responseText contains the whole JSON data object, in
				 // case of need
				 $.popup({}, errorThrown);
				 //console.log(errorThrown);

				 // On failure, restore button's original label
				 $SubmitButton
					.attr('disabled', false)
					.val($SubmitButtonLabel);
			},
			success: function(json){
				json = $.postParseJson(json);
				$FormContainer = $Form.parents('.Actions').first();

				$FormContainer.find('.SayThanks').hide();
				$FormContainer.find('.Thanked').show();

				UpdateObjectThanksCount($Form.attr('object-signature'), json.ResultData.ObjectThanksCount);
			},
			complete: function(XMLHttpRequest, textStatus) {
				//RefreshingDiscussions = false;
			}
		});

		return false;
	});
	//});
});
