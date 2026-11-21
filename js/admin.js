jQuery(function($) {
    function getActiveEditor() {
        return window.tinymce && tinymce.activeEditor ? tinymce.activeEditor : null;
    }

    function centerModal() {
        var $modal = $("#pz-talk-modal");
        var left = ($(window).width() - $modal.outerWidth()) / 2;
        var top = ($(window).height() - $modal.outerHeight()) / 2;

        $modal.css({ left: left + "px", top: top + "px" });
    }

    function closeModal() {
        $("#pz-talk-overlay, #pz-talk-modal").hide();
        $("#pz-talk-serif").val("");
        $("#pz-talk-check").prop("checked", false);
    }

    $("#pz-talk-open").off("click.pzTalk").on("click.pzTalk", function() {
        var editor = getActiveEditor();
        var selectedContent = editor ? editor.selection.getContent() : "";
        var nameMatch = selectedContent.match(/name="([^"]*)"/);
        var iconMatch = selectedContent.match(/icon="([^"]*)"/);
        var selectedName = nameMatch ? nameMatch[1] : "";

        $("#pz-talk-overlay, #pz-talk-modal").show();

        if (selectedName) {
            $("#pz-talk-name, #pz-talk-icon").val(selectedName);
        }

        if (iconMatch && iconMatch[1] !== selectedName) {
            $("#pz-talk-check").prop("checked", true);
            $("#pz-talk-icon").prop("disabled", false).val(iconMatch[1]);
        }

        selectedContent = selectedContent
            .replace(/\[[^\]]*\]/g, "")
            .replace(/<\/*p>/g, "");

        $("#pz-talk-serif").val(selectedContent).trigger("focus");
        centerModal();
    });

    $("#pz-talk-overlay, #pz-talk-close").off("click.pzTalk").on("click.pzTalk", function(event) {
        event.preventDefault();
        closeModal();

        var editor = getActiveEditor();
        if (editor) {
            editor.focus();
        }
    });

    $(document).on("keydown.pzTalk", function(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });

    $("#pz-talk-serif").on("keydown.pzTalk", function(event) {
        if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
            return;
        }

        var $names = $("#pz-talk-name");
        var index = $names.prop("selectedIndex");
        var lastIndex = $names.children().length - 1;

        index += event.key === "ArrowDown" ? 1 : -1;
        index = Math.max(0, Math.min(lastIndex, index));
        $names.prop("selectedIndex", index);
        $("#pz-talk-icon").prop("selectedIndex", index);
        event.preventDefault();
    });

    $("#pz-talk-name").on("change.pzTalk", function() {
        $("#pz-talk-icon").val($(this).val());
    });

    $("#pz-talk-insert").off("click.pzTalk").on("click.pzTalk", function(event) {
        event.preventDefault();

        var editor = getActiveEditor();
        if (!editor) {
            return;
        }

        var code = $("#pz-talk-code").val();
        var name = $("#pz-talk-name").val();
        var icon = $("#pz-talk-icon").val();
        var shortcode = "<p>[" + code + " name=\"" + name + "\" ";

        if (name !== icon) {
            shortcode += "icon=\"" + icon + "\" ";
        }

        shortcode += "]" + $("#pz-talk-serif").val() + "[/" + code + "]</p>";
        editor.selection.setContent(shortcode);
        closeModal();
        editor.focus();
    });

    $("#pz-talk-check").on("change.pzTalk", function() {
        $("#pz-talk-icon").prop("disabled", !this.checked);
    });

    $(window).on("resize.pzTalk", centerModal);
});
