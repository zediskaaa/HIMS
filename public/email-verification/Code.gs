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
var LOGO_BASE64   = "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";

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
  var logoBlob = buildHimsLogoBlob();
  var plainTextBody =
    "HIMS Supply Chain and Inventory\n\n" +
    "Your password reset verification code is: " + otp + "\n\n" +
    "This code expires in 5 minutes. Do not share it with anyone.\n\n" +
    "If you did not request a password reset, you can safely ignore this email.";

  GmailApp.sendEmail(email, EMAIL_SUBJECT, plainTextBody, {
    htmlBody: htmlBody,
    name: SENDER_NAME,
    inlineImages: {
      himsLogo: logoBlob
    }
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
                      '<td width="44" height="44" align="center" valign="middle" style="width:44px; height:44px; border-radius:10px; background-color:#ffffff;">' +
                        '<img src="cid:himsLogo" width="44" height="44" alt="HIMS" style="display:block; width:44px; height:44px; border:0; border-radius:10px;">' +
                      '</td>' +
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

function buildHimsLogoBlob() {
  return Utilities.newBlob(
    Utilities.base64Decode(LOGO_BASE64),
    "image/png",
    "hims-logo.png"
  );
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
