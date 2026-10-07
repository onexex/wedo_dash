<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login'); exit; }
  date_default_timezone_set("Asia/Manila");
  require_once 'includes/messages-lib.php';
  $msgWith = trim((string) ($_GET['with'] ?? ''));
  $msgVer  = @filemtime(__DIR__ . '/assets/js/script-message.js') ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : htmlspecialchars($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (Bootstrap modals for the shell's change-password dialog) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/script-message.js?v=<?php echo $msgVer; ?>" defer></script>

    <style type="text/css">
      .msg-app{display:grid;grid-template-columns:340px 1fr;height:calc(100vh - var(--topbar-h) - 140px);min-height:460px;margin:18px 0 0;overflow:hidden}
      .msg-app [hidden]{display:none !important}   /* the display:flex rules below must not un-hide panels */

      /* ---------------------------------------------------------------- side: search, tabs, list */
      .msg-side{display:flex;flex-direction:column;min-height:0;border-right:1px solid var(--border);background:var(--surface)}
      .msg-side__head{padding:14px 14px 10px;display:flex;flex-direction:column;gap:10px}
      .msg-search{position:relative}
      .msg-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:13px}
      .msg-search .wd-input{padding:9px 52px 9px 34px;font-size:13px;border-radius:var(--radius-pill);background:var(--surface-2);border-color:transparent}
      .msg-search .wd-input:focus{background:var(--surface);border-color:var(--brand)}
      .msg-side__row{display:flex;gap:8px;align-items:center}
      .msg-side__row .msg-search{flex:1}
      .msg-newgrp{position:relative;flex:none;width:38px;height:38px;border:0;border-radius:50%;background:var(--brand-tint);color:var(--brand-700);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s}
      .msg-newgrp:hover{background:var(--brand);color:#fff}
      .msg-newgrp__plus{position:absolute;right:3px;bottom:4px;font-size:9px}
      .msg-kbd{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:10.5px;color:var(--text-3);border:1px solid var(--border-2);border-radius:5px;padding:1px 5px;background:var(--surface);pointer-events:none}
      .msg-search .wd-input:focus + .msg-kbd{display:none}
      /* who's online now: horizontal strip of faces */
      .msg-online{display:flex;flex-direction:column;gap:6px;margin:0 -14px;padding:2px 14px 4px;border-bottom:1px solid var(--border)}
      .msg-online__label{display:flex;align-items:center;gap:6px;font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3)}
      .msg-online__dot{width:7px;height:7px;border-radius:50%;background:#22c55e}
      .msg-online__n{background:#dcfce7;color:#166534;border-radius:var(--radius-pill);padding:0 7px;font-size:10.5px;letter-spacing:0}
      .msg-online__row{display:flex;gap:4px;overflow-x:auto;padding-bottom:6px;scrollbar-width:thin}
      .msg-online__none{font-size:12px;color:var(--text-3);padding:2px 0 6px}
      .msg-face{flex:none;width:58px;border:0;background:none;padding:4px 2px;border-radius:12px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;color:var(--text)}
      .msg-face:hover,.msg-face:focus-visible{background:var(--surface-2);outline:none}
      .msg-face__name{font-size:11px;font-weight:600;max-width:54px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .msg-tabs{display:flex;gap:6px}
      .msg-tab{border:0;background:var(--surface-2);color:var(--text-2);font-size:12px;font-weight:600;padding:5px 12px;border-radius:var(--radius-pill);cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .15s,color .15s}
      .msg-tab:hover{color:var(--text)}
      .msg-tab.is-on{background:var(--brand-tint);color:var(--brand-700)}
      .msg-tab__n{background:var(--brand);color:#fff;border-radius:var(--radius-pill);font-size:10px;min-width:17px;height:17px;padding:0 5px;display:inline-flex;align-items:center;justify-content:center}
      .msg-tab__n:empty{display:none}

      .msg-side__body{flex:1;overflow-y:auto;min-height:0;padding:0 8px 10px}
      .msg-label{padding:12px 10px 6px;font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3)}
      .msg-item{display:flex;align-items:center;gap:11px;width:100%;padding:10px 10px;border:0;border-radius:12px;background:none;text-align:left;cursor:pointer;color:var(--text);transition:background .12s}
      .msg-item:hover,.msg-item:focus-visible{background:var(--surface-2);outline:none}
      .msg-item.is-active{background:var(--brand-tint)}
      .msg-item__main{flex:1;min-width:0}
      .msg-item__top{display:flex;align-items:baseline;gap:8px}
      .msg-item__name{flex:1;min-width:0;font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .msg-item__time{font-size:11px;color:var(--text-3);white-space:nowrap}
      .msg-item__bottom{display:flex;align-items:center;gap:8px;margin-top:2px}
      .msg-item__preview{flex:1;min-width:0;font-size:12.5px;color:var(--text-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .msg-item__preview.is-typing{color:var(--brand-700);font-style:italic}
      .msg-item.is-unread .msg-item__name,.msg-item.is-unread .msg-item__preview{color:var(--text);font-weight:700}
      .msg-item.is-unread .msg-item__time{color:var(--brand-700);font-weight:700}
      .msg-item__badge{min-width:19px;height:19px;padding:0 6px;border-radius:var(--radius-pill);background:var(--brand);color:#fff;font-size:10.5px;font-weight:700;display:flex;align-items:center;justify-content:center}
      .msg-item mark{background:#fff3b0;color:inherit;padding:0;border-radius:2px}

      /* ---------------------------------------------------------------- groups */
      .msg-av.msg-av--group{background:linear-gradient(135deg,#7c2d12,#c2410c);font-size:15px}
      .msg-sender{font-size:11.5px;font-weight:700;color:var(--text-2);margin:10px 0 -6px 38px}
      .msg-event{display:flex;justify-content:center;margin:12px 0}
      .msg-event span{max-width:80%;text-align:center;font-size:12px;color:var(--text-2);background:rgba(255,255,255,.75);border:1px solid var(--border);border-radius:var(--radius-pill);padding:4px 12px}
      .msg-typing__who{font-size:11px;color:var(--text-3);margin:0 0 -4px 38px}
      .msg-callbar{display:flex;align-items:center;gap:9px;padding:9px 18px;background:#ecfdf3;border-bottom:1px solid #bbf7d0;color:#166534;font-size:13px;font-weight:600}
      .msg-callbar__text{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .msg-callbar__dot{width:8px;height:8px;border-radius:50%;background:#22c55e;animation:msgPulse 1.6s infinite}
      @keyframes msgPulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.6)}100%{box-shadow:0 0 0 9px rgba(34,197,94,0)}}
      .msg-callbar__btn{border:0;border-radius:var(--radius-pill);background:#16a34a;color:#fff;font-weight:700;font-size:12.5px;padding:6px 16px;cursor:pointer}
      .msg-callbar__btn:disabled{background:#86efac;cursor:default}
      .msg-call--info{background:var(--surface-2);color:var(--text-2)}

      /* modal: new group / group info */
      .msg-modal{position:fixed;inset:0;z-index:1040;background:rgba(9,21,46,.45);display:flex;align-items:center;justify-content:center;padding:16px}
      .msg-modal__card{width:min(460px,100%);max-height:min(640px,calc(100vh - 32px));display:flex;flex-direction:column;background:var(--surface);border-radius:var(--radius-lg);box-shadow:0 24px 60px rgba(9,21,46,.3);overflow:hidden;animation:msgIn .2s ease-out}
      .msg-modal__head{display:flex;align-items:center;gap:10px;padding:16px 18px;border-bottom:1px solid var(--border)}
      .msg-modal__head h3{flex:1;font-size:16px;margin:0}
      .msg-modal__x{border:0;background:none;font-size:18px;color:var(--text-3);cursor:pointer;width:32px;height:32px;border-radius:50%}
      .msg-modal__x:hover{background:var(--surface-2);color:var(--text)}
      .msg-modal__body{flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:12px}
      .msg-modal__body > *{flex-shrink:0}   /* on short screens the body scrolls; the people list is never squeezed to 0px */
      .msg-modal__foot{display:flex;gap:8px;justify-content:flex-end;align-items:center;padding:12px 18px;border-top:1px solid var(--border)}
      .msg-modal__foot .grow{flex:1}
      .msg-modal label{font-size:12px;font-weight:700;color:var(--text-2);margin:0 0 5px;display:block}
      .msg-modal .wd-input{font-size:13.5px}
      .msg-modal__err{color:var(--danger-text);font-size:12.5px}
      .msg-modal__err:empty{display:none}
      .msg-chips{display:flex;flex-wrap:wrap;gap:6px}
      .msg-chips:empty{display:none}
      .msg-chip{display:inline-flex;align-items:center;gap:6px;background:var(--brand-tint);color:var(--brand-700);border-radius:var(--radius-pill);padding:3px 6px 3px 10px;font-size:12px;font-weight:600}
      .msg-chip button{border:0;background:none;color:inherit;cursor:pointer;font-size:12px;width:18px;height:18px;border-radius:50%;padding:0}
      .msg-chip button:hover{background:rgba(0,0,0,.08)}
      .msg-pick{display:flex;flex-direction:column;gap:2px;min-height:44px;max-height:260px;overflow-y:auto}
      .msg-pick .msg-item{padding:8px}
      .msg-pick .msg-item.is-on{background:var(--brand-tint)}
      .msg-pick__tick{flex:none;width:20px;height:20px;border-radius:50%;border:2px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px}
      .msg-item.is-on .msg-pick__tick{background:var(--brand);border-color:var(--brand)}
      .msg-member{display:flex;align-items:center;gap:10px;padding:6px 0}
      .msg-member__main{flex:1;min-width:0;line-height:1.3}
      .msg-member__name{font-weight:600;font-size:13.5px}
      .msg-member__sub{font-size:11.5px;color:var(--text-3)}
      .msg-role{font-size:10.5px;font-weight:700;color:var(--brand-700);background:var(--brand-tint);border-radius:var(--radius-pill);padding:2px 8px}
      .msg-linkbtn{border:0;background:none;color:var(--brand-700);font-weight:600;font-size:12.5px;cursor:pointer;padding:4px 6px;border-radius:6px}
      .msg-linkbtn:hover{background:var(--brand-tint)}
      .msg-linkbtn--danger{color:var(--danger-text)}
      .msg-linkbtn--danger:hover{background:var(--danger-bg)}
      .msg-rename{display:flex;gap:8px}
      .msg-rename .wd-input{flex:1}
      .msg-section{font-family:var(--font-head);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-top:4px}

      /* ---------------------------------------------------------------- avatars + presence */
      .msg-av{position:relative;flex:none;width:42px;height:42px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600}
      .msg-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
      .msg-av--sm{width:28px;height:28px;font-size:10.5px}
      .msg-av--lg{width:44px;height:44px;font-size:14px}
      .msg-av.is-online::after{content:"";position:absolute;right:0;bottom:0;width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid var(--surface)}
      .msg-av--sm.is-online::after{width:9px;height:9px}

      /* ---------------------------------------------------------------- thread */
      .msg-main{position:relative;display:flex;flex-direction:column;min-width:0;min-height:0;background:var(--surface)}
      .msg-main__head{display:flex;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--surface);z-index:2}
      .msg-main__head .n{font-family:var(--font-head);font-weight:700;font-size:15px;line-height:1.2}
      .msg-main__head .r{font-size:12px;color:var(--text-3);display:flex;align-items:center;gap:6px}
      .msg-main__head .r .on{color:#16a34a;font-weight:600}
      .msg-back{display:none}
      .msg-main__who{flex:1;min-width:0}
      .msg-call{flex:none;width:40px;height:40px;border:0;border-radius:50%;background:var(--brand-tint);color:var(--brand-700);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s,transform .12s}
      .msg-call:hover{background:var(--brand);color:#fff;transform:scale(1.05)}

      .msg-scroll{flex:1;overflow-y:auto;min-height:0;padding:12px 20px 16px;
        background:radial-gradient(circle at 1px 1px, rgba(22,32,58,.045) 1px, transparent 0) 0 0/18px 18px, var(--surface-2);scroll-behavior:smooth}
      .msg-scroll.is-instant{scroll-behavior:auto}
      .msg-day{position:sticky;top:0;z-index:1;display:flex;justify-content:center;margin:12px 0 8px;pointer-events:none}
      .msg-day span{background:rgba(255,255,255,.92);backdrop-filter:blur(6px);border:1px solid var(--border);box-shadow:var(--shadow-sm);border-radius:var(--radius-pill);padding:3px 12px;font-size:11px;font-weight:600;color:var(--text-2)}
      .msg-new{display:flex;align-items:center;gap:10px;margin:14px 0 8px;color:var(--brand-700);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
      .msg-new::before,.msg-new::after{content:"";flex:1;height:1px;background:var(--brand);opacity:.35}

      .msg-row{position:relative;display:flex;align-items:flex-end;gap:8px;margin-top:2px}
      .msg-row.is-first{margin-top:10px}
      .msg-row--mine{justify-content:flex-end}
      .msg-row .msg-av--sm{visibility:hidden}
      .msg-row.is-last .msg-av--sm{visibility:visible}
      .msg-bubble{position:relative;max-width:min(68%,560px);padding:8px 13px;border-radius:18px;font-size:13.5px;line-height:1.45;white-space:pre-wrap;overflow-wrap:anywhere;
        background:var(--surface);color:var(--text);border:1px solid var(--border);box-shadow:0 1px 1px rgba(16,24,40,.04)}
      .msg-bubble a{color:inherit;text-decoration:underline;text-underline-offset:2px;word-break:break-all}
      .msg-row:not(.msg-row--mine):not(.is-first) .msg-bubble{border-top-left-radius:6px}
      .msg-row:not(.msg-row--mine):not(.is-last) .msg-bubble{border-bottom-left-radius:6px}
      .msg-row--mine .msg-bubble{background:linear-gradient(135deg,var(--brand),var(--brand-600));color:#fff;border-color:transparent}
      .msg-row--mine:not(.is-first) .msg-bubble{border-top-right-radius:6px}
      .msg-row--mine:not(.is-last) .msg-bubble{border-bottom-right-radius:6px}
      .msg-bubble.is-emoji{background:none !important;border:0;box-shadow:none;font-size:34px;line-height:1.15;padding:0 2px}
      .msg-row.is-pending .msg-bubble{opacity:.6}
      .msg-row.is-failed .msg-bubble{background:var(--danger-bg) !important;color:var(--danger-text) !important;border:1px solid #f5c2bc}
      /* GIFs */
      .msg-gifbtn{font:800 10.5px/1 system-ui,-apple-system,'Segoe UI',sans-serif;letter-spacing:.3px}
      .msg-gifbtn.is-on{color:var(--brand);background:var(--brand-tint)}
      .msg-gif{position:absolute;left:16px;bottom:64px;z-index:5;width:340px;height:380px;display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:0 12px 32px rgba(16,24,40,.18);overflow:hidden}
      .msg-gif__search{padding:10px 10px 6px}
      .msg-gif__search .wd-input{font-size:13px}
      .msg-gif__grid{flex:1;min-height:0;overflow-y:auto;padding:4px 10px 10px;display:grid;grid-template-columns:1fr 1fr;grid-auto-rows:max-content;gap:6px;align-content:start}
      .msg-gif__grid button{display:block;width:100%;aspect-ratio:3 / 2;padding:0;border:0;border-radius:8px;overflow:hidden;background:var(--surface-2);cursor:pointer}
      .msg-gif__grid button:hover,.msg-gif__grid button:focus-visible{outline:2px solid var(--brand);outline-offset:-2px}
      .msg-gif__grid img{display:block;width:100%;height:100%;object-fit:cover}
      .msg-gif__note{grid-column:1 / -1;padding:18px 6px;text-align:center;font-size:12.5px;color:var(--text-3)}
      .msg-bubble.is-gif{padding:0;background:none !important;border:0;box-shadow:none;line-height:0;max-width:min(260px,68%)}
      .msg-bubble.is-gif img{display:block;width:100%;height:auto;border-radius:14px;background:var(--surface-2)}
      /* pictures + documents (msgfile access right) */
      .msg-bubble.is-img{padding:0;background:none !important;border:0;box-shadow:none;line-height:0;max-width:min(300px,68%)}
      .msg-img{display:block;border-radius:14px;overflow:hidden;background:var(--surface-2);cursor:zoom-in}
      .msg-img img{display:block;width:100%;height:auto;max-height:360px;object-fit:cover}
      .msg-bubble.is-file{padding:0;white-space:normal}
      .msg-file{display:flex;align-items:center;gap:11px;padding:10px 12px;min-width:220px;max-width:320px;color:inherit !important;text-decoration:none !important}
      .msg-file__ico{font-size:26px;flex:none;color:var(--brand)}
      .msg-row--mine .msg-file__ico{color:#fff}
      .msg-file__txt{display:flex;flex-direction:column;min-width:0;flex:1;line-height:1.3}
      .msg-file__name{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;word-break:normal}
      .msg-file__size{font-size:11.5px;opacity:.75}
      .msg-file__dl{flex:none;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;background:rgba(0,0,0,.06)}
      .msg-row--mine .msg-file__dl{background:rgba(255,255,255,.2)}
      .msg-row.is-pending .msg-file,.msg-row.is-pending .msg-img{pointer-events:none}
      .msg-main.is-drop{position:relative}
      .msg-main.is-drop::after{content:"Drop to send";position:absolute;inset:10px;z-index:8;display:flex;align-items:center;justify-content:center;
        border:2px dashed var(--brand);border-radius:16px;background:rgba(255,255,255,.88);color:var(--brand-700);font-weight:700;font-size:15px;pointer-events:none}
      /* reactions */
      .msg-react-btn{display:none;flex:none;align-self:center;width:28px;height:28px;border:0;border-radius:50%;background:none;color:var(--text-3);font-size:15px;cursor:pointer;opacity:0;align-items:center;justify-content:center;transition:opacity .15s,background .15s,color .15s}
      .msg-rx-on .msg-react-btn{display:flex}
      .msg-row:hover .msg-react-btn,.msg-row:focus-within .msg-react-btn,.msg-row.rx-open .msg-react-btn,.msg-row.show-rx .msg-react-btn{opacity:1}
      .msg-react-btn:hover,.msg-row.rx-open .msg-react-btn{background:var(--surface-2);color:var(--text)}
      .msg-row:not([data-id]) .msg-react-btn,.msg-row.is-pending .msg-react-btn,.msg-row.is-failed .msg-react-btn{visibility:hidden}
      /* delete my own message (msgdel access right) */
      .msg-del-btn{display:none;flex:none;align-self:center;width:28px;height:28px;border:0;border-radius:50%;background:none;color:var(--text-3);font-size:14px;cursor:pointer;opacity:0;align-items:center;justify-content:center;transition:opacity .15s,background .15s,color .15s}
      .msg-del-on .msg-del-btn{display:flex}
      .msg-row:hover .msg-del-btn,.msg-row:focus-within .msg-del-btn,.msg-row.show-rx .msg-del-btn{opacity:1}
      .msg-del-btn:hover{background:var(--danger-bg);color:var(--danger-text)}
      .msg-row:not([data-id]) .msg-del-btn,.msg-row.is-pending .msg-del-btn,.msg-row.is-failed .msg-del-btn{visibility:hidden}
      .msg-bubble.is-deleted{background:none !important;color:var(--text-3) !important;border:1px dashed var(--border-2) !important;box-shadow:none;font-style:italic;font-size:12.5px}
      .msg-bubble.is-deleted i{margin-right:3px;font-size:11px}
      .msg-row.has-rx{margin-bottom:20px}
      .msg-rx{position:absolute;right:6px;bottom:-14px;display:flex;align-items:center;gap:1px;height:22px;padding:0 6px;border-radius:999px;background:var(--surface);border:1px solid var(--border);box-shadow:0 1px 3px rgba(16,24,40,.14);font-size:12.5px;line-height:1;color:var(--text-2);cursor:pointer;white-space:nowrap;animation:msgRxPop .25s ease-out}
      .msg-rx.is-mine{border-color:var(--brand);background:var(--brand-tint)}
      .msg-rx__n{margin-left:3px;font-size:11px;font-weight:700}
      @keyframes msgRxPop{0%{transform:scale(.4)}70%{transform:scale(1.15)}100%{transform:scale(1)}}
      .msg-rxbar{position:absolute;bottom:calc(100% + 4px);left:38px;z-index:6;display:flex;gap:2px;padding:4px;background:var(--surface);border:1px solid var(--border);border-radius:999px;box-shadow:0 8px 24px rgba(16,24,40,.18);animation:msgIn .15s ease-out}
      .msg-row--mine .msg-rxbar{left:auto;right:0}
      .msg-rxbar.is-below{bottom:auto;top:calc(100% + 4px)}
      .msg-rxbar button{border:0;background:none;width:36px;height:36px;border-radius:50%;font-size:22px;line-height:1;cursor:pointer;transition:transform .12s,background .12s}
      .msg-rxbar button:hover,.msg-rxbar button:focus-visible{transform:scale(1.25);background:var(--surface-2);outline:none}
      .msg-rxbar button.is-on{background:var(--brand-tint)}
      /* mentions */
      .msg-mention{font-weight:700;color:var(--brand-700)}
      .msg-row--mine .msg-mention{color:#fff;text-decoration:underline;text-underline-offset:2px}
      .msg-row.is-mention .msg-bubble{background:#fff7e0;border-color:#f3d27a;box-shadow:inset 3px 0 0 #f0b429}
      .msg-item__at{flex:none;width:19px;height:19px;border-radius:50%;background:#f0b429;color:#fff;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center}
      .msg-mention-pop{position:absolute;left:16px;right:16px;bottom:calc(100% - 4px);z-index:6;max-height:260px;overflow-y:auto;padding:4px;background:var(--surface);border:1px solid var(--border);border-radius:12px;box-shadow:0 12px 32px rgba(16,24,40,.18)}
      .msg-mention-pop button{display:flex;align-items:center;gap:10px;width:100%;border:0;background:none;padding:6px 8px;border-radius:8px;text-align:left;font-size:13px;color:var(--text);cursor:pointer}
      .msg-mention-pop button.is-on,.msg-mention-pop button:hover{background:var(--brand-tint)}
      .msg-mention-pop button span:not(.msg-av){font-weight:600}
      .msg-mention-pop small{margin-left:auto;color:var(--text-3);font-size:11.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .msg-mention-pop__all{display:flex;align-items:center;justify-content:center;background:var(--brand-tint);color:var(--brand-700);font-size:12px}
      .msg-meta{font-size:10.5px;color:var(--text-3);margin:4px 2px 2px}
      .msg-meta--mine{text-align:right}
      .msg-meta--theirs{margin-left:38px}
      .msg-status{display:flex;justify-content:flex-end;align-items:center;gap:5px;font-size:10.5px;color:var(--text-3);margin:3px 2px 0}
      .msg-status.is-seen{color:var(--brand-700);font-weight:600}
      .msg-status.is-failed{color:var(--danger-text);font-weight:600}
      .msg-status button{border:0;background:none;color:inherit;font:inherit;text-decoration:underline;cursor:pointer;padding:0}
      @keyframes msgIn{from{opacity:0;transform:translateY(6px) scale(.98)}to{opacity:1;transform:none}}
      .msg-row.is-new{animation:msgIn .22s ease-out}

      /* typing bubble */
      .msg-typing{display:flex;align-items:flex-end;gap:8px;margin-top:10px}
      .msg-typing .msg-bubble{display:flex;gap:4px;padding:12px 14px}
      .msg-typing i{width:7px;height:7px;border-radius:50%;background:var(--text-3);animation:msgDot 1.2s infinite ease-in-out}
      .msg-typing i:nth-child(2){animation-delay:.15s}.msg-typing i:nth-child(3){animation-delay:.3s}
      @keyframes msgDot{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-4px);opacity:1}}

      /* jump to latest */
      .msg-jump{position:absolute;right:22px;bottom:86px;z-index:3;border:0;border-radius:var(--radius-pill);background:var(--surface);color:var(--text);
        box-shadow:0 6px 18px rgba(16,24,40,.18);padding:8px 14px;font-size:12.5px;font-weight:600;display:flex;align-items:center;gap:7px;cursor:pointer;transition:transform .15s}
      .msg-jump:hover{transform:translateY(-1px)}
      .msg-jump.has-new{background:var(--brand);color:#fff}

      /* ---------------------------------------------------------------- composer */
      .msg-compose{position:relative;display:flex;align-items:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border);background:var(--surface)}
      .msg-compose__field{flex:1;display:flex;align-items:flex-end;gap:4px;background:var(--surface-2);border:1px solid transparent;border-radius:22px;padding:4px 6px 4px 14px;transition:border-color .15s,background .15s}
      .msg-compose__field:focus-within{background:var(--surface);border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-tint)}
      .msg-compose textarea{flex:1;border:0;background:none;resize:none;outline:none;min-height:34px;max-height:140px;padding:7px 0;font-size:13.5px;line-height:1.4;color:var(--text)}
      .msg-ico{flex:none;width:34px;height:34px;border:0;border-radius:50%;background:none;color:var(--text-3);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center}
      .msg-ico:hover,.msg-ico.is-on{color:var(--brand-700);background:var(--brand-tint)}
      .msg-send{flex:none;width:42px;height:42px;border:0;border-radius:50%;background:var(--brand);color:#fff;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .12s,opacity .15s}
      .msg-send:hover:not(:disabled){transform:scale(1.06)}
      .msg-send:disabled{opacity:.35;cursor:default}
      html.wdcb-on .msg-compose{padding-right:92px}   /* keep Send clear of the floating Corner bubble */
      html.wdcb-on .msg-jump{right:96px}
      .msg-count{position:absolute;right:70px;top:-20px;font-size:10.5px;color:var(--text-3);background:var(--surface);padding:0 6px;border-radius:6px}
      .msg-count:empty{display:none}
      .msg-count.is-over{color:var(--danger-text);font-weight:700}
      .msg-emoji{position:absolute;left:16px;bottom:64px;z-index:5;width:292px;background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:0 12px 32px rgba(16,24,40,.18);padding:10px;display:grid;grid-template-columns:repeat(8,1fr);gap:2px}
      .msg-emoji[hidden]{display:none}
      .msg-emoji button{border:0;background:none;font-size:20px;line-height:1;padding:6px 0;border-radius:8px;cursor:pointer}
      .msg-emoji button:hover{background:var(--surface-2)}
      .msg-error{padding:0 16px 10px;font-size:12px;color:var(--danger-text);background:var(--surface)}
      .msg-note{padding:12px 16px;border-top:1px solid var(--border);font-size:12.5px;color:var(--text-3);text-align:center;background:var(--surface)}

      /* ---------------------------------------------------------------- empty + loading */
      .msg-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:30px;text-align:center;color:var(--text-3)}
      .msg-empty i{font-size:34px;color:var(--border-2);margin-bottom:6px}
      .msg-empty b{color:var(--text-2);font-size:14px}
      .msg-empty span{font-size:12.5px;max-width:280px}
      .msg-empty .msg-hello{font-size:40px;margin-bottom:4px}
      .msg-side .msg-empty{padding:40px 24px}
      .msg-skel{display:flex;align-items:center;gap:11px;padding:10px}
      .msg-skel .a{width:42px;height:42px;border-radius:50%}
      .msg-skel .l{flex:1;display:flex;flex-direction:column;gap:7px}
      .msg-skel .l span{height:10px;border-radius:6px}
      .msg-skel .l span:last-child{width:70%}
      .msg-skelb{height:34px;border-radius:16px;margin:10px 0;width:46%}
      .msg-skelb.r{margin-left:auto;width:38%}
      .msg-shimmer{background:linear-gradient(90deg,var(--surface-2) 25%,#e9ecf1 37%,var(--surface-2) 63%);background-size:400% 100%;animation:msgShim 1.3s ease infinite}
      .msg-scroll .msg-shimmer{background:linear-gradient(90deg,#e6e9ef 25%,#f3f5f8 37%,#e6e9ef 63%);background-size:400% 100%}
      @keyframes msgShim{0%{background-position:100% 50%}100%{background-position:0 50%}}

      @media (prefers-reduced-motion: reduce){
        .msg-row.is-new,.msg-typing i,.msg-shimmer,.msg-rx,.msg-rxbar{animation:none}
        .msg-scroll{scroll-behavior:auto}
      }

      /* ---------------------------------------------------------------- phones: one pane at a time */
      @media(max-width:760px){
        .msg-app{grid-template-columns:1fr;height:calc(100vh - var(--topbar-h) - 120px)}
        .msg-app.has-thread .msg-side{display:none}
        .msg-app:not(.has-thread) .msg-main{display:none}
        .msg-side{border-right:0}
        .msg-back{display:flex}
        .msg-bubble{max-width:82%}
        .msg-kbd{display:none}
        .msg-emoji{left:8px;right:8px;width:auto}
        .msg-gif{left:8px;right:8px;width:auto;height:min(400px,60vh)}
      }
    </style>
</head>
<body>
<?php $wd_active = 'messages'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
      <div>
        <h1>Messages</h1>
        <p>Chat one-to-one with your immediate superior and colleagues.</p>
      </div>
    </div>

    <div class="wd-card msg-app" id="msgApp"
         data-token="<?php echo htmlspecialchars(msg_csrf_token()); ?>"
         data-with="<?php echo htmlspecialchars($msgWith); ?>"
         data-max="<?php echo MSG_MAX_LEN; ?>">

      <aside class="msg-side">
        <div class="msg-side__head">
          <div class="msg-side__row">
            <div class="msg-search">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="search" class="wd-input" id="msgSearch" placeholder="Search chats or people" autocomplete="off" aria-label="Search chats or people">
              <span class="msg-kbd" aria-hidden="true">Ctrl K</span>
            </div>
            <button type="button" class="msg-newgrp" id="msgNewGroup" hidden title="New group" aria-label="New group"><i class="fa-solid fa-user-group"></i><i class="fa-solid fa-plus msg-newgrp__plus"></i></button>
          </div>
          <div class="msg-online" id="msgOnline" hidden>
            <div class="msg-online__label"><span class="msg-online__dot"></span>Online now <span class="msg-online__n" id="msgOnlineN"></span></div>
            <div class="msg-online__row" id="msgOnlineRow" role="list" aria-label="Colleagues online now"></div>
          </div>
          <div class="msg-tabs" role="tablist" aria-label="Filter conversations">
            <button type="button" class="msg-tab is-on" data-filter="all" role="tab" aria-selected="true">All</button>
            <button type="button" class="msg-tab" data-filter="unread" role="tab" aria-selected="false">Unread <span class="msg-tab__n" id="msgUnreadN"></span></button>
          </div>
        </div>
        <div class="msg-side__body" id="msgList" aria-live="polite"></div>
      </aside>

      <section class="msg-main" id="msgMain">
        <div class="msg-empty" id="msgPlaceholder">
          <i class="fa-regular fa-comments"></i>
          <b>Select a conversation</b>
          <span>Pick someone on the left, or search for a colleague to start a new chat. <br>Tip: press <b>Ctrl K</b> to search.</span>
        </div>

        <div class="msg-main__head" id="msgHead" hidden>
          <button type="button" class="wd-iconbtn msg-back" id="msgBack" aria-label="Back to conversations"><i class="fa-solid fa-arrow-left"></i></button>
          <div class="msg-av msg-av--lg" id="msgHeadAv"></div>
          <div class="msg-main__who"><div class="n" id="msgHeadName"></div><div class="r" id="msgHeadRole"></div></div>
          <button type="button" class="msg-call" id="msgCallBtn" hidden title="Video call" aria-label="Start a video call"><i class="fa-solid fa-video"></i></button>
          <button type="button" class="msg-call msg-call--info" id="msgInfoBtn" hidden title="Group info" aria-label="Group info and members"><i class="fa-solid fa-circle-info"></i></button>
          <button type="button" class="msg-call msg-call--info" id="msgClearBtn" hidden title="Delete conversation" aria-label="Delete this conversation for me"><i class="fa-regular fa-trash-can"></i></button>
        </div>
        <div class="msg-callbar" id="msgCallBar" hidden>
          <span class="msg-callbar__dot"></span><i class="fa-solid fa-video"></i>
          <span class="msg-callbar__text" id="msgCallBarText"></span>
          <button type="button" class="msg-callbar__btn" id="msgCallJoin">Join</button>
        </div>
        <div class="msg-scroll" id="msgScroll" hidden></div>
        <button type="button" class="msg-jump" id="msgJump" hidden><i class="fa-solid fa-arrow-down"></i> <span>Latest</span></button>

        <form class="msg-compose" id="msgCompose" hidden autocomplete="off">
          <div class="msg-emoji" id="msgEmoji" hidden role="dialog" aria-label="Emoji"></div>
          <div class="msg-gif" id="msgGif" hidden role="dialog" aria-label="GIFs"></div>
          <div class="msg-compose__field">
            <textarea id="msgText" rows="1" placeholder="Write a message&hellip;" aria-label="Message"></textarea>
            <button type="button" class="msg-ico" id="msgFileBtn" hidden aria-label="Send a picture or document" title="Picture or document"><i class="fa-solid fa-paperclip"></i></button>
            <input type="file" id="msgFileInput" hidden multiple tabindex="-1" aria-hidden="true"
                   accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
            <button type="button" class="msg-ico msg-gifbtn" id="msgGifBtn" hidden aria-label="Send a GIF" title="GIF">GIF</button>
            <button type="button" class="msg-ico" id="msgEmojiBtn" aria-label="Insert emoji" title="Emoji"><i class="fa-regular fa-face-smile"></i></button>
          </div>
          <button type="submit" class="msg-send" id="msgSend" aria-label="Send" title="Send (Enter)"><i class="fa-solid fa-paper-plane"></i></button>
          <span class="msg-count" id="msgCount"></span>
        </form>
        <div class="msg-error" id="msgError" hidden></div>
        <div class="msg-note" id="msgReadonly" hidden>You can no longer send messages to this person.</div>
      </section>

      <!-- new group / group info (built by script-message.js) -->
      <div class="msg-modal" id="msgModal" hidden>
        <div class="msg-modal__card" id="msgModalCard" role="dialog" aria-modal="true"></div>
      </div>
    </div>

<?php include 'includes/wd-footer.php'; ?>
</body>
</html>
