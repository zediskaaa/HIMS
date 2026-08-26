/**
 * Forgot Password – Email OTP Verification
 * Google Apps Script Backend
 *
 * Deploy as Web App:
 *   Execute as → Me
 *   Who has access → Anyone
 *
 * Endpoints (via GET query params):
 *   ?action=sendOtp&email=...&callback=cbName
 *   ?action=verifyOtp&email=...&otp=...&callback=cbName
 */

// ─── Configuration ──────────────────────────────────────────────────────────────
var OTP_LENGTH    = 6;
var OTP_EXPIRY_MS = 5 * 60 * 1000; // 5 minutes
var EMAIL_SUBJECT = "HIMS – Password Reset Code";
var SENDER_NAME   = "HIMS Supply Chain and Inventory";

// ─── Main Entry Point ───────────────────────────────────────────────────────────

function doGet(e) {
  var params   = e.parameter;
  var action   = params.action   || "";
  var email    = params.email    || "";
  var otp      = params.otp      || "";
  var callback = params.callback || "callback";

  var result;

  try {
    switch (action) {
      case "sendOtp":
        result = handleSendOtp(email);
        break;
      case "verifyOtp":
        result = handleVerifyOtp(email, otp);
        break;
      default:
        result = { success: false, message: "Invalid action." };
    }
  } catch (err) {
    result = { success: false, message: "Server error: " + err.message };
  }

  // Return JSONP response
  var jsonpOutput = callback + "(" + JSON.stringify(result) + ");";
  return ContentService
    .createTextOutput(jsonpOutput)
    .setMimeType(ContentService.MimeType.JAVASCRIPT);
}

// ─── Send OTP ────────────────────────────────────────────────────────────────────

function handleSendOtp(email) {
  if (!email || !isValidEmail(email)) {
    return { success: false, message: "Please provide a valid email address." };
  }

  // Generate OTP
  var otp = generateOtp(OTP_LENGTH);

  // Store OTP with timestamp
  var store = PropertiesService.getScriptProperties();
  var data  = JSON.stringify({
    otp: otp,
    createdAt: new Date().getTime()
  });
  store.setProperty("otp_" + email, data);

  var htmlBody = buildPasswordResetEmail(otp);
  var plainTextBody =
    "HIMS Supply Chain and Inventory\n\n" +
    "Your password reset verification code is: " + otp + "\n\n" +
    "This code expires in 5 minutes. Do not share it with anyone.\n\n" +
    "If you did not request a password reset, you can safely ignore this email.";

  GmailApp.sendEmail(email, EMAIL_SUBJECT, plainTextBody, {
    htmlBody: htmlBody,
    name: SENDER_NAME
  });

  return { success: true, message: "Verification code sent to " + email };
}

// ─── Transactional Email Template ──────────────────────────────────────────────

function buildPasswordResetEmail(otp) {
  return '<!DOCTYPE html>' +
    '<html lang="en">' +
    '<head>' +
      '<meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1.0">' +
      '<title>HIMS Password Reset Code</title>' +
    '</head>' +
    '<body style="margin:0; padding:0; background-color:#f5f5f5; font-family:Inter,Segoe UI,Arial,sans-serif; color:#262626; -webkit-font-smoothing:antialiased;">' +
      '<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">Use this secure verification code to reset your HIMS password.</div>' +
      '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#f5f5f5; border-collapse:collapse;">' +
        '<tr>' +
          '<td align="center" style="padding:32px 16px;">' +
            '<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:560px; border-collapse:separate; border-spacing:0; background-color:#ffffff; border:1px solid #e5e5e5; border-radius:16px; box-shadow:0 12px 32px rgba(10,10,10,0.10); overflow:hidden;">' +
              '<tr>' +
                '<td style="height:4px; background-color:#3395ff; font-size:0; line-height:0;">&nbsp;</td>' +
              '</tr>' +
              '<tr>' +
                '<td style="padding:28px 32px; background-color:#0a0a0a; border-radius:15px 15px 0 0;">' +
                  '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">' +
                    '<tr>' +
                      '<td width="44" height="44" align="center" valign="middle" style="width:44px; height:44px; border-radius:10px; background-color:#174cb6; color:#ffffff; font-size:20px; line-height:44px; font-weight:700;">H</td>' +
                      '<td style="padding-left:14px;">' +
                        '<div style="color:#ffffff; font-size:18px; line-height:24px; font-weight:700; letter-spacing:-0.2px;">HIMS</div>' +
                        '<div style="margin-top:2px; color:#a3a3a3; font-size:10px; line-height:14px; font-weight:500; letter-spacing:1.4px; text-transform:uppercase;">Supply Chain and Inventory</div>' +
                      '</td>' +
                    '</tr>' +
                  '</table>' +
                '</td>' +
              '</tr>' +
              '<tr>' +
                '<td style="padding:32px; background-color:#ffffff;">' +
                  '<div style="display:inline-block; margin:0 0 16px; padding:5px 11px; border:1px solid #d9edff; border-radius:999px; background-color:#eef7ff; color:#145ee1; font-size:11px; line-height:16px; font-weight:600; letter-spacing:0.8px; text-transform:uppercase;">Secure account recovery</div>' +
                  '<h1 style="margin:0; color:#171717; font-size:28px; line-height:36px; font-weight:700; letter-spacing:-0.6px;">Reset your password</h1>' +
                  '<p style="margin:12px 0 24px; color:#525252; font-size:15px; line-height:24px;">We received a request to reset the password for your HIMS account. Enter the verification code below to continue.</p>' +
                  '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; border-collapse:separate; border-spacing:0;">' +
                    '<tr>' +
                      '<td align="center" style="padding:24px 12px; border:1px solid #d9edff; border-radius:12px; background-color:#eef7ff;">' +
                        '<div style="margin-bottom:8px; color:#525252; font-size:11px; line-height:16px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase;">Verification code</div>' +
                        '<div style="color:#145ee1; font-family:Consolas,Courier New,monospace; font-size:36px; line-height:44px; font-weight:700; letter-spacing:10px; white-space:nowrap;">' + otp + '</div>' +
                      '</td>' +
                    '</tr>' +
                  '</table>' +
                  '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin-top:20px; border-collapse:separate; border-spacing:0;">' +
                    '<tr>' +
                      '<td width="4" style="width:4px; border-radius:4px 0 0 4px; background-color:#3395ff; font-size:0;">&nbsp;</td>' +
                      '<td style="padding:13px 16px; border-radius:0 8px 8px 0; background-color:#fafafa; color:#404040; font-size:13px; line-height:20px;"><strong>This code expires in 5 minutes.</strong> For your security, never share this code with anyone.</td>' +
                    '</tr>' +
                  '</table>' +
                  '<div style="height:1px; margin:24px 0; background-color:#e5e5e5; font-size:0; line-height:0;">&nbsp;</div>' +
                  '<p style="margin:0; color:#737373; font-size:13px; line-height:21px;">Did not request this password reset? You can safely ignore this email. Your password will remain unchanged.</p>' +
                '</td>' +
              '</tr>' +
              '<tr>' +
                '<td align="center" style="padding:20px 32px; border-top:1px solid #e5e5e5; border-radius:0 0 15px 15px; background-color:#fafafa;">' +
                  '<p style="margin:0; color:#525252; font-size:12px; line-height:18px; font-weight:600;">HIMS Supply Chain and Inventory</p>' +
                  '<p style="margin:4px 0 0; color:#a3a3a3; font-size:11px; line-height:17px;">Automated security message · Please do not reply</p>' +
                '</td>' +
              '</tr>' +
            '</table>' +
          '</td>' +
        '</tr>' +
      '</table>' +
    '</body>' +
    '</html>';
}

// ─── Verify OTP ──────────────────────────────────────────────────────────────────

function handleVerifyOtp(email, otp) {
  if (!email || !otp) {
    return { success: false, message: "Email and verification code are required." };
  }

  var store = PropertiesService.getScriptProperties();
  var raw   = store.getProperty("otp_" + email);

  if (!raw) {
    return { success: false, message: "No verification code found. Please request a new one." };
  }

  var data    = JSON.parse(raw);
  var now     = new Date().getTime();
  var elapsed = now - data.createdAt;

  // Check expiry
  if (elapsed > OTP_EXPIRY_MS) {
    store.deleteProperty("otp_" + email);
    return { success: false, message: "Code has expired. Please request a new one." };
  }

  // Check match
  if (data.otp !== otp) {
    return { success: false, message: "Invalid code. Please try again." };
  }

  // OTP is valid — clean up
  store.deleteProperty("otp_" + email);
  return { success: true, message: "Email verified successfully!" };
}

// ─── Helpers ─────────────────────────────────────────────────────────────────────

function generateOtp(length) {
  var digits = "0123456789";
  var otp = "";
  for (var i = 0; i < length; i++) {
    otp += digits.charAt(Math.floor(Math.random() * digits.length));
  }
  return otp;
}

function isValidEmail(email) {
  var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}
