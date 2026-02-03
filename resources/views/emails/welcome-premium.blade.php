<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Welcome to Tohfaah</title>
</head>

<body style="
  margin:0;
  padding:0;
  background-color:#fdf2f8;
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
">

<!-- OUTER WRAPPER -->
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0; background-color:#fdf2f8;">
  <tr>
    <td align="center">

      <!-- EMAIL CARD -->
      <table width="600" cellpadding="0" cellspacing="0" style="
        background:#ffffff;
        border-radius:20px;
        overflow:hidden;
        box-shadow:0 20px 40px rgba(0,0,0,0.08);
      ">

        <!-- HEADER -->
        <tr>
          <td style="
            background:linear-gradient(135deg,#fce7f3,#fbcfe8);
            padding:36px;
            text-align:center;
          ">

            <!-- LOGO -->
            <img
              src="https://tohfaah.online/assests/android-chrome-192x192.png"
              alt="Tohfaah"
              width="120"
              height="120"
              style="
                display:block;
                margin:0 auto 18px;
                border-radius:24px;
                background:#ffffff;
                box-shadow:0 8px 20px rgba(0,0,0,0.08);
              "
            />

            <h1 style="
              margin:0;
              font-size:28px;
              font-weight:600;
              color:#9d174d;
            ">
              Welcome to Tohfaah 💖
            </h1>

            <p style="
              margin:10px 0 0;
              font-size:16px;
              color:#831843;
            ">
              Thoughtful digital gifts, made with love
            </p>
          </td>
        </tr>

        <!-- CONTENT -->
        <tr>
          <td style="
            padding:36px;
            color:#374151;
            font-size:16px;
            line-height:1.6;
          ">

            <p style="margin-top:0;">
              Hi {{ $user->full_name ?? 'there' }},
            </p>

            <p>
              We’re genuinely happy you’re here ✨  
              <strong>Tohfaah</strong> was created with one simple belief:
            </p>

            <p style="
              font-size:18px;
              font-style:italic;
              color:#9d174d;
              text-align:center;
              margin:24px 0;
            ">
              “The smallest gestures often mean the most.”
            </p>

            <p>
              Whether it’s a kiss 💋, a warm hug 🤍, flowers 🌸, or a heartfelt surprise —
              Tohfaah helps you express emotions in a way that feels personal, beautiful, and real.
            </p>

            <!-- CTA BUTTON -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:32px 0;">
              <tr>
                <td align="center">
                  <a
                    href="https://tohfaah.com"
                    style="
                      background:linear-gradient(135deg,#ec4899,#db2777);
                      color:#ffffff;
                      text-decoration:none;
                      padding:16px 38px;
                      border-radius:999px;
                      font-size:17px;
                      font-weight:600;
                      display:inline-block;
                    "
                  >
                    Create Your First Gift 💝
                  </a>
                </td>
              </tr>
            </table>

            <p>
              No noise. No pressure.  
              Just moments that matter.
            </p>

            <p style="margin-bottom:0;">
              With love,<br/>
              <strong style="color:#9d174d;">Team Tohfaah</strong> 💕
            </p>

          </td>
        </tr>

        <!-- DIVIDER -->
        <tr>
          <td style="padding:0 36px;">
            <hr style="border:none; border-top:1px solid #fbcfe8;" />
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="
            padding:24px 36px;
            font-size:13px;
            color:#9ca3af;
            text-align:center;
          ">
            <p style="margin:0;">
              You received this email because you signed up at <strong>tohfaah.com</strong>
            </p>
            <p style="margin:8px 0 0;">
              © {{ date('Y') }} Tohfaah · All rights reserved
            </p>
          </td>
        </tr>

      </table>

      <div style="height:24px;"></div>

    </td>
  </tr>
</table>

</body>
</html>
