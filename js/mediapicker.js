(function ($) {



	$(document).on("click", "input:button[name=mediaselect], #pz-talk-persons img[id^=img-]", function(e) {
		var sid = this.id;
		sid = sid.slice(sid.indexOf('-', 1));

	    e.preventDefault();

		var language = (window.navigator.userLanguage || window.navigator.language || window.navigator.browserLanguage).substr(0,2) == "ja" ? "ja" : "en";
		if (language == "ja") {
			var msg = "画像を選択";
		} else {
			var msg = "Choose Image";
		}

		function openPicker(attachment) {
		var custom_uploader = wp.media({
		    title: msg,
		    state: 'library',
		    content: 'browse',
		    library: { type: "image" },		/* 画像のみ */
		    button: { text: msg },
		    multiple: false					/* 複数選択なし */
		});
		custom_uploader.on("select", function() {
			var images = custom_uploader.state().get("selection");
			images.each(function(file){
				document.getElementById("url"+sid).value = "";					// クリア
				document.getElementById("url"+sid).value = file.toJSON().url;		// 画像URL
				document.getElementById("img"+sid).src = "";
				document.getElementById("img"+sid).src = file.toJSON().url;
				document.getElementById("url"+sid).dispatchEvent(new Event("input", { bubbles: true }));
			});
		});

		custom_uploader.on("open", function() {
			custom_uploader.content.mode("browse");
			var state = custom_uploader.state("library");
			var selection = state.get("selection");
			selection.reset(attachment ? [attachment] : []);
			if (attachment) {
				// Include older images even when the first library page does not contain them.
				state.get("library").add(attachment, { at: 0 });
				selection.single(attachment);
			}
		});
		custom_uploader.open();
		custom_uploader.content.mode('browse');
		}

		var url = document.getElementById("url" + sid).value.trim();
		if (!url || !window.pzTalkMedia) { openPicker(null); return; }
		$.post(ajaxurl, {
			action: "pz_talk_image_id",
			nonce: pzTalkMedia.nonce,
			url: url
		}).done(function(response) {
			if (!response.success || !response.data.id) { openPicker(null); return; }
			var attachment = wp.media.attachment(response.data.id);
			attachment.fetch().done(function() {
				openPicker(attachment);
			}).fail(function() { openPicker(null); });
		}).fail(function() { openPicker(null); });
	});

	$(document).on("keydown", "#pz-talk-persons img[id^=img-]", function(e) {
		if (e.key === "Enter" || e.key === " ") {
			e.preventDefault();
			$(this).trigger("click");
		}
	});

})(jQuery);
