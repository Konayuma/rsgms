<?php
if (!isset($_SESSION['user_id'])) {
    return;
}

if (!isset($pdo)) {
    return;
}

$sharedNavbarUserId = (int) $_SESSION['user_id'];
$sharedNavbarRole = $_SESSION['role'] ?? 'member';

$sharedNavbarStmt = $pdo->prepare('SELECT id, full_name, username, email, role FROM users WHERE id = ?');
$sharedNavbarStmt->execute([$sharedNavbarUserId]);
$sharedNavbarUser = $sharedNavbarStmt->fetch();

if (!$sharedNavbarUser) {
    return;
}

$sharedNavbarDisplayName = $sharedNavbarUser['full_name'] ?: ($sharedNavbarUser['username'] ?? 'User');
$sharedNavbarRoleLabel = ucfirst(str_replace('_', ' ', $sharedNavbarRole));
$sharedNavbarInitial = strtoupper(substr($sharedNavbarDisplayName, 0, 1));

$sharedNavbarStmt = $pdo->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE');
$sharedNavbarStmt->execute([$sharedNavbarUserId]);
$sharedNavbarUnreadCount = (int) ($sharedNavbarStmt->fetch()['unread'] ?? 0);

$sharedNavbarStmt = $pdo->prepare('SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$sharedNavbarStmt->execute([$sharedNavbarUserId]);
$sharedNavbarNotifications = $sharedNavbarStmt->fetchAll();

$sharedNavbarPreview = static function (string $text, int $limit = 82): string {
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '...');
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
};
?>
<style>
    .shared-navbar-spacer { height: 72px; }
    .shared-navbar {
        position: fixed; top: 0; left: var(--sidebar-width); right: 0; z-index: 120;
        pointer-events: none; padding: 12px 20px 0;
        transition: left .3s ease;
    }
    .shared-navbar-shell {
        pointer-events: auto; display: flex; align-items: center; justify-content: flex-end; gap: 10px;
    }
    .shared-navbar-wrap { position: relative; }
    .shared-navbar-button {
        min-height: 40px; display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 14px; border: 1px solid oklch(0 0 0 / .06); border-radius: 12px;
        background: var(--cream); color: var(--ink);
        box-shadow: var(--shadow-card); cursor: pointer;
        transition: transform .15s, box-shadow .15s;
    }
    .shared-navbar-button:hover { transform: translateY(-1px); box-shadow: var(--shadow-card-hover); }
    .shared-navbar-bell { font-weight: 500; font-size: 0.88rem; }
    .shared-navbar-profile { font-size: 0.88rem; }
    .shared-navbar-avatar {
        width: 32px; height: 32px; border-radius: 50%; display: inline-flex;
        align-items: center; justify-content: center;
        background: var(--clay); color: var(--cream); font-size: 0.85rem; font-weight: 600;
    }
    .shared-navbar-copy { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.2; text-align: left; }
    .shared-navbar-label { font-weight: 500; font-size: 0.88rem; color: var(--ink); }
    .shared-navbar-subtext { color: var(--ink-soft); font-size: 0.72rem; }
    .shared-navbar-badge {
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: var(--radius-full);
        background: var(--clay); color: var(--cream);
        font-size: 0.7rem; font-weight: 600;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .shared-navbar-menu {
        position: absolute; top: calc(100% + 8px); right: 0; width: 340px;
        background: var(--white-soft); border: 1px solid oklch(from var(--clay) l c h / .08);
        border-radius: 16px; box-shadow: 0 20px 40px oklch(0 0 0 / .08);
        overflow: hidden; opacity: 0; visibility: hidden;
        transform: translateY(-6px) scale(.98);
        transition: opacity .18s, transform .18s, visibility .18s;
        z-index: 140;
    }
    .shared-navbar-menu.is-open { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
    .shared-navbar-section { padding: 12px 16px; }
    .shared-navbar-section + .shared-navbar-section { border-top: 1px solid oklch(from var(--clay) l c h / .06); }
    .shared-navbar-section-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
    .shared-navbar-title { font-family:'Fraunces',serif; font-size: 0.9rem; font-weight: 500; color: var(--ink); font-variation-settings:'SOFT' 80; }
    .shared-navbar-subtitle { color: var(--ink-soft); font-size: 0.78rem; }
    .shared-navbar-link, .shared-navbar-action {
        display: flex; align-items: flex-start; gap: 8px; width: 100%;
        padding: 8px 10px; border-radius: 10px; text-decoration: none;
        color: var(--ink); border: none; background: transparent; cursor: pointer;
        text-align: left; transition: background .15s;
    }
    .shared-navbar-link:hover, .shared-navbar-action:hover { background: oklch(from var(--clay) l c h / .04); }
    .shared-navbar-link strong, .shared-navbar-action strong { display: block; color: var(--ink); font-weight: 500; margin-bottom: 1px; font-size: 0.85rem; }
    .shared-navbar-link span, .shared-navbar-action span { color: var(--ink-soft); font-size: 0.8rem; line-height: 1.4; }
    .shared-navbar-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--clay); margin-top: 5px; flex: 0 0 auto; }
    .shared-navbar-dot.is-read { background: oklch(from var(--clay) l c h / .2); }
    .shared-navbar-empty { color: var(--ink-soft); font-size: 0.85rem; line-height: 1.5; padding: 4px 2px; }
    .shared-navbar-name { font-family:'Fraunces',serif; font-weight: 500; color: var(--ink); font-size: 0.9rem; font-variation-settings:'SOFT' 80; }
    .shared-navbar-role { color: var(--ink-soft); font-size: 0.78rem; }
    .shared-navbar-email { color: var(--ink-soft); font-size: 0.82rem; }
    .shared-navbar-mark-all {
        padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer;
        background: var(--clay); color: var(--cream); font-size: 0.75rem; font-family:'Sora',sans-serif; font-weight: 500;
    }
    .shared-navbar-mark-all:hover { background: var(--clay-dark); }
    .shared-navbar-logout { color: var(--danger); }
    .shared-navbar-logout:hover { background: oklch(from var(--danger) l c h / .06); }
    .sidebar-toggle{
        display:none; position:fixed; top:12px; left:12px; z-index:200;
        width:36px; height:36px; border-radius:10px;
        background:var(--cream); border:1px solid oklch(0 0 0 / .06);
        cursor:pointer; align-items:center; justify-content:center;
        font-size:1rem; color:var(--ink); box-shadow:var(--shadow-card);
    }
    @media(max-width:768px){
        .sidebar-toggle{display:flex}
        .shared-navbar{left:0;padding:8px 10px 0}
        .shared-navbar-shell{flex-wrap:nowrap;justify-content:flex-end;gap:6px}
        .shared-navbar-wrap{flex:0 1 auto;min-width:0}
        .shared-navbar-button{width:auto;min-height:40px;padding:6px 10px;font-size:0.82rem;white-space:nowrap}
        .shared-navbar-button .shared-navbar-copy{display:none}
        .shared-navbar-button .shared-navbar-subtext{display:none}
        .shared-navbar-button .fa-chevron-down{display:none}
        .shared-navbar-bell span{display:none}
        .shared-navbar-avatar{width:28px;height:28px;font-size:0.75rem;flex:0 0 auto}
        .shared-navbar-menu{width:min(92vw,340px);right:-10px}
        .shared-navbar-spacer{height:56px}
    }
    @media(max-width:480px){
        .shared-navbar{padding:6px 8px 0}
        .shared-navbar-button{padding:4px 8px;min-height:36px;gap:4px;border-radius:10px}
        .shared-navbar-avatar{width:26px;height:26px;font-size:0.7rem}
        .shared-navbar-badge{min-width:16px;height:16px;font-size:0.62rem;padding:0 4px}
        .shared-navbar-spacer{height:48px}
        .shared-navbar-menu{width:96vw;right:-8px;top:calc(100%+4px)}
    }
</style>
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
<div class="shared-navbar" data-shared-navbar>
    <div class="shared-navbar-shell">
        <div class="shared-navbar-wrap">
            <button type="button" class="shared-navbar-button shared-navbar-bell" data-navbar-toggle="notifications" aria-haspopup="true" aria-expanded="false" aria-controls="sharedNotificationsMenu">
                <i class="fa-solid fa-bell"></i>
                <span>Alerts</span>
                <?php if ($sharedNavbarUnreadCount > 0): ?><span class="shared-navbar-badge"><?php echo $sharedNavbarUnreadCount; ?></span><?php endif; ?>
            </button>
            <div class="shared-navbar-menu" id="sharedNotificationsMenu" role="menu" aria-label="Notification preview">
                <div class="shared-navbar-section">
                    <div class="shared-navbar-section-head">
                        <div>
                            <div class="shared-navbar-title">Notifications</div>
                            <div class="shared-navbar-subtitle"><?php echo $sharedNavbarUnreadCount; ?> unread</div>
                        </div>
                        <?php if ($sharedNavbarUnreadCount > 0): ?>
                        <button type="button" class="shared-navbar-mark-all" data-mark-all-read>Mark all read</button>
                        <?php endif; ?>
                    </div>
                    <?php if (count($sharedNavbarNotifications) > 0): ?>
                        <?php foreach ($sharedNavbarNotifications as $notification): ?>
                        <?php $sharedNavbarMessage = $sharedNavbarPreview((string) ($notification['message'] ?? '')); ?>
                        <a class="shared-navbar-link" href="notifications.php">
                            <span class="shared-navbar-dot <?php echo !empty($notification['is_read']) ? 'is-read' : ''; ?>"></span>
                            <span>
                                <strong><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></strong>
                                <span><?php echo htmlspecialchars($sharedNavbarMessage); ?></span>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="shared-navbar-empty">No notifications yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="shared-navbar-wrap">
            <button type="button" class="shared-navbar-button shared-navbar-profile" data-navbar-toggle="profile" aria-haspopup="true" aria-expanded="false" aria-controls="sharedProfileMenu">
                <span class="shared-navbar-avatar"><?php echo htmlspecialchars($sharedNavbarInitial); ?></span>
                <span class="shared-navbar-copy">
                    <span class="shared-navbar-label"><?php echo htmlspecialchars($sharedNavbarDisplayName); ?></span>
                    <span class="shared-navbar-subtext"><?php echo htmlspecialchars($sharedNavbarRoleLabel); ?></span>
                </span>
                <i class="fa-solid fa-chevron-down" style="margin-left:auto;"></i>
            </button>
            <div class="shared-navbar-menu" id="sharedProfileMenu" role="menu" aria-label="Profile menu">
                <div class="shared-navbar-section">
                    <div class="shared-navbar-section-head">
                        <div>
                            <div class="shared-navbar-title"><?php echo htmlspecialchars($sharedNavbarDisplayName); ?></div>
                            <div class="shared-navbar-subtitle"><?php echo htmlspecialchars($sharedNavbarRoleLabel); ?></div>
                        </div>
                    </div>
                    <div class="shared-navbar-email"><?php echo htmlspecialchars($sharedNavbarUser['email'] ?? 'No email on file'); ?></div>
                </div>
                <div class="shared-navbar-section">
                    <a class="shared-navbar-link" href="profile.php"><i class="fa-solid fa-user"></i><span><strong>Profile</strong><span>Update your account details.</span></span></a>
                    <a class="shared-navbar-link" href="dashboard.php"><i class="fa-solid fa-chart-column"></i><span><strong>Dashboard</strong><span>Return to the main overview.</span></span></a>
                    <a class="shared-navbar-link shared-navbar-logout" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span><strong>Logout</strong><span>End your session securely.</span></span></a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="shared-navbar-spacer" aria-hidden="true"></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
    var navbar=document.querySelector('[data-shared-navbar]');
    if(!navbar)return;
    var toggles=navbar.querySelectorAll('[data-navbar-toggle]');
    function closeMenus(){toggles.forEach(function(t){var m=document.getElementById(t.getAttribute('aria-controls'));if(m){t.setAttribute('aria-expanded','false');m.classList.remove('is-open')}})}
    toggles.forEach(function(toggle){var menuId=toggle.getAttribute('aria-controls');var menu=menuId?document.getElementById(menuId):null;if(!menu)return;
        toggle.addEventListener('click',function(e){e.stopPropagation();var isOpen=menu.classList.contains('is-open');closeMenus();if(!isOpen){menu.classList.add('is-open');toggle.setAttribute('aria-expanded','true')}})});
    document.addEventListener('click',function(e){if(!navbar.contains(e.target))closeMenus()});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeMenus()});

    var markBtn=document.querySelector('[data-mark-all-read]');
    if(markBtn){markBtn.addEventListener('click',function(){
        var r=new XMLHttpRequest();r.open('POST','ajax/mark_notification.php',true);
        r.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        r.onload=function(){if(r.status===200){pollUnread();pollNotifications()}};
        r.send('action=mark_all_read')
    })}

    var unreadBadge=document.querySelector('.shared-navbar-badge');
    var unreadSub=document.querySelector('.shared-navbar-subtitle');
    var dropLinks=document.querySelector('#sharedNotificationsMenu .shared-navbar-section');
    function pollUnread(){var r=new XMLHttpRequest();r.open('GET','ajax/unread_count.php?_='+Date.now(),true);r.onload=function(){if(r.status!==200)return;try{var d=JSON.parse(r.responseText);var c=d.unread||0;if(unreadBadge){unreadBadge.textContent=c;unreadBadge.style.display=c>0?'inline-flex':'none'}if(unreadSub)unreadSub.textContent=c+' unread'}catch(_){}};r.send()}
    function pollNotifications(){var r=new XMLHttpRequest();r.open('GET','ajax/recent_notifications.php?_='+Date.now(),true);r.onload=function(){if(r.status!==200||!dropLinks)return;try{var d=JSON.parse(r.responseText);var items=d.notifications||[];var html='';if(items.length>0){items.forEach(function(n){var dot=n.is_read?'is-read':'';html+='<a class="shared-navbar-link" href="notifications.php"><span class="shared-navbar-dot '+dot+'"></span><span><strong>'+esc(n.title)+'</strong><span>'+esc(n.preview)+'</span></span></a>'})}else{html='<div class="shared-navbar-empty">No notifications yet.</div>'}dropLinks.innerHTML=html}catch(_){}};r.send()}
    function esc(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML}
    setInterval(pollUnread,30000);setInterval(pollNotifications,60000);
});

// sidebar toggle (mobile)
document.addEventListener('DOMContentLoaded',function(){
    var btn=document.getElementById('sidebarToggle');
    if(!btn)return;
    btn.addEventListener('click',function(){document.querySelector('.sidebar').classList.toggle('open')});
});

// sidebar collapse (desktop — persists to localStorage)
document.addEventListener('DOMContentLoaded',function(){
    var side=document.getElementById('appSidebar');
    var collBtn=document.getElementById('sidebarCollapseBtn');
    if(!side||!collBtn)return;
    var main=document.querySelector('.main-content');
    var nav=document.querySelector('.shared-navbar');
    function applyCollapse(collapsed){
        side.classList.toggle('collapsed',collapsed);
        if(main)main.classList.toggle('sidebar-collapsed',collapsed);
        if(nav)nav.style.left=collapsed?'var(--sidebar-collapsed)':'var(--sidebar-width)';
    }
    // restore saved state
    var saved=localStorage.getItem('rsgms_sidebar_collapsed');
    if(saved==='true')applyCollapse(true);
    collBtn.addEventListener('click',function(){
        var now=side.classList.contains('collapsed');
        applyCollapse(!now);
        localStorage.setItem('rsgms_sidebar_collapsed',!now);
    });
});
</script>
