<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KidsGrow – Parent Sign In</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
  <style>
    /* exact same CSS as admin signin */
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
    html,body{height:100%;overflow:hidden;}
    body{display:flex;background:#f4f7ff;}
    .container{display:flex;width:100%;height:100vh;}
    .left-side{flex:1;background:#009688;color:white;padding:40px;display:flex;
      flex-direction:column;justify-content:space-between;align-items:center;}
    .brand{align-self:flex-start;display:flex;align-items:center;gap:10px;font-size:24px;font-weight:600;}
    .brand i{font-size:28px;}
    .illustration{width:400px;max-width:100%;object-fit:contain;}
    .welcome{max-width:480px;text-align:center;}
    .welcome h2{font-size:28px;font-weight:700;margin-bottom:15px;}
    .welcome p{font-size:16px;line-height:1.6;}
    .copyright{font-size:14px;opacity:.8;text-align:center;}
    .right-side{flex:1;background:white;display:flex;justify-content:center;align-items:center;padding:40px;}
    .form-container{width:100%;max-width:400px;background:white;border-radius:20px;
      padding:40px;box-shadow:0 10px 30px rgba(0,0,0,.1);}
    .form-header h2{font-size:28px;text-align:center;color:#333;font-weight:700;margin-bottom:10px;}
    .form-header p{font-size:16px;text-align:center;color:#666;margin-bottom:30px;}
    .form-group{margin-bottom:25px;position:relative;}
    .form-group label{display:block;font-size:16px;font-weight:500;color:#333;margin-bottom:8px;}
    .form-group input{width:100%;padding:12px 15px;padding-right:40px;
      border:2px solid #e1e1e1;border-radius:10px;font-size:16px;transition:.3s;}
    .form-group input:focus{border-color:#009688;outline:none;
      box-shadow:0 0 0 3px rgba(0,150,136,.1);}
    .input-wrapper{position:relative;}
    .toggle-password{position:absolute;right:15px;top:50%;
      transform:translateY(-50%);cursor:pointer;color:#555;z-index:2;}
    .action-button{width:100%;padding:14px;background:#009688;color:white;
      font-size:16px;font-weight:600;border:none;border-radius:10px;
      cursor:pointer;transition:background .3s;margin-top:10px;}
    .action-button:hover{background:#00796b;}
    .popup{position:fixed;top:20px;right:20px;padding:15px 25px;
      border-radius:10px;color:white;font-size:14px;opacity:0;
      transform:translateY(-20px);transition:all .3s ease;}
    .error-popup{background:#ff4d4d;}
    .success-popup{background:#00c853;}
    .popup.show{opacity:1;transform:translateY(0);}
    @media(max-width:768px){
      html,body{overflow:auto;}
      .container{flex-direction:column;}
      .left-side,.right-side{width:100%;flex:none;height:auto;}
      .illustration{width:80%;}
      .form-container{margin-top:30px;}
    }
  </style>
</head>
<body>

  <div class="container">
    <!-- Left Panel -->
    <div class="left-side">
      <div class="brand"><i class="fas fa-child"></i> KidsGrow</div>
      <img src="images/mother-baby.png" alt="Mother & Baby" class="illustration"/>
      <div class="welcome">
        <h2>Welcome to KidsGrow!</h2>
        <p>Empowering healthy childhoods with accurate records and personalized care. Join us in safeguarding the future, one child at a time.</p>
      </div>
      <div class="copyright">&copy; 2025 KidsGrow Educational Solutions. All Rights Reserved.</div>
    </div>

    <!-- Right Panel -->
    <div class="right-side">
      <div class="form-container">

        <!-- SIGN IN -->
        <div id="loginSection">
          <div class="form-header">
            <h2>Sign In</h2>
            <p>Access your <strong>KidsGrow</strong> account</p>
          </div>
          <form id="loginForm">
            <input type="hidden" name="action" value="sign_in">
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" placeholder="Enter your Email Address" required>
            </div>
            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrapper">
                <input type="password" id="password" name="password" placeholder="Enter your Password" required>
                <span class="toggle-password" onclick="togglePasswordVisibility('password',this)"><i class="fas fa-eye"></i></span>
              </div>
            </div>
            <button type="submit" class="action-button">Sign In</button>
            <p style="text-align:center;margin-top:12px;cursor:pointer;color:#009688;"
               onclick="showSection('forgotSection')">Forgot Password?</p>
          </form>
        </div>

        <!-- FORGOT PASSWORD -->
        <div id="forgotSection" style="display:none">
          <div class="form-header">
            <h2>Forgot Password</h2>
            <p>Enter your email to receive an OTP</p>
          </div>
          <div class="form-group">
            <label for="forgotEmail">Email Address</label>
            <input type="email" id="forgotEmail" placeholder="Enter your Email Address" required>
          </div>
          <button class="action-button" onclick="sendOTP()">Send OTP</button>
          <p style="text-align:center;margin-top:12px;cursor:pointer;color:#009688;"
             onclick="showSection('loginSection')">Back to Sign In</p>
        </div>

        <!-- OTP VERIFICATION -->
        <div id="otpSection" style="display:none">
          <div class="form-header">
            <h2>OTP Verification</h2>
            <p>Enter the OTP sent to your email</p>
          </div>
          <div class="form-group">
            <label for="otp">OTP</label>
            <input type="text" id="otp" placeholder="Enter OTP" required>
          </div>
          <button class="action-button" onclick="verifyOTP()">Verify OTP</button>
          <p style="text-align:center;margin-top:12px;cursor:pointer;color:#009688;"
             onclick="showSection('forgotSection')">Back</p>
        </div>

        <!-- RESET PASSWORD -->
        <div id="resetSection" style="display:none">
          <div class="form-header">
            <h2>Reset Password</h2>
            <p>Enter your new password</p>
          </div>
          <div class="form-group">
            <label for="newPassword">New Password</label>
            <div class="input-wrapper">
              <input type="password" id="newPassword" placeholder="Enter new password" required>
              <span class="toggle-password" onclick="togglePasswordVisibility('newPassword',this)"><i class="fas fa-eye"></i></span>
            </div>
          </div>
          <button class="action-button" onclick="resetPassword()">Reset Password</button>
          <p style="text-align:center;margin-top:12px;cursor:pointer;color:#009688;"
             onclick="showSection('loginSection')">Back to Sign In</p>
        </div>

      </div>
    </div>
  </div>

  <!-- popups -->
  <div id="errorPopup" class="popup error-popup"></div>
  <div id="successPopup" class="popup success-popup"></div>

  <script>
    function showSection(id){
      ['loginSection','forgotSection','otpSection','resetSection'].forEach(s=>{
        document.getElementById(s).style.display='none';
      });
      document.getElementById(id).style.display='block';
    }
    function togglePasswordVisibility(field,btn){
      const pw = document.getElementById(field);
      const icon = btn.querySelector('i');
      if(pw.type==='password'){
        pw.type='text'; icon.classList.replace('fa-eye','fa-eye-slash');
      } else {
        pw.type='password'; icon.classList.replace('fa-eye-slash','fa-eye');
      }
    }
    function showPopup(msg,type){
      const pop=document.getElementById(type==='error'?'errorPopup':'successPopup');
      pop.textContent=msg; pop.classList.add('show');
      setTimeout(()=>pop.classList.remove('show'),3000);
    }

    // SIGN IN
    document.getElementById('loginForm').addEventListener('submit',async e=>{
      e.preventDefault();
      const data=new URLSearchParams(new FormData(e.target));
      data.append('action','sign_in');
      try {
        const res=await fetch('user_auth.php?no_rewrite=1',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:data.toString()
        });
        const json=await res.json();
        if(!json.success) return showPopup(json.message,'error');
        if(json.role!=='Parent') return showPopup('Only parents may sign in here','error');
        showPopup(json.message,'success');
        setTimeout(()=>location.href='dashboard.php',500);
      } catch{
        showPopup('Server error, try again','error');
      }
    });

    // SEND OTP
    function sendOTP(){
      const email=document.getElementById('forgotEmail').value.trim();
      if(!email) return showPopup('Enter your email','error');
      const data=new URLSearchParams({action:'send_otp',email});
      fetch('user_auth.php?no_rewrite=1',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:data.toString()
      })
      .then(r=>r.json())
      .then(j=>{
        if(!j.success) return showPopup(j.message,'error');
        showPopup(j.message,'success');
        showSection('otpSection');
      })
      .catch(()=>showPopup('Server error','error'));
    }

    // VERIFY OTP
    function verifyOTP(){
      const otp=document.getElementById('otp').value.trim();
      if(!otp) return showPopup('Enter the OTP','error');
      const data=new URLSearchParams({action:'verify_otp',otp});
      fetch('user_auth.php?no_rewrite=1',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:data.toString()
      })
      .then(r=>r.json())
      .then(j=>{
        if(!j.success) return showPopup(j.message,'error');
        showPopup(j.message,'success');
        showSection('resetSection');
      })
      .catch(()=>showPopup('Server error','error'));
    }

    // RESET PASSWORD
    function resetPassword(){
      const pwd=document.getElementById('newPassword').value;
      if(!pwd) return showPopup('Enter new password','error');
      const data=new URLSearchParams({action:'reset_password',new_password:pwd});
      fetch('user_auth.php?no_rewrite=1',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:data.toString()
      })
      .then(r=>r.json())
      .then(j=>{
        if(!j.success) return showPopup(j.message,'error');
        showPopup(j.message,'success');
        setTimeout(()=>showSection('loginSection'),1000);
      })
      .catch(()=>showPopup('Server error','error'));
    }
  </script>
</body>
</html>
