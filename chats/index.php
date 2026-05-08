<?php
/**
 * chats/index.php - نظام المراسلات الداخلي
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

$my_id      = intval($_SESSION['user']['id']);
$my_name    = htmlspecialchars($_SESSION['user']['name']);
$my_role    = strval($_SESSION['user']['role']);
$company_id = intval($_SESSION['user']['company_id']);

// ألوان ثابتة للأدوار (تُعاد دورياً حسب رقم الدور)
$role_palette = [
    '#1b2f6e', '#28a745', '#fd7e14', '#6f42c1',
    '#20c997', '#17a2b8', '#e83e8c', '#f59e0b',
    '#343a40', '#dc3545', '#0c1c3e', '#6c757d',
];

// جلب جميع الأدوار النشطة من جدول roles
$safe_company = intval($company_id);
$roles_map   = []; // role_id => ['name'=>..., 'color'=>...]
$roles_res   = mysqli_query($conn, "SELECT id, name FROM roles WHERE status = '1' ORDER BY level ASC, id ASC");
if ($roles_res) {
    $palette_idx = 0;
    while ($r = mysqli_fetch_assoc($roles_res)) {
        $roles_map[strval($r['id'])] = [
            'name'  => $r['name'],
            'color' => $role_palette[$palette_idx % count($role_palette)],
        ];
        $palette_idx++;
    }
}

// دالة مساعدة: اسم ولون الدور من الخريطة
function getRoleInfo($role, $roles_map, $role_palette) {
    if (isset($roles_map[$role])) {
        return $roles_map[$role];
    }
    // مدير عام أو أدوار خارج الجدول
    $fallback_names = ['-1' => 'إدارة عليا', '0' => 'مدير عام'];
    return [
        'name'  => isset($fallback_names[$role]) ? $fallback_names[$role] : 'مستخدم',
        'color' => $role_palette[abs(intval($role)) % count($role_palette)],
    ];
}

// جلب المستخدمين في نفس الشركة مع آخر رسالة وعدد غير المقروءة
// مربوطين بأدوار موجودة في جدول roles (أو أدوار خاصة -1، 0)
$sql_contacts = "
    SELECT
        u.id,
        u.name,
        u.role,
        u.status
        ,(SELECT m.message
          FROM messages m
          WHERE m.company_id = $safe_company
            AND ((m.sender_id = $my_id AND m.receiver_id = u.id AND m.is_deleted_sender = 0)
              OR (m.sender_id = u.id AND m.receiver_id = $my_id AND m.is_deleted_receiver = 0))
          ORDER BY m.created_at DESC, m.id DESC
          LIMIT 1
         ) AS last_message
        ,(SELECT m.created_at
          FROM messages m
          WHERE m.company_id = $safe_company
            AND ((m.sender_id = $my_id AND m.receiver_id = u.id)
              OR (m.sender_id = u.id AND m.receiver_id = $my_id))
          ORDER BY m.created_at DESC, m.id DESC
          LIMIT 1
         ) AS last_message_time
        ,(SELECT COUNT(*)
          FROM messages m
          WHERE m.company_id  = $safe_company
            AND m.sender_id   = u.id
            AND m.receiver_id = $my_id
            AND m.is_read     = 0
            AND m.is_deleted_receiver = 0
         ) AS unread_count
    FROM users u
    WHERE u.company_id = $safe_company
      AND u.id         != $my_id
      AND u.is_deleted  = 0
      AND u.status      = 'active'
      AND (
          u.role IN (SELECT CAST(id AS CHAR) FROM roles WHERE status = '1')
          OR u.role IN ('-1', '0')
      )
    ORDER BY last_message_time DESC, u.name ASC
";
$contacts_result = mysqli_query($conn, $sql_contacts);
$contacts    = [];
$departments = []; // role_id => role_name (للأدوار الموجودة فعلاً في القائمة)
while ($row = mysqli_fetch_assoc($contacts_result)) {
    $info              = getRoleInfo($row['role'], $roles_map, $role_palette);
    $row['role_name']  = $info['name'];
    $row['role_color'] = $info['color'];
    $row['avatar']     = mb_substr($row['name'], 0, 1);
    $contacts[]        = $row;
    $dept_key = $row['role'];
    if (!isset($departments[$dept_key])) {
        $departments[$dept_key] = $info['name'];
    }
}

$page_title = "المراسلات الداخلية";
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main chat-main-page">
<div class="chat-wrapper">

    <!-- ===== لوحة جهات الاتصال ===== -->
    <div class="contacts-panel" id="contactsPanel">

        <!-- Header -->
        <div class="contacts-header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h5 style="margin: 0;"><i class="fas fa-comments"></i> المراسلات الداخلية</h5>
                <button class="broadcast-btn" onclick="openBroadcastModal()" title="إرسال رسالة للجميع">
                    <i class="fas fa-bullhorn"></i>
                </button>
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="contactSearch" placeholder="ابحث عن مستخدم...">
            </div>
        </div>

        <!-- Dept Filter Tabs -->
        <div class="dept-tabs">
            <span class="dept-tab active" data-role="all">الكل</span>
            <?php foreach ($departments as $role_key => $role_name): ?>
                <span class="dept-tab" data-role="<?php echo htmlspecialchars($role_key); ?>">
                    <?php echo htmlspecialchars($role_name); ?>
                </span>
            <?php endforeach; ?>
        </div>

        <!-- Contacts List -->
        <div class="contacts-list" id="contactsList">
            <?php if (empty($contacts)): ?>
                <div class="no-contacts">
                    <i class="fas fa-user-slash" style="font-size:2rem; margin-bottom:8px; display:block;"></i>
                    لا يوجد مستخدمون آخرون في شركتك
                </div>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <?php
                    $unread = intval($contact['unread_count']);
                    $last   = $contact['last_message'];
                    $last   = $last ? (mb_strlen($last) > 35 ? mb_substr($last, 0, 35) . '...' : $last) : '';
                    $time   = $contact['last_message_time'] ? date('H:i', strtotime($contact['last_message_time'])) : '';
                    ?>
                    <div class="contact-item"
                         data-id="<?php echo intval($contact['id']); ?>"
                         data-role="<?php echo htmlspecialchars($contact['role']); ?>"
                         data-name="<?php echo htmlspecialchars($contact['name']); ?>"
                         data-role-name="<?php echo htmlspecialchars($contact['role_name']); ?>"
                         data-color="<?php echo htmlspecialchars($contact['role_color']); ?>"
                         data-avatar="<?php echo htmlspecialchars($contact['avatar']); ?>"
                         onclick="openConversation(this)">
                        <div class="contact-avatar" style="background:<?php echo htmlspecialchars($contact['role_color']); ?>">
                            <?php echo htmlspecialchars($contact['avatar']); ?>
                        </div>
                        <div class="contact-info">
                            <div class="contact-name"><?php echo htmlspecialchars($contact['name']); ?></div>
                            <div class="contact-role"><?php echo htmlspecialchars($contact['role_name']); ?></div>
                            <?php if ($last): ?>
                                <div class="contact-last-msg"><?php echo htmlspecialchars($last); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="contact-meta">
                            <?php if ($time): ?>
                                <span class="contact-time"><?php echo $time; ?></span>
                            <?php endif; ?>
                            <?php if ($unread > 0): ?>
                                <span class="unread-badge" data-unread="<?php echo intval($contact['id']); ?>">
                                    <?php echo $unread > 99 ? '99+' : $unread; ?>
                                </span>
                            <?php else: ?>
                                <span class="unread-badge" data-unread="<?php echo intval($contact['id']); ?>" style="display:none;"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== لوحة المحادثة ===== -->
    <div class="chat-panel" id="chatPanel">

        <!-- Empty State -->
        <div class="chat-empty" id="chatEmpty">
            <i class="fas fa-comment-dots"></i>
            <p>اختر شخصاً لبدء المراسلة</p>
            <small>اختر أحد الأشخاص من القائمة لبدء محادثة معه</small>
        </div>

        <!-- Chat Active Area (مخفية في البداية) -->
        <div id="chatActive" style="display:none; flex-direction:column; height:100%; overflow:hidden; display:none;">

            <!-- Chat Header -->
            <div class="chat-header">
                <button class="mobile-back-btn" onclick="closeMobileChat()">
                    <i class="fas fa-arrow-right"></i> رجوع
                </button>
                <div class="avatar" id="chatAvatar"></div>
                <div class="chat-header-info">
                    <div class="chat-header-name" id="chatUserName"></div>
                    <div class="chat-header-role" id="chatUserRole"></div>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="messages-area" id="messagesArea">
                <div class="messages-loading">
                    <i class="fas fa-circle-notch fa-spin"></i> جاري تحميل الرسائل...
                </div>
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="إرسال">
                    <i class="fas fa-paper-plane"></i>
                </button>
                <textarea id="messageInput"
                          placeholder="اكتب رسالتك هنا..."
                          rows="1"
                          onkeydown="handleInputKey(event)"
                          oninput="autoResize(this)"></textarea>
            </div>

        </div>

    </div>
</div><!-- .chat-wrapper -->
</div><!-- .main -->

<!-- ===== Broadcast Modal ===== -->
<div class="broadcast-modal" id="broadcastModal">
    <div class="broadcast-dialog">
        <div class="broadcast-header">
            <h5><i class="fas fa-bullhorn"></i> إرسال رسالة جماعية</h5>
            <button class="broadcast-close" onclick="closeBroadcastModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="broadcast-body">
            <!-- اختيار نوع المستلمين -->
            <div class="recipients-type">
                <label>
                    <i class="fas fa-users"></i>
                    المستلمين
                </label>
                <div class="recipients-type-options">
                    <div class="type-option active" data-type="all" onclick="selectRecipientType('all')">
                        <i class="fas fa-users"></i>
                        الكل (<?php echo count($contacts); ?>)
                    </div>
                    <div class="type-option" data-type="specific" onclick="selectRecipientType('specific')">
                        <i class="fas fa-user-check"></i>
                        تحديد مستخدمين
                    </div>
                </div>
            </div>

            <!-- قائمة المستخدمين -->
            <div class="recipients-list" id="recipientsList">
                <div class="select-actions">
                    <button class="select-btn" onclick="selectAllRecipients()"><i class="fas fa-check-double"></i> تحديد الكل</button>
                    <button class="select-btn" onclick="deselectAllRecipients()"><i class="fas fa-times"></i> إلغاء الكل</button>
                </div>
                <input type="text" class="recipients-search" id="recipientsSearch" placeholder="ابحث عن مستخدم...">
                <div id="recipientsContainer">
                    <?php foreach ($contacts as $contact): ?>
                        <div class="recipient-item" data-name="<?php echo htmlspecialchars($contact['name']); ?>" data-role="<?php echo htmlspecialchars($contact['role_name']); ?>" onclick="toggleRecipient(<?php echo $contact['id']; ?>)">
                            <input type="checkbox" value="<?php echo $contact['id']; ?>" class="recipient-checkbox" onclick="event.stopPropagation();">
                            <div class="recipient-avatar" style="background: <?php echo htmlspecialchars($contact['role_color']); ?>">
                                <?php echo htmlspecialchars($contact['avatar']); ?>
                            </div>
                            <div class="recipient-info">
                                <div class="recipient-name"><?php echo htmlspecialchars($contact['name']); ?></div>
                                <div class="recipient-role"><?php echo htmlspecialchars($contact['role_name']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="selected-count" id="selectedCount">تم تحديد 0 مستخدم</div>
            </div>

            <!-- الرسالة -->
            <label style="margin-top: 20px;">
                <i class="fas fa-envelope"></i>
                الرسالة
            </label>
            <textarea id="broadcastMessage" placeholder="اكتب رسالتك هنا..."></textarea>
            <div class="broadcast-char-count">
                <span id="broadcastCharCount">0</span> / 2000 حرف
            </div>
        </div>
        <div class="broadcast-footer">
            <button class="broadcast-cancel-btn" onclick="closeBroadcastModal()">إلغاء</button>
            <button class="broadcast-send-btn" id="broadcastSendBtn" onclick="sendBroadcast()">
                <i class="fas fa-paper-plane"></i>
                إرسال
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    // ===== المتغيرات الرئيسية =====
    var currentUserId   = <?php echo $my_id; ?>;
    var activeContactId = null;
    var activeContactEl = null;
    var lastMessageId   = 0;
    var pollTimer       = null;
    var navPollTimer    = null;

    // ===== فتح محادثة =====
    window.openConversation = function(el) {
        var userId    = parseInt(el.getAttribute('data-id'));
        var name      = el.getAttribute('data-name');
        var roleName  = el.getAttribute('data-role-name');
        var color     = el.getAttribute('data-color');
        var avatar    = el.getAttribute('data-avatar');

        // تمييز جهة الاتصال المحددة
        document.querySelectorAll('.contact-item').forEach(function(c) { c.classList.remove('active'); });
        el.classList.add('active');

        activeContactId = userId;
        activeContactEl = el;
        lastMessageId   = 0;

        // تحديث header المحادثة
        document.getElementById('chatAvatar').textContent        = avatar;
        document.getElementById('chatAvatar').style.background   = color;
        document.getElementById('chatUserName').textContent      = name;
        document.getElementById('chatUserRole').textContent      = roleName;

        // إظهار لوحة المحادثة
        document.getElementById('chatEmpty').style.display  = 'none';
        var chatActive = document.getElementById('chatActive');
        chatActive.style.display = 'flex';
        chatActive.style.flexDirection = 'column';
        chatActive.style.height = '100%';
        chatActive.style.overflow = 'hidden';

        // Mobile
        document.getElementById('contactsPanel').classList.remove('mobile-show');
        document.getElementById('chatPanel').classList.remove('mobile-hide');

        // تحميل الرسائل
        document.getElementById('messagesArea').innerHTML =
            '<div class="messages-loading"><i class="fas fa-circle-notch fa-spin"></i> جاري تحميل الرسائل...</div>';

        loadMessages(userId, false);
        markRead(userId);

        // إخفاء شارة الرسائل غير المقروءة
        var badge = el.querySelector('[data-unread="' + userId + '"]');
        if (badge) badge.style.display = 'none';

        // إعادة تشغيل التحديث التلقائي
        clearInterval(pollTimer);
        pollTimer = setInterval(function() { pollNewMessages(); }, 3000);

        // التركيز على خانة الإدخال
        setTimeout(function() { document.getElementById('messageInput').focus(); }, 100);
    };

    // ===== تحميل الرسائل =====
    function loadMessages(userId, append) {
        var params = 'with_user_id=' + userId;
        if (append && lastMessageId > 0) {
            params += '&last_id=' + lastMessageId;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_messages.php?' + params, true);
        xhr.onload = function() {
            if (xhr.status !== 200) return;
            try {
                var data = JSON.parse(xhr.responseText);
                if (!data.success) return;
                if (append) {
                    if (data.messages.length > 0) {
                        appendMessages(data.messages);
                        // تنبيه فقط إذا كانت رسائل واردة
                        var incoming = data.messages.filter(function(m) { return !m.is_mine; });
                        if (incoming.length > 0) {
                            markRead(userId);
                        }
                    }
                } else {
                    renderMessages(data.messages);
                }
            } catch(e) {}
        };
        xhr.send();
    }

    // ===== رسم كل الرسائل =====
    function renderMessages(messages) {
        var area = document.getElementById('messagesArea');
        area.innerHTML = '';
        if (messages.length === 0) {
            area.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8; font-size:0.85rem;">لا توجد رسائل بعد... ابدأ المحادثة!</div>';
            return;
        }
        var lastDate = '';
        messages.forEach(function(msg) {
            if (msg.date_label !== lastDate) {
                var sep = document.createElement('div');
                sep.className = 'date-separator';
                var dateLabel = msg.date_label === new Date().toISOString().slice(0,10) ? 'اليوم' : msg.date_label;
                sep.innerHTML = '<span>' + dateLabel + '</span>';
                area.appendChild(sep);
                lastDate = msg.date_label;
            }
            area.appendChild(createBubble(msg));
            if (msg.id > lastMessageId) lastMessageId = msg.id;
        });
        scrollToBottom();
    }

    // ===== إلحاق رسائل جديدة =====
    function appendMessages(messages) {
        var area = document.getElementById('messagesArea');
        var wasAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 60;
        var lastDate = '';
        // الحصول على آخر تاريخ معروض
        var seps = area.querySelectorAll('.date-separator span');
        if (seps.length > 0) lastDate = seps[seps.length-1].textContent;

        messages.forEach(function(msg) {
            var dateLabel = msg.date_label === new Date().toISOString().slice(0,10) ? 'اليوم' : msg.date_label;
            if (dateLabel !== lastDate) {
                var sep = document.createElement('div');
                sep.className = 'date-separator';
                sep.innerHTML = '<span>' + dateLabel + '</span>';
                area.appendChild(sep);
                lastDate = dateLabel;
            }
            area.appendChild(createBubble(msg));
            if (msg.id > lastMessageId) lastMessageId = msg.id;
        });
        if (wasAtBottom) scrollToBottom();
    }

    // ===== إنشاء فقاعة رسالة =====
    function createBubble(msg) {
        var row = document.createElement('div');
        row.className = 'msg-row ' + (msg.is_mine ? 'mine' : 'theirs');

        var readTick = '';
        if (msg.is_mine) {
            readTick = '<span class="msg-read-tick' + (msg.is_read ? ' read' : '') + '">' +
                       '<i class="fas fa-' + (msg.is_read ? 'check-double' : 'check') + '"></i>' +
                       '</span>';
        }

        row.innerHTML =
            '<div class="msg-bubble ' + (msg.is_mine ? 'mine' : 'theirs') + '">' +
                escapeHtml(msg.message).replace(/\n/g, '<br>') +
                '<div class="msg-time">' + msg.time_label + ' ' + readTick + '</div>' +
            '</div>';
        return row;
    }

    // ===== تحديث تلقائي =====
    function pollNewMessages() {
        if (!activeContactId) return;
        loadMessages(activeContactId, true);
    }

    // ===== تعليم مقروءة =====
    function markRead(senderId) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'mark_read.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('sender_id=' + senderId);
        // تحديث شارة nav
        updateNavBadge();
    }

    // ===== إرسال رسالة =====
    window.sendMessage = function() {
        if (!activeContactId) return;
        var input = document.getElementById('messageInput');
        var msg   = input.value.trim();
        if (!msg) return;

        var btn = document.getElementById('sendBtn');
        btn.disabled = true;
        input.value  = '';
        autoResize(input);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'send_message.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            btn.disabled = false;
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    // إضافة الرسالة فوراً دون انتظار polling
                    var fakeMsg = {
                        id: data.message_id,
                        is_mine: true,
                        message: msg,
                        is_read: 0,
                        time_label: new Date().toLocaleTimeString('ar', {hour:'2-digit', minute:'2-digit'}),
                        date_label: new Date().toISOString().slice(0,10)
                    };
                    var area = document.getElementById('messagesArea');
                    // إزالة رسالة "لا توجد رسائل"
                    var empty = area.querySelector('div[style]');
                    if (empty && empty.textContent.indexOf('ابدأ') !== -1) area.removeChild(empty);
                    appendMessages([fakeMsg]);
                    if (data.message_id > lastMessageId) lastMessageId = data.message_id;
                    // تحديث آخر رسالة في قائمة جهات الاتصال
                    updateContactLastMessage(activeContactId, msg);
                }
            } catch(e) {}
        };
        xhr.onerror = function() { btn.disabled = false; };
        xhr.send('receiver_id=' + activeContactId + '&message=' + encodeURIComponent(msg));
    };

    // ===== مفتاح Enter للإرسال =====
    window.handleInputKey = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    // ===== تغيير حجم خانة الإدخال تلقائياً =====
    window.autoResize = function(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    };

    // ===== تحديث آخر رسالة في جهة الاتصال =====
    function updateContactLastMessage(userId, text) {
        var el = document.querySelector('.contact-item[data-id="' + userId + '"]');
        if (!el) return;
        var lastEl = el.querySelector('.contact-last-msg');
        var shortText = text.length > 35 ? text.slice(0, 35) + '...' : text;
        if (lastEl) {
            lastEl.textContent = shortText;
        } else {
            var info = el.querySelector('.contact-info');
            if (info) {
                var d = document.createElement('div');
                d.className = 'contact-last-msg';
                d.textContent = shortText;
                info.appendChild(d);
            }
        }
        var timeEl = el.querySelector('.contact-time');
        var now = new Date().toLocaleTimeString('ar', {hour:'2-digit', minute:'2-digit'});
        if (timeEl) { timeEl.textContent = now; }
    }

    // ===== الانزلاق للأسفل =====
    function scrollToBottom() {
        var area = document.getElementById('messagesArea');
        area.scrollTop = area.scrollHeight;
    }

    // ===== Escape HTML =====
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ===== تحديث شارة Nav =====
    function updateNavBadge() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_unread_count.php', true);
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                var badge = document.getElementById('nav-unread-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            } catch(e) {}
        };
        xhr.send();
    }

    // ===== تحديث شارات الرسائل غير المقروءة في القائمة =====
    function pollContactsBadges() {
        // تحديث الشارات لجهات الاتصال بدون polling كثيف
        document.querySelectorAll('.contact-item').forEach(function(el) {
            var uid = parseInt(el.getAttribute('data-id'));
            if (uid === activeContactId) return; // المحادثة المفتوحة لا تحتاج تحديث
        });
    }

    // ===== Toast إشعار رسالة جديدة =====
    var lastNotifiedId = 0;
    function checkIncomingToast(messages) {
        if (!messages || messages.length === 0) return;
        var incoming = messages.filter(function(m) { return !m.is_mine && m.id > lastNotifiedId; });
        if (incoming.length > 0 && lastNotifiedId > 0) {
            showToast(incoming[0].sender_name, incoming[0].message);
        }
        if (incoming.length > 0) {
            lastNotifiedId = Math.max.apply(null, incoming.map(function(m) { return m.id; }));
        }
    }

    function showToast(from, msg) {
        var old = document.getElementById('chatToast');
        if (old) old.remove();
        var toast = document.createElement('div');
        toast.id = 'chatToast';
        toast.className = 'chat-toast';
        var shortMsg = msg.length > 60 ? msg.slice(0, 60) + '...' : msg;
        toast.innerHTML = '<i class="fas fa-comment-alt"></i><div><strong>' + escapeHtml(from) + '</strong><br><small>' + escapeHtml(shortMsg) + '</small></div>';
        document.body.appendChild(toast);
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 4000);
    }

    // دالة إظهار إشعار بسيط
    function simpleToast(msg, icon) {
        var old = document.getElementById('chatToast');
        if (old) old.remove();
        var toast = document.createElement('div');
        toast.id = 'chatToast';
        toast.className = 'chat-toast';
        var iconHtml = icon || 'fas fa-info-circle';
        toast.innerHTML = '<i class="' + iconHtml + '"></i><div>' + escapeHtml(msg) + '</div>';
        document.body.appendChild(toast);
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 4000);
    }

    // ===== فلترة جهات الاتصال بالقسم =====
    document.querySelectorAll('.dept-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.dept-tab').forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var role = tab.getAttribute('data-role');
            document.querySelectorAll('.contact-item').forEach(function(item) {
                if (role === 'all' || item.getAttribute('data-role') === role) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // ===== البحث في جهات الاتصال =====
    document.getElementById('contactSearch').addEventListener('input', function() {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.contact-item').forEach(function(item) {
            var name = item.getAttribute('data-name').toLowerCase();
            var role = item.getAttribute('data-role-name').toLowerCase();
            item.style.display = (!q || name.indexOf(q) !== -1 || role.indexOf(q) !== -1) ? '' : 'none';
        });
    });

    // ===== Mobile Back =====
    window.closeMobileChat = function() {
        document.getElementById('contactsPanel').classList.add('mobile-show');
        document.getElementById('chatPanel').classList.add('mobile-hide');
        clearInterval(pollTimer);
        activeContactId = null;
    };

    // ===== تحديث شارة Nav كل 30 ثانية =====
    updateNavBadge();
    navPollTimer = setInterval(updateNavBadge, 30000);

    // ===== فتح محادثة من URL =====
    var urlParams = new URLSearchParams(window.location.search);
    var withUser  = urlParams.get('with');
    if (withUser) {
        var targetEl = document.querySelector('.contact-item[data-id="' + withUser + '"]');
        if (targetEl) {
            openConversation(targetEl);
        }
    }

    // ===== Broadcast Modal Functions =====
    window.openBroadcastModal = function() {
        document.getElementById('broadcastModal').classList.add('show');
        document.getElementById('broadcastMessage').value = '';
        document.getElementById('broadcastCharCount').textContent = '0';
        // إعادة تعيين نوع المستلمين للكل
        selectRecipientType('all');
        document.getElementById('broadcastMessage').focus();
    };

    window.closeBroadcastModal = function() {
        document.getElementById('broadcastModal').classList.remove('show');
    };

    // عداد الأحرف
    document.getElementById('broadcastMessage').addEventListener('input', function() {
        var len = this.value.length;
        document.getElementById('broadcastCharCount').textContent = len;
        if (len > 2000) {
            this.value = this.value.substring(0, 2000);
            document.getElementById('broadcastCharCount').textContent = '2000';
        }
    });

    // إغلاق Modal عند الضغط خارجها
    document.getElementById('broadcastModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBroadcastModal();
        }
    });

    // اختيار نوع المستلمين
    window.selectRecipientType = function(type) {
        document.querySelectorAll('.type-option').forEach(function(el) {
            el.classList.remove('active');
        });
        document.querySelector('.type-option[data-type="' + type + '"]').classList.add('active');
        
        if (type === 'specific') {
            document.getElementById('recipientsList').classList.add('show');
        } else {
            document.getElementById('recipientsList').classList.remove('show');
        }
    };

    // تحديد جميع المستخدمين
    window.selectAllRecipients = function() {
        document.querySelectorAll('.recipient-checkbox').forEach(function(cb) {
            cb.checked = true;
        });
        updateSelectedCount();
    };

    // إلغاء تحديد الكل
    window.deselectAllRecipients = function() {
        document.querySelectorAll('.recipient-checkbox').forEach(function(cb) {
            cb.checked = false;
        });
        updateSelectedCount();
    };

    // تبديل تحديد مستخدم
    window.toggleRecipient = function(id) {
        var cb = document.querySelector('.recipient-checkbox[value="' + id + '"]');
        if (cb) {
            cb.checked = !cb.checked;
            updateSelectedCount();
        }
    };

    // تحديث عدد المستخدمين المحددين
    function updateSelectedCount() {
        var count = document.querySelectorAll('.recipient-checkbox:checked').length;
        var countEl = document.getElementById('selectedCount');
        if (count > 0) {
            countEl.textContent = 'تم تحديد ' + count + ' مستخدم';
            countEl.classList.add('show');
        } else {
            countEl.classList.remove('show');
        }
    }

    // البحث في المستخدمين
    document.getElementById('recipientsSearch').addEventListener('input', function() {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.recipient-item').forEach(function(item) {
            var name = item.getAttribute('data-name').toLowerCase();
            var role = item.getAttribute('data-role').toLowerCase();
            item.style.display = (!q || name.indexOf(q) !== -1 || role.indexOf(q) !== -1) ? '' : 'none';
        });
    });

    // تحديث العداد عند تغيير الـ checkboxes
    document.querySelectorAll('.recipient-checkbox').forEach(function(cb) {
        cb.addEventListener('change', updateSelectedCount);
    });

    // إرسال الرسالة الجماعية
    window.sendBroadcast = function() {
        var textarea = document.getElementById('broadcastMessage');
        var msg = textarea.value.trim();
        if (!msg) {
            simpleToast('الرجاء كتابة رسالة أولاً', 'fas fa-exclamation-circle');
            return;
        }

        // جمع المستلمين
        var recipientType = document.querySelector('.type-option.active').getAttribute('data-type');
        var recipients = [];
        
        if (recipientType === 'specific') {
            document.querySelectorAll('.recipient-checkbox:checked').forEach(function(cb) {
                recipients.push(cb.value);
            });
            
            if (recipients.length === 0) {
                simpleToast('الرجاء تحديد مستلم واحد على الأقل', 'fas fa-exclamation-circle');
                return;
            }
        }

        var btn = document.getElementById('broadcastSendBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'send_broadcast.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال';
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    simpleToast(data.message, 'fas fa-check-circle');
                    closeBroadcastModal();
                    // تحديث قائمة جهات الاتصال
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    simpleToast(data.message || 'فشل في إرسال الرسائل', 'fas fa-exclamation-triangle');
                }
            } catch(e) {
                simpleToast('حدث خطأ غير متوقع', 'fas fa-exclamation-triangle');
                console.error('Broadcast error:', e);
            }
        };
        xhr.onerror = function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال';
            simpleToast('فشل الاتصال بالخادم', 'fas fa-exclamation-triangle');
        };
        
        var params = 'message=' + encodeURIComponent(msg);
        if (recipientType === 'specific') {
            params += '&recipients=' + encodeURIComponent(JSON.stringify(recipients));
        }
        xhr.send(params);
    };
})();
</script>
</body>
</html>
