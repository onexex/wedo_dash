/* ==========================================================================
   corner-polls.js — Corner polls (corner.php). Talks to query/corner-polls.php,
   which answers every change with the poll's re-rendered card.
   Config: window.WD_POLLS = { api, token }.
   ========================================================================== */
$(function () {
    var cfg = window.WD_POLLS;
    if (!cfg) { return; }
    var $root = $("#cornerPolls");
    var busy = 0, toastTimer = null;
    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    function post(action, data) {
        busy++;
        return $.ajax({ url: cfg.api, type: "POST", dataType: "json",
                        data: $.extend({ action: action, token: cfg.token }, data) })
            .always(function () { busy--; });
    }
    function errMsg(x) {
        return (x && x.responseJSON && x.responseJSON.msg) || "Couldn’t reach the server. Check your connection and try again.";
    }
    function toast(msg, isError) {
        var $t = $("#cpToast");
        $t.text(msg).toggleClass("is-error", !!isError).addClass("is-show");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { $t.removeClass("is-show"); }, 3500);
    }

    /* bars grow from where they were (or from 0 the first time results show) */
    function growBars($card, from) {
        $card.find(".cp__bar").each(function () {
            var target = this.getAttribute("data-w") + "%";
            if (reduceMotion) { this.style.width = target; return; }
            var start = from ? (from[$(this).closest(".cp__opt").attr("data-option")] || "0%") : "0%";
            this.style.width = start;
        });
        void ($card[0] && $card[0].offsetWidth);
        $card.find(".cp__bar").each(function () { this.style.width = this.getAttribute("data-w") + "%"; });
    }

    function replaceCard($old, html, isNew) {
        if (!html) {
            $old.slideUp(200, function () { $(this).remove(); syncEmpty(); });
            return;
        }
        var from = {};
        if ($old.hasClass("is-revealed")) {
            $old.find(".cp__opt").each(function () { from[$(this).attr("data-option")] = $(this).find(".cp__bar")[0].style.width; });
        }
        var $new = $(html);
        if (isNew) { $new.addClass("is-new"); }
        $old.replaceWith($new);
        growBars($new, from);
        return $new;
    }

    function syncEmpty() {
        $("#cpEmpty").prop("hidden", $("#cpOpen .cp").length > 0);
    }

    // first paint: results already visible animate in
    $root.find(".cp.is-revealed").each(function () { growBars($(this), null); });

    /* ---------- vote ---------- */
    $root.on("click", ".cp__opt", function () {
        var $opt = $(this), $card = $opt.closest(".cp");
        if ($opt.prop("disabled") || $card.find(".cp__opt.is-pending").length) { return; }
        var multi = $opt.find(".cp__mark--box").length > 0;
        // optimistic tick so the tap feels instant; the server's card replaces it
        if (!multi && !$opt.hasClass("is-mine")) { $card.find(".cp__opt").removeClass("is-mine").attr("aria-pressed", "false"); }
        $opt.toggleClass("is-mine").attr("aria-pressed", $opt.hasClass("is-mine") ? "true" : "false").addClass("is-pending");

        post("vote", { id: $card.attr("data-poll"), option: $opt.attr("data-option") })
            .done(function (r) { replaceCard($card, r.html); })
            .fail(function (x) {
                var r = x.responseJSON;
                if (r && r.html !== undefined) { replaceCard($card, r.html); }
                else { $opt.removeClass("is-pending").toggleClass("is-mine"); }
                toast(errMsg(x), true);
            });
    });

    /* ---------- suggest an option ---------- */
    $root.on("input", ".cp__add input", function () {
        $(this).closest(".cp__add").toggleClass("has-text", $.trim(this.value) !== "");
    });
    $root.on("submit", ".cp__add", function (e) {
        e.preventDefault();
        var $form = $(this), $card = $form.closest(".cp"), label = $.trim($form.find("input").val());
        if (!label) { return; }
        $form.find("input, button").prop("disabled", true);
        post("add_option", { id: $card.attr("data-poll"), label: label })
            .done(function (r) { replaceCard($card, r.html); toast("Added “" + label + "” with your vote."); })
            .fail(function (x) {
                $form.find("input, button").prop("disabled", false);
                toast(errMsg(x), true);
            });
    });

    /* ---------- author menu: close / delete ---------- */
    function closeMenus() {
        $root.find(".cp__menulist").prop("hidden", true);
        $root.find(".cp__menubtn").attr("aria-expanded", "false");
        $root.find(".cp__menulist .is-confirm").removeClass("is-confirm").html('<i class="fa-solid fa-trash-can"></i> Delete poll');
    }
    $root.on("click", ".cp__menubtn", function (e) {
        e.stopPropagation();
        var $list = $(this).next(".cp__menulist"), open = $list.prop("hidden");
        closeMenus();
        $list.prop("hidden", !open);
        $(this).attr("aria-expanded", open ? "true" : "false");
    });
    $(document).on("click", function (e) { if (!$(e.target).closest(".cp__menu").length) { closeMenus(); } });
    $(document).on("keydown", function (e) { if (e.key === "Escape") { closeMenus(); } });

    $root.on("click", ".cp__menulist button", function (e) {
        e.stopPropagation();
        var $b = $(this), act = $b.attr("data-act"), $card = $b.closest(".cp");
        if (act === "delete" && !$b.hasClass("is-confirm")) {
            $b.addClass("is-confirm").html('<i class="fa-solid fa-triangle-exclamation"></i> Click again to delete');
            return;
        }
        closeMenus();
        post(act, { id: $card.attr("data-poll") })
            .done(function (r) {
                replaceCard($card, r.html);
                toast(act === "delete" ? "Poll deleted." : "Voting closed. Results are final.");
            })
            .fail(function (x) { toast(errMsg(x), true); });
    });

    /* ---------- live results ---------- */
    function refresh() {
        if (busy || document.visibilityState !== "visible" || $("#pollModal").hasClass("in")) { return; }
        if ($(document.activeElement).closest(".cp__add").length) { return; }
        var ids = $root.find(".cp").map(function () { return $(this).attr("data-poll"); }).get();
        if (!ids.length) { return; }
        $.getJSON(cfg.api, { action: "cards", ids: ids.join(",") }).done(function (r) {
            if (busy || !r || !r.cards) { return; }
            $.each(r.cards, function (id, html) {
                var $card = $root.find('.cp[data-poll="' + id + '"]');
                if (!$card.length) { return; }
                var sig = html ? (html.match(/data-sig="([^"]+)"/) || [])[1] : "";
                if (sig !== $card.attr("data-sig")) { replaceCard($card, html); }
            });
        });
    }
    setInterval(refresh, 20000);
    document.addEventListener("visibilitychange", function () { if (document.visibilityState === "visible") { refresh(); } });

    /* ======================= create dialog ======================= */
    var $m = $("#pollModal");
    if (!$m.length) { return; }
    var MAX = 10;
    var TEMPLATES = {
        lunch:  { q: "What should we have for lunch?", o: ["🍕 Pizza", "🍜 Ramen", "🍗 Fried chicken", "🥗 Salad bowls"], closes: "1d", add: true },
        outing: { q: "Where should we go for the team outing?", o: ["🏖️ Beach resort", "⛰️ Mountain getaway", "🎳 Bowling & dinner", "🎤 Karaoke night"], closes: "7d", add: true },
        yesno:  { q: "", o: ["👍 Yes", "👎 No"], closes: "3d" },
        day:    { q: "Which day works best?", o: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"], closes: "3d", multi: true }
    };

    function optRow(val) {
        return $('<div class="pm__opt"><span></span>'
               + '<input type="text" class="wd-input" maxlength="120" placeholder="Option">'
               + '<button type="button" class="pm__rm" aria-label="Remove option"><i class="fa-solid fa-xmark"></i></button></div>')
               .find("input").val(val || "").end();
    }
    function renumber() {
        var $rows = $m.find(".pm__opt");
        $rows.each(function (i) {
            $(this).find("span").text(i + 1);
            $(this).find("input").attr("placeholder", i < 2 ? "Option " + (i + 1) : "Option " + (i + 1) + " (optional)");
        });
        $m.find(".pm__rm").prop("disabled", $rows.length <= 2);
        $m.find(".pm__addopt").prop("disabled", $rows.length >= MAX);
    }
    function setOptions(list) {
        var $box = $m.find(".pm__opts").empty();
        $.each(list.length >= 2 ? list : ["", ""], function (i, v) { $box.append(optRow(v)); });
        renumber();
    }
    function reset() {
        $m.find("form")[0].reset();
        $m.find("#pmDetailsWrap").prop("hidden", true);
        $m.find(".pm__more").prop("hidden", false);
        $m.find(".pm__custom").prop("hidden", true);
        $m.find(".pm__err").prop("hidden", true);
        $m.find(".pm__count").text("0/200");
        setOptions([]);
    }
    reset();

    $m.on("shown.bs.modal", function () { $("#pmQuestion").focus(); });
    $m.on("hidden.bs.modal", reset);

    $m.on("click", ".pm__chip", function () {
        var t = TEMPLATES[$(this).attr("data-tpl")];
        if (t.q || !$.trim($("#pmQuestion").val())) { $("#pmQuestion").val(t.q).trigger("input"); }
        setOptions(t.o.slice());
        $m.find('input[name="closes"][value="' + t.closes + '"]').prop("checked", true).trigger("change");
        $("#pmMulti").prop("checked", !!t.multi);
        $("#pmAllowAdd").prop("checked", !!t.add);
        (t.q ? $m.find(".pm__opt input").first() : $("#pmQuestion")).focus();
    });
    $("#pmQuestion").on("input", function () { $m.find(".pm__count").text(this.value.length + "/200"); });
    $m.on("click", ".pm__more", function () {
        $(this).prop("hidden", true);
        $("#pmDetailsWrap").prop("hidden", false).find("textarea").focus();
    });
    $m.on("click", ".pm__addopt", function () {
        if ($m.find(".pm__opt").length >= MAX) { return; }
        var $r = optRow("");
        $m.find(".pm__opts").append($r);
        renumber();
        $r.find("input").focus();
    });
    $m.on("click", ".pm__rm", function () {
        $(this).closest(".pm__opt").remove();
        renumber();
    });
    // Enter in an option jumps to the next one (adding a row at the end)
    $m.on("keydown", ".pm__opt input", function (e) {
        if (e.key !== "Enter") { return; }
        e.preventDefault();
        var $next = $(this).closest(".pm__opt").next(".pm__opt");
        if ($next.length) { $next.find("input").focus(); } else { $m.find(".pm__addopt").trigger("click"); }
    });
    $m.on("change", 'input[name="closes"]', function () {
        var custom = this.value === "custom" && this.checked;
        $m.find(".pm__custom").prop("hidden", !custom);
        if (custom) {
            var d = new Date(Date.now() + 86400000 - new Date().getTimezoneOffset() * 60000);
            var $in = $("#pmClosesAt").attr("min", new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16));
            if (!$in.val()) { $in.val(d.toISOString().slice(0, 14) + "00"); }
            $in.focus();
        }
    });

    $m.on("submit", "form", function (e) {
        e.preventDefault();
        var $err = $m.find(".pm__err").prop("hidden", true);
        var options = $m.find(".pm__opt input").map(function () { return $.trim(this.value); }).get().filter(Boolean);
        var q = $.trim($("#pmQuestion").val());
        var problem = q.length < 3 ? "Write your question first." : options.length < 2 ? "Add at least 2 options." : "";
        if (problem) { $err.text(problem).prop("hidden", false); return; }

        var $btn = $m.find(".pm__submit").prop("disabled", true).html('<i class="fa-solid fa-spinner fa-spin"></i> Posting…');
        post("create", {
            question: q, details: $.trim($("#pmDetails").val()), options: options,
            multi: $("#pmMulti").is(":checked") ? 1 : 0, allow_add: $("#pmAllowAdd").is(":checked") ? 1 : 0,
            anonymous: $("#pmAnon").is(":checked") ? 1 : 0,
            closes: $m.find('input[name="closes"]:checked').val(), closes_at: $("#pmClosesAt").val()
        }).done(function (r) {
            var $card = $(r.html).addClass("is-new");
            $("#cpOpen").prepend($card);
            syncEmpty();
            $m.modal("hide");
            toast("Poll posted. Everyone can vote now.");
            $card[0].scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "nearest" });
        }).fail(function (x) {
            $err.text(errMsg(x)).prop("hidden", false);
        }).always(function () {
            $btn.prop("disabled", false).html('<i class="fa-solid fa-paper-plane"></i> Post poll');
        });
    });
});
