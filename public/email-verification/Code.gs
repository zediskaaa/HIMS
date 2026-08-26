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

  // Build the email HTML
  var htmlBody =
    '<div style="font-family: \'Segoe UI\', Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 0;">' +
      // Header bar
      '<div style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 32px 32px 24px; border-radius: 12px 12px 0 0;">' +
        '<div style="display: inline-block; background: rgba(255,255,255,0.1); border-radius: 8px; padding: 8px 12px; font-size: 18px; font-weight: bold; color: #818cf8;">H</div>' +
        '<span style="margin-left: 12px; font-size: 18px; font-weight: 600; color: #fff; vertical-align: middle;">HIMS</span>' +
        '<p style="margin: 16px 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #94a3b8;">Password Reset Request</p>' +
      '</div>' +
      // Body
      '<div style="background: #ffffff; padding: 32px; border: 1px solid #e2e8f0; border-top: none;">' +
        '<p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 24px;">You requested a password reset for your HIMS account. Use the verification code below to proceed. This code expires in <strong>5 minutes</strong>.</p>' +
        '<div style="background: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 24px; text-align: center; margin: 0 0 24px;">' +
          '<span style="font-size: 40px; font-weight: 800; letter-spacing: 14px; color: #1a1a2e; font-family: \'Courier New\', monospace;">' + otp + '</span>' +
        '</div>' +
        '<p style="color: #64748b; font-size: 13px; line-height: 1.5; margin: 0;">If you did not request this password reset, you can safely ignore this email. Your account remains secure.</p>' +
      '</div>' +
      // Footer
      '<div style="background: #f8fafc; padding: 20px 32px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px; text-align: center;">' +
        '<p style="color: #94a3b8; font-size: 11px; margin: 0;">Hospital Inventory Management System</p>' +
      '</div>' +
    '</div>';

  GmailApp.sendEmail(email, EMAIL_SUBJECT, "Your HIMS password reset code is: " + otp + " (expires in 5 minutes)", {
    htmlBody: htmlBody,
    name: "HIMS Supplier and Inventory Management"
  });

  return { success: true, message: "Verification code sent to " + email };
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
