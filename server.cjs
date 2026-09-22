var __create = Object.create;
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __getProtoOf = Object.getPrototypeOf;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __copyProps = (to, from, except, desc) => {
  if (from && typeof from === "object" || typeof from === "function") {
    for (let key of __getOwnPropNames(from))
      if (!__hasOwnProp.call(to, key) && key !== except)
        __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
  }
  return to;
};
var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
  // If the importer is in node compatibility mode or this is not an ESM
  // file that has been converted to a CommonJS file using a Babel-
  // compatible transform (i.e. "__esModule" has not been set), then set
  // "default" to the CommonJS "module.exports" for node compatibility.
  isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
  mod
));

// server.ts
var import_express = __toESM(require("express"), 1);
var import_path = __toESM(require("path"), 1);
var import_vite = require("vite");
var import_resend = require("resend");
var resendClient = null;
function getResendClient() {
  const apiKey = process.env.RESEND_API_KEY;
  if (!apiKey) return null;
  if (!resendClient) {
    resendClient = new import_resend.Resend(apiKey);
  }
  return resendClient;
}
async function startServer() {
  const app = (0, import_express.default)();
  const PORT = 3e3;
  app.use(import_express.default.json());
  app.get("/api/health", (_req, res) => {
    res.json({ status: "ok", timestamp: (/* @__PURE__ */ new Date()).toISOString() });
  });
  const handleEnquiry = async (req, res) => {
    try {
      const {
        name,
        fullName,
        email,
        phone,
        contactNumber,
        company,
        companyName,
        service,
        subject,
        message,
        hp_field
      } = req.body;
      if (hp_field && hp_field.trim().length > 0) {
        console.warn("\u26A0\uFE0F [SPAM TRAP] Honeypot field was filled, ignoring submission.");
        return res.json({
          success: true,
          message: "Enquiry submitted successfully."
        });
      }
      const customerName = (name || fullName || "").trim();
      const customerEmail = (email || "").trim();
      const customerPhone = (phone || contactNumber || "").trim();
      const customerCompany = (company || companyName || "").trim();
      const customerService = (service || subject || "Finance Health Check & Diagnostic Review").trim();
      const customerMessage = (message || "").trim();
      if (!customerName || !customerEmail) {
        return res.status(400).json({
          success: false,
          message: "Name and email are required."
        });
      }
      const timestamp = (/* @__PURE__ */ new Date()).toLocaleString("en-US", {
        timeZone: "Asia/Dubai",
        dateStyle: "full",
        timeStyle: "long"
      }) + " (GST / UTC+4)";
      const adminEmail = process.env.ADMIN_EMAIL || process.env.CONTACT_NOTIFICATION_EMAIL || "sales@finackle.com";
      const fromEmail = process.env.FROM_EMAIL || process.env.RESEND_FROM_EMAIL || "Finackle <website@finackle.com>";
      const emailSubject = `New Website Enquiry - ${customerName}`;
      const adminHtml = `
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <title>New Website Enquiry</title>
          <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F6F8FC; margin: 0; padding: 24px; color: #13215D; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #E5EAF2; overflow: hidden; box-shadow: 0 4px 20px rgba(19, 33, 93, 0.06); }
            .header { background-color: #13215D; padding: 28px 32px; color: #ffffff; }
            .header h1 { margin: 0 0 6px 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px; text-transform: uppercase; }
            .header p { margin: 0; font-size: 13px; color: #14CBC9; font-weight: 600; letter-spacing: 0.5px; }
            .content { padding: 32px; }
            .highlight-card { background-color: #EEF2FB; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; border-left: 4px solid #14CBC9; }
            .highlight-title { font-size: 17px; font-weight: 800; color: #13215D; margin-bottom: 4px; }
            .highlight-sub { font-size: 13px; color: #475569; }
            .data-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
            .data-table td { padding: 10px 0; border-bottom: 1px solid #F1F4F9; vertical-align: top; }
            .label { width: 130px; font-size: 11px; text-transform: uppercase; font-weight: 700; color: #667085; letter-spacing: 0.5px; }
            .value { font-size: 14px; font-weight: 600; color: #13215D; }
            .value a { color: #142360; text-decoration: underline; }
            .message-container { background: #F9FAFC; border: 1px solid #E5EAF2; border-radius: 10px; padding: 16px; font-size: 14px; line-height: 1.6; color: #334155; white-space: pre-wrap; }
            .footer { padding: 20px 32px; background: #FAFBFD; border-top: 1px solid #E5EAF2; font-size: 12px; color: #667085; text-align: center; }
          </style>
        </head>
        <body>
          <div class="container">
            <div class="header">
              <h1>NEW WEBSITE ENQUIRY</h1>
              <p>Finackle Finance & Advisory Portal</p>
            </div>
            <div class="content">
              <div class="highlight-card">
                <div class="highlight-title">${customerName}</div>
                <div class="highlight-sub">Service: ${customerService}</div>
              </div>

              <table class="data-table">
                <tr>
                  <td class="label">Name:</td>
                  <td class="value">${customerName}</td>
                </tr>
                <tr>
                  <td class="label">Email:</td>
                  <td class="value"><a href="mailto:${customerEmail}">${customerEmail}</a></td>
                </tr>
                <tr>
                  <td class="label">Phone:</td>
                  <td class="value">${customerPhone || '<span style="color: #94A3B8; font-weight: normal;">Not provided</span>'}</td>
                </tr>
                <tr>
                  <td class="label">Company:</td>
                  <td class="value">${customerCompany || '<span style="color: #94A3B8; font-weight: normal;">Not provided</span>'}</td>
                </tr>
                <tr>
                  <td class="label">Service:</td>
                  <td class="value"><strong>${customerService}</strong></td>
                </tr>
                <tr>
                  <td class="label">Submitted:</td>
                  <td class="value" style="color: #667085; font-size: 13px;">${timestamp}</td>
                </tr>
              </table>

              <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #667085; letter-spacing: 0.5px; margin-bottom: 6px;">Message:</div>
              <div class="message-container">${customerMessage || "No additional message provided."}</div>
            </div>
            <div class="footer">
              Sent automatically from the Finackle Website to ${adminEmail} via Resend.
            </div>
          </div>
        </body>
        </html>
      `;
      const adminText = `
NEW WEBSITE ENQUIRY
=======================================
Name: ${customerName}
Email: ${customerEmail}
Phone: ${customerPhone || "Not provided"}
Company: ${customerCompany || "Not provided"}
Service: ${customerService}

Message:
${customerMessage || "No additional message provided."}

Submission Date/Time:
${timestamp}
=======================================
Sent via Finackle Website to ${adminEmail}
      `.trim();
      const customerHtml = `
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <title>Thank You for Contacting Finackle</title>
          <style>
            body { margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F6F8FC; color: #13215D; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #E5EAF2; overflow: hidden; box-shadow: 0 4px 20px rgba(19, 33, 93, 0.06); }
            .header { background-color: #13215D; padding: 28px 32px; color: #ffffff; text-align: left; }
            .header h1 { margin: 0 0 6px 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
            .header p { margin: 0; font-size: 13px; color: #14CBC9; font-weight: 600; letter-spacing: 0.5px; }
            .content { padding: 32px; line-height: 1.7; font-size: 15px; color: #334155; }
            .salutation { font-size: 16px; font-weight: 700; color: #13215D; margin-bottom: 16px; }
            .notice-box { background: #EEF2FB; border-radius: 10px; padding: 18px 20px; border-left: 4px solid #142360; margin: 20px 0; font-size: 14px; color: #13215D; }
            .signoff { margin-top: 24px; color: #13215D; font-weight: 600; }
            .footer { padding: 20px 32px; background: #FAFBFD; border-top: 1px solid #E5EAF2; font-size: 12px; color: #667085; text-align: center; }
          </style>
        </head>
        <body>
          <div class="container">
            <div class="header">
              <h1>FINACKLE</h1>
              <p>Finance Leadership & Strategic Advisory</p>
            </div>
            <div class="content">
              <div class="salutation">Dear ${customerName},</div>
              
              <p>Thank you for contacting Finackle.</p>
              
              <div class="notice-box">
                We have received your enquiry and our team will review it and get back to you shortly.
              </div>

              <p>If you have any immediate questions, feel free to reach us directly at <a href="mailto:${adminEmail}" style="color: #142360; font-weight: 600;">${adminEmail}</a>.</p>

              <div class="signoff">
                Regards,<br>
                <strong>Finackle Team</strong>
              </div>
            </div>
            <div class="footer">
              Finackle Financial Services &bull; Ajman Free Zone, UAE &bull; <a href="https://finackle.com" style="color: #142360; text-decoration: none;">finackle.com</a>
            </div>
          </div>
        </body>
        </html>
      `;
      const customerText = `
Dear ${customerName},

Thank you for contacting Finackle.

We have received your enquiry and our team will review it and get back to you shortly.

Regards,
Finackle Team

Finackle Financial Services
Email: ${adminEmail}
Website: https://finackle.com
      `.trim();
      const resend = getResendClient();
      if (!resend) {
        console.warn(
          "\u26A0\uFE0F [RESEND] RESEND_API_KEY is not set. Simulating successful submission.",
          { name: customerName, email: customerEmail, adminEmail }
        );
        return res.json({
          success: true,
          message: "Enquiry submitted successfully."
        });
      }
      let sendResult = await resend.emails.send({
        from: fromEmail,
        to: [adminEmail],
        replyTo: customerEmail,
        subject: emailSubject,
        html: adminHtml,
        text: adminText
      });
      if (sendResult.error && !fromEmail.includes("onboarding@resend.dev")) {
        console.warn(
          `\u26A0\uFE0F [RESEND NOTICE] Sending from ${fromEmail} failed: ${sendResult.error.message}. Retrying with default sender...`
        );
        const retryResult = await resend.emails.send({
          from: "Finackle <onboarding@resend.dev>",
          to: [adminEmail],
          replyTo: customerEmail,
          subject: emailSubject,
          html: adminHtml,
          text: adminText
        });
        if (!retryResult.error) {
          sendResult = retryResult;
        }
      }
      if (sendResult.error) {
        console.error("\u274C [RESEND ADMIN ERROR]:", sendResult.error);
        return res.status(500).json({
          success: false,
          message: "Unable to submit enquiry."
        });
      }
      try {
        let autoReplyResult = await resend.emails.send({
          from: fromEmail,
          to: [customerEmail],
          replyTo: adminEmail,
          subject: "Thank You for Contacting Finackle",
          html: customerHtml,
          text: customerText
        });
        if (autoReplyResult.error && !fromEmail.includes("onboarding@resend.dev")) {
          await resend.emails.send({
            from: "Finackle <onboarding@resend.dev>",
            to: [customerEmail],
            replyTo: adminEmail,
            subject: "Thank You for Contacting Finackle",
            html: customerHtml,
            text: customerText
          });
        }
      } catch (autoErr) {
        console.warn("\u26A0\uFE0F [RESEND AUTO-REPLY WARNING]:", autoErr);
      }
      console.log("\u2705 [RESEND SUCCESS] Enquiry processed successfully.");
      return res.json({
        success: true,
        message: "Enquiry submitted successfully."
      });
    } catch (err) {
      console.error("Error handling contact submission:", err);
      return res.status(500).json({
        success: false,
        message: "Unable to submit enquiry."
      });
    }
  };
  app.post("/api/send-enquiry.php", handleEnquiry);
  app.post("/api/contact", handleEnquiry);
  if (process.env.NODE_ENV !== "production") {
    const vite = await (0, import_vite.createServer)({
      server: { middlewareMode: true },
      appType: "spa"
    });
    app.use(vite.middlewares);
  } else {
    const distPath = import_path.default.join(process.cwd(), "dist");
    app.use(import_express.default.static(distPath));
    app.get("*all", (_req, res) => {
      res.sendFile(import_path.default.join(distPath, "index.html"));
    });
  }
  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Finackle server running on http://0.0.0.0:${PORT}`);
  });
}
startServer();
//# sourceMappingURL=server.cjs.map
