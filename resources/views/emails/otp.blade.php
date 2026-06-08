<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mã xác thực - NamBo Specialties</title>
  <style>
    body { margin:0; padding:0; background:#f4f1ec; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
    .wrapper { max-width:560px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
    .header { background:linear-gradient(135deg,#2D3E27 0%,#4A6741 100%); padding:36px 40px; text-align:center; }
    .header h1 { margin:0; color:#ffffff; font-size:22px; font-weight:700; letter-spacing:0.5px; }
    .header p  { margin:6px 0 0; color:rgba(255,255,255,0.75); font-size:13px; }
    .body { padding:40px 40px 32px; }
    .greeting { font-size:16px; color:#2d1f0e; margin-bottom:16px; font-weight:600; }
    .desc { font-size:14px; color:#7A6652; line-height:1.65; margin-bottom:28px; }
    .otp-box { background:#f7f4f0; border:2px dashed #D4A373; border-radius:12px; text-align:center; padding:28px 20px; margin-bottom:28px; }
    .otp-label { font-size:12px; color:#7A6652; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:10px; }
    .otp-code { font-size:44px; font-weight:900; color:#2D3E27; letter-spacing:10px; font-family:'Courier New',monospace; }
    .expire-note { font-size:13px; color:#c07a1a; font-weight:600; margin-top:12px; }
    .warning { background:#fff8f0; border-left:3px solid #c07a1a; padding:12px 16px; border-radius:0 8px 8px 0; font-size:13px; color:#7A6652; line-height:1.55; margin-bottom:24px; }
    .footer { background:#f7f4f0; padding:20px 40px; text-align:center; }
    .footer p { margin:0; font-size:12px; color:#aaa; line-height:1.6; }
    .footer strong { color:#4A6741; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>🌴 NamBo Specialties</h1>
      <p>Marketplace Đặc Sản Miền Nam</p>
    </div>

    <div class="body">
      <p class="greeting">Xin chào {{ $hoTen }},</p>
      <p class="desc">
        Cảm ơn bạn đã đăng ký tài khoản tại <strong>NamBo Specialties</strong>.<br/>
        Vui lòng sử dụng mã OTP bên dưới để xác thực địa chỉ email của bạn.
      </p>

      <div class="otp-box">
        <div class="otp-label">Mã xác thực OTP</div>
        <div class="otp-code">{{ $otpCode }}</div>
        <div class="expire-note">⏱ Mã có hiệu lực trong <strong>5 phút</strong></div>
      </div>

      <div class="warning">
        ⚠️ Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email này.
        Không chia sẻ mã OTP với bất kỳ ai.
      </div>
    </div>

    <div class="footer">
      <p>
        Email này được gửi tự động từ hệ thống <strong>NamBo Specialties</strong>.<br/>
        Vui lòng không trả lời email này.
      </p>
    </div>
  </div>
</body>
</html>
