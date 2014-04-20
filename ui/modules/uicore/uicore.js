function uiCoreDeleteImage(id) {
	$.post(window.location.href, {__submit_form_id: 'delete_image', image_id: id}, function(data) {
		if (data=='OK') $("#image_field_" + id).remove();
		else alert(data);
	});
}
function uiCoreAddVideo() {
	var url = $("#addvideo").val();
	var videodesc = $("#addvideodesc").val();
	if (url.trim()=="") {
		alert("Не указан адрес на YouTube");
		return false;
	}
	$.post(window.location, {__submit_form_id: 'add_video', youtube_url: url, video_desc: videodesc}, function(data) {
		$("#addvideo").val('');
		$("#addvideodesc").val('');
		$("#videos").html(data);

	});
}

function uiCoreDeleteVideo(video_id) {
	$.post(window.location, {__submit_form_id: 'delete_video', video_id: video_id}, function(data) {
		$("#videos").html(data);
	});
}

function uiCore_deleteObject(callback_name, warning_text, redirect_url) {
	if (confirm(warning_text)) {
		$.post(window.location, {__submit_form_id: callback_name}, function(data) {
			if (data==='OK') window.location=redirect_url;
			else alert(data);
		});
	}
}


function switchSlide(slider_id, id) {
	$("#" + slider_id + " .slide").removeClass('active');
	$("#" + slider_id + " #slide_" + id).addClass('active');
	$("#" + slider_id + " .slider_thumb").removeClass('active');
	$("#" + slider_id + " #slider_thumb_" + id).addClass('active');
}