/**
 * Smart Phayao SEC Platform — Google Workspace Bridge (Apps Script Web App)
 *
 * PHP (core/GoogleWorkspace.php) เรียก Web App นี้แบบ server-to-server ด้วย cURL
 * ทุกคำขอต้องเซ็น HMAC-SHA256 ด้วย SHARED_SECRET (กันคนนอกเรียก URL ตรง)
 *
 * Script Properties ที่ต้องตั้ง (Project Settings → Script properties):
 *   SHARED_SECRET   คีย์ลับอย่างน้อย 32 ตัวอักษร (ค่าเดียวกับ config google.shared_secret)
 *   ROOT_FOLDER_ID  รหัสโฟลเดอร์ใน Google Shared Drive ที่ใช้เก็บไฟล์ของระบบ
 *   CALENDAR_ID     (ไม่บังคับ) รหัส Google Calendar ขององค์กรสำหรับซิงก์ปฏิทินกลาง
 *
 * Deploy: Deploy → New deployment → Web app
 *   Execute as: Me (บัญชี Google Workspace ที่เป็นสมาชิก Shared Drive)
 *   Who has access: Anyone   (ความปลอดภัยมาจากลายเซ็น HMAC ไม่ใช่การล็อกอิน Google)
 */

var MAX_CLOCK_SKEW_SECONDS = 300;
var NONCE_TTL_SECONDS = 600;
var MAX_FILE_BYTES = 20 * 1024 * 1024;

function doPost(e) {
  try {
    var req = JSON.parse(e.postData.contents);
    verify_(req);
    var payload = JSON.parse(req.payload || '{}');
    var handler = ACTIONS[req.action];
    if (!handler) throw new Error('ไม่รู้จัก action: ' + req.action);
    return json_({ success: true, message: 'ok', data: handler(payload) || {} });
  } catch (err) {
    return json_({ success: false, message: String(err && err.message ? err.message : err), data: null });
  }
}

function doGet() {
  return json_({ success: false, message: 'ใช้ POST เท่านั้น', data: null });
}

/**
 * ตรวจการตั้งค่าจากหน้า Editor: เลือกฟังก์ชัน checkSetup แล้วกด Run
 * (การกด Run ครั้งแรกจะขอสิทธิ์ Drive/Calendar/Gmail ด้วย) ดูผลที่ Execution log
 */
function checkSetup() {
  var props = PropertiesService.getScriptProperties();
  var secret = props.getProperty('SHARED_SECRET') || '';
  var ok = true;
  function report(pass, text) {
    if (!pass) ok = false;
    Logger.log((pass ? '✅ ' : '❌ ') + text);
  }

  report(secret.length >= 32, 'SHARED_SECRET ยาว ' + secret.length + ' ตัวอักษร (ต้องอย่างน้อย 32)');
  report(secret === secret.trim(), 'SHARED_SECRET ไม่มีช่องว่างหน้า/หลัง');
  Logger.log('ℹ️ บัญชีที่รันสคริปต์: ' + Session.getEffectiveUser().getEmail());
  Logger.log('ℹ️ Time zone ของโปรเจกต์: ' + Session.getScriptTimeZone() + ' (ควรเป็น Asia/Bangkok)');

  try {
    var root = DriveApp.getFolderById(props.getProperty('ROOT_FOLDER_ID'));
    var probe = root.createFile('smartsec-setup-check.txt', 'ทดสอบสิทธิ์ ' + new Date());
    probe.setTrashed(true);
    report(true, 'ROOT_FOLDER_ID: โฟลเดอร์ "' + root.getName() + '" สร้างและลบไฟล์ได้');
  } catch (err) {
    report(false, 'ROOT_FOLDER_ID ใช้ไม่ได้: ' + err.message + ' (ตรวจรหัสโฟลเดอร์ และสิทธิ์ Content manager ใน Shared Drive)');
  }

  var calendarId = props.getProperty('CALENDAR_ID');
  if (calendarId) {
    var cal = CalendarApp.getCalendarById(calendarId);
    report(!!cal, cal ? 'CALENDAR_ID: ปฏิทิน "' + cal.getName() + '"' : 'CALENDAR_ID ไม่ถูกต้อง หรือบัญชีนี้ไม่มีสิทธิ์แก้ไขปฏิทิน');
  } else {
    Logger.log('ℹ️ ไม่ได้ตั้ง CALENDAR_ID (ข้ามการซิงก์ Google Calendar)');
  }

  Logger.log('ℹ️ โควตาอีเมลที่เหลือวันนี้: ' + MailApp.getRemainingDailyQuota());
  Logger.log(ok ? '🎉 พร้อมใช้งาน — ขั้นต่อไป: Deploy เป็น Web app' : '⚠️ ยังมีรายการที่ต้องแก้ตามเครื่องหมาย ❌ ด้านบน');
}

var ACTIONS = {
  'ping': function () {
    var props = PropertiesService.getScriptProperties();
    var root = DriveApp.getFolderById(props.getProperty('ROOT_FOLDER_ID'));
    var calendarId = props.getProperty('CALENDAR_ID');
    var cal = calendarId ? CalendarApp.getCalendarById(calendarId) : null;
    return {
      account: Session.getEffectiveUser().getEmail(),
      folder: root.getName(),
      calendar: cal ? cal.getName() : null,
      mailQuota: MailApp.getRemainingDailyQuota()
    };
  },

  'drive.upload': function (p) {
    var bytes = Utilities.base64Decode(p.base64);
    if (bytes.length > MAX_FILE_BYTES) throw new Error('ไฟล์ใหญ่เกิน 20 MB');
    var folder = folderByPath_(String(p.folder || 'misc'));
    var blob = Utilities.newBlob(bytes, String(p.mimeType), String(p.name));
    var file = folder.createFile(blob);
    return { id: file.getId(), size: file.getSize(), name: file.getName() };
  },

  'drive.download': function (p) {
    var file = DriveApp.getFileById(String(p.id));
    assertInRoot_(file);
    var blob = file.getBlob();
    return { name: file.getName(), mimeType: blob.getContentType(), size: file.getSize(), base64: Utilities.base64Encode(blob.getBytes()) };
  },

  'drive.trash': function (p) {
    var file = DriveApp.getFileById(String(p.id));
    assertInRoot_(file);
    file.setTrashed(true);
    return { id: file.getId() };
  },

  'calendar.upsert': function (p) {
    var cal = calendar_();
    var start = new Date(p.start);
    var end = p.end ? new Date(p.end) : null;
    var options = { description: p.description || '', location: p.location || '' };
    var event = p.eventId ? cal.getEventById(String(p.eventId)) : null;
    if (event) {
      event.setTitle(String(p.title));
      event.setDescription(options.description);
      event.setLocation(options.location);
      if (p.allDay) {
        end ? event.setAllDayDates(start, end) : event.setAllDayDate(start);
      } else {
        event.setTime(start, end || new Date(start.getTime() + 3600000));
      }
    } else if (p.allDay) {
      event = end ? cal.createAllDayEvent(String(p.title), start, end, options) : cal.createAllDayEvent(String(p.title), start, options);
    } else {
      event = cal.createEvent(String(p.title), start, end || new Date(start.getTime() + 3600000), options);
    }
    return { eventId: event.getId() };
  },

  'calendar.delete': function (p) {
    var event = calendar_().getEventById(String(p.eventId));
    if (event) event.deleteEvent();
    return { eventId: p.eventId };
  },

  'mail.send': function (p) {
    var to = (p.to || []).join(',');
    if (!to) throw new Error('ไม่มีผู้รับ');
    MailApp.sendEmail({ to: to, subject: String(p.subject), body: String(p.body || ''), htmlBody: String(p.htmlBody || ''), name: String(p.name || '') });
    return { sent: (p.to || []).length };
  }
};

// ---------------------------------------------------------------- ความปลอดภัย

function verify_(req) {
  var secret = PropertiesService.getScriptProperties().getProperty('SHARED_SECRET');
  if (!secret || secret.length < 32) throw new Error('ยังไม่ได้ตั้ง SHARED_SECRET (อย่างน้อย 32 ตัวอักษร)');
  if (!req || !req.action || typeof req.payload !== 'string' || !req.ts || !req.nonce || !req.sig) throw new Error('คำขอไม่ครบถ้วน');

  var now = Math.floor(Date.now() / 1000);
  if (Math.abs(now - Number(req.ts)) > MAX_CLOCK_SKEW_SECONDS) throw new Error('คำขอหมดอายุ (เวลาเครื่องไม่ตรงกัน)');

  var expected = hex_(Utilities.computeHmacSha256Signature(req.ts + '.' + req.nonce + '.' + req.action + '.' + req.payload, secret));
  if (!safeEquals_(expected, String(req.sig))) throw new Error('ลายเซ็นไม่ถูกต้อง');

  var cache = CacheService.getScriptCache();
  var key = 'nonce_' + req.nonce;
  if (cache.get(key)) throw new Error('คำขอซ้ำ (replay)');
  cache.put(key, '1', NONCE_TTL_SECONDS);
}

function safeEquals_(a, b) {
  if (a.length !== b.length) return false;
  var diff = 0;
  for (var i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

function hex_(bytes) {
  return bytes.map(function (b) { return ('0' + (b & 0xff).toString(16)).slice(-2); }).join('');
}

/** อนุญาตเฉพาะไฟล์ที่อยู่ใต้โฟลเดอร์รากของระบบ (กันการดึงไฟล์อื่นใน Drive ด้วย ID) */
function assertInRoot_(file) {
  var rootId = PropertiesService.getScriptProperties().getProperty('ROOT_FOLDER_ID');
  var queue = [];
  var parents = file.getParents();
  while (parents.hasNext()) queue.push(parents.next());
  for (var depth = 0; depth < 10 && queue.length; depth++) {
    var next = [];
    for (var i = 0; i < queue.length; i++) {
      if (queue[i].getId() === rootId) return;
      var up = queue[i].getParents();
      while (up.hasNext()) next.push(up.next());
    }
    queue = next;
  }
  throw new Error('ไม่อนุญาตให้เข้าถึงไฟล์นอกโฟลเดอร์ของระบบ');
}

// ---------------------------------------------------------------- utilities

function folderByPath_(path) {
  var folder = DriveApp.getFolderById(PropertiesService.getScriptProperties().getProperty('ROOT_FOLDER_ID'));
  path.split('/').forEach(function (name) {
    name = name.replace(/[^\w.\-]/g, '_');
    if (!name) return;
    var it = folder.getFoldersByName(name);
    folder = it.hasNext() ? it.next() : folder.createFolder(name);
  });
  return folder;
}

function calendar_() {
  var id = PropertiesService.getScriptProperties().getProperty('CALENDAR_ID');
  if (!id) throw new Error('ยังไม่ได้ตั้ง CALENDAR_ID');
  var cal = CalendarApp.getCalendarById(id);
  if (!cal) throw new Error('ไม่พบ Google Calendar ตาม CALENDAR_ID หรือไม่มีสิทธิ์แก้ไข');
  return cal;
}

function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
