/* ==========================================================================
   corner-feed.js — Corner announcements feed (corner.php).
   Talks to query/corner-feed.php. Config: window.WD_FEED = { api, token }.
   ========================================================================== */
$(function () {
    var cfg = window.WD_FEED;
    var $feed = $("#cornerFeed"), $list = $("#cfList");
    if (!cfg || !$feed.length) { return; }
    var filter = "all", toastTimer = null;

    function post(action, data) {
        return $.ajax({ url: cfg.api, type: "POST", dataType: "json",
                        data: $.extend({ action: action, token: cfg.token }, data) });
    }
    function errMsg(x) {
        return (x && x.responseJSON && x.responseJSON.msg) || "Couldn’t reach the server. Check your connection and try again.";
    }
    function toast(msg, isError) {
        var $t = $("#cfToast");
        $t.text(msg).toggleClass("is-error", !!isError).addClass("is-show");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { $t.removeClass("is-show"); }, 3500);
    }

    /* long posts collapse to 6 lines with "See more" */
    function clamp($scope) {
        $scope.find(".cf__body").each(function () {
            var $b = $(this), $more = $b.siblings(".cf__more");
            if ($b.data("open")) { return; }
            $b.addClass("is-clamped");
            $more.prop("hidden", this.scrollHeight <= this.clientHeight + 2);
        });
    }
    $feed.on("click", ".cf__more", function () {
        var $b = $(this).siblings(".cf__body"), open = !$b.data("open");
        $b.data("open", open).toggleClass("is-clamped", !open);
        $(this).text(open ? "See less" : "See more");
    });

    /* All / News / Birthdays */
    function applyFilter() {
        var shown = 0;
        $list.children(".cf").each(function () {
            var ok = filter === "all" || $(this).attr("data-kind") === filter;
            $(this).toggleClass("is-hidden", !ok);
            if (ok) { shown++; }
        });
        $("#cfEmpty").prop("hidden", $list.children(".cf").length > 0);
        $("#cfFilterEmpty").prop("hidden", shown > 0 || !$list.children(".cf").length);
    }
    $feed.on("click", ".cf-filters button", function () {
        filter = $(this).attr("data-filter");
        $feed.find(".cf-filters button").attr("aria-pressed", "false");
        $(this).attr("aria-pressed", "true");
        applyFilter();
    });

    function insert(html, where) {
        var $new = $(html).addClass("is-in");
        where === "top" ? $list.prepend($new) : $list.append($new);
        clamp($new);
        applyFilter();
        return $new;
    }

    clamp($list);
    $(window).on("load", function () { clamp($list); });   // avatars / fonts may change line breaks

    /* ---------- composer ---------- */
    var $c = $("#cfCompose");
    if ($c.length) {
        var $body = $c.find(".cf-compose__body"), $title = $c.find(".cf-compose__title");
        var $count = $c.find(".cf-compose__count"), $btn = $c.find(".cf-compose__post");

        var autosize = function () { $body[0].style.height = "auto"; $body[0].style.height = $body[0].scrollHeight + "px"; };
        var sync = function () {
            var n = $body.val().length;
            $count.text(n + "/1000").toggleClass("is-near", n > 900);
            $btn.prop("disabled", $.trim($body.val()).length < 6);
            autosize();
        };
        var close = function () {
            $c.removeClass("is-open");
            $body.val(""); $title.val("");
            sync();
        };
        $body.on("focus", function () { $c.addClass("is-open"); });
        $body.on("input", sync);
        $c.on("click", ".cf-compose__cancel", function () { close(); $body.blur(); });
        $c.on("keydown", function (e) {
            if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); if (!$btn.prop("disabled")) { $c.trigger("submit"); } }
            if (e.key === "Escape" && !$.trim($body.val()) && !$.trim($title.val())) { close(); $body.blur(); }
        });
        $c.on("submit", function (e) {
            e.preventDefault();
            if ($btn.prop("disabled")) { return; }
            $btn.prop("disabled", true).html('<i class="fa-solid fa-spinner fa-spin"></i> Posting…');
            post("post", { body: $body.val(), title: $title.val() })
                .done(function (r) {
                    if (filter === "bday") { $feed.find('.cf-filters button[data-filter="all"]').trigger("click"); }
                    insert(r.html, "top");
                    close();
                    $body.blur();
                    toast("Posted. Everyone can see it now.");
                })
                .fail(function (x) { toast(errMsg(x), true); sync(); })
                .always(function () { $btn.html('<i class="fa-solid fa-paper-plane"></i> Post'); sync(); });
        });
        sync();
    }

    /* ---------- post menu: edit / delete ---------- */
    function closeMenus() {
        $feed.find(".cf__menulist").prop("hidden", true);
        $feed.find(".cf__menubtn").attr("aria-expanded", "false");
        $feed.find(".cf__menulist .is-confirm").removeClass("is-confirm").html('<i class="fa-solid fa-trash-can"></i> Delete');
    }
    $feed.on("click", ".cf__menubtn", function (e) {
        e.stopPropagation();
        var $l = $(this).next(".cf__menulist"), open = $l.prop("hidden");
        closeMenus();
        $l.prop("hidden", !open);
        $(this).attr("aria-expanded", open ? "true" : "false");
    });
    $(document).on("click", function (e) { if (!$(e.target).closest(".cf__menu").length) { closeMenus(); } });
    $(document).on("keydown", function (e) { if (e.key === "Escape") { closeMenus(); } });

    $feed.on("click", ".cf__menulist button", function (e) {
        e.stopPropagation();
        var $b = $(this), $card = $b.closest(".cf"), aid = $card.attr("data-aid");
        if ($b.attr("data-act") === "delete") {
            if (!$b.hasClass("is-confirm")) {
                $b.addClass("is-confirm").html('<i class="fa-solid fa-triangle-exclamation"></i> Click again to delete');
                return;
            }
            closeMenus();
            post("delete", { aid: aid })
                .done(function () {
                    $card.slideUp(180, function () { $(this).remove(); applyFilter(); });
                    toast("Post deleted.");
                })
                .fail(function (x) { toast(errMsg(x), true); });
            return;
        }
        // edit in place
        closeMenus();
        if ($card.find(".cf__edit").length) { return; }
        var $main = $card.find(".cf__main");
        $main.find(".cf__body, .cf__more").prop("hidden", true);
        var $ed = $('<div class="cf__edit"><textarea maxlength="1000" aria-label="Edit announcement"></textarea>'
                  + '<div class="cf__editbar"><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-ed="cancel">Cancel</button>'
                  + '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-ed="save"><i class="fa-solid fa-check"></i> Save</button></div></div>');
        $ed.find("textarea").val($main.find(".cf__src").val());
        $main.append($ed);
        var ta = $ed.find("textarea").focus()[0];
        ta.setSelectionRange(ta.value.length, ta.value.length);
    });
    $feed.on("click", ".cf__edit [data-ed]", function () {
        var $card = $(this).closest(".cf"), $ed = $card.find(".cf__edit");
        if ($(this).attr("data-ed") === "cancel") {
            $ed.remove();
            $card.find(".cf__body").prop("hidden", false);
            clamp($card);
            return;
        }
        var $save = $(this).prop("disabled", true);
        post("edit", { aid: $card.attr("data-aid"), body: $ed.find("textarea").val() })
            .done(function (r) {
                var $new = $(r.html);
                $card.replaceWith($new);
                clamp($new);
                applyFilter();
                toast("Changes saved.");
            })
            .fail(function (x) { $save.prop("disabled", false); toast(errMsg(x), true); });
    });
    $feed.on("keydown", ".cf__edit textarea", function (e) {
        if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); $(this).closest(".cf__edit").find('[data-ed="save"]').trigger("click"); }
        if (e.key === "Escape") { $(this).closest(".cf__edit").find('[data-ed="cancel"]').trigger("click"); }
    });

    /* ---------- "Vote now" on a poll's announcement (also corner#poll-N from Notifications) ---------- */
    function goToPoll(id) {
        var $p = $("#poll-" + id);
        if (!$p.length) { toast("This poll was removed.", true); return; }
        $p.closest("details").prop("open", true);
        $p[0].scrollIntoView({ behavior: "smooth", block: "center" });
        $p.removeClass("is-spot");
        void $p[0].offsetWidth;
        $p.addClass("is-spot");
    }
    $feed.on("click", ".cf-poll__go", function (e) {
        e.preventDefault();
        goToPoll($(this).attr("data-poll"));
    });
    var hash = /^#poll-(\d+)$/.exec(location.hash);
    if (hash) { setTimeout(function () { goToPoll(hash[1]); }, 150); }

    /* ---------- older posts ---------- */
    $feed.on("click", "#cfOlder", function () {
        var $b = $(this).prop("disabled", true);
        $.getJSON(cfg.api, { action: "more", before: $b.attr("data-before") })
            .done(function (r) {
                insert(r.html, "bottom");
                var last = $list.children(".cf").last().attr("data-aid");
                if (r.more && last) { $b.attr("data-before", last).prop("disabled", false); } else { $b.remove(); }
            })
            .fail(function (x) { $b.prop("disabled", false); toast(errMsg(x), true); });
    });
});
