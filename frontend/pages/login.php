<?php require '../config/init.php'; ?>
<form action="/api/users.php?action=login" method="POST" id="loginForm">
  <input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="Mot de passe" required>
  <button type="submit">Se connecter</button>
</form>
<script>
  document.getElementById('loginForm').addEventListener('submit', async e=>{
    e.preventDefault();
    const res=await fetch('/backend/api/users.php?action=login',{method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        email:e.target.email.value,
        password:e.target.password.value
      })
    });
    const json=await res.json();
    if(json.success){
      localStorage.setItem('token', json.session_token);
      location.href='/dashboard';
    } else alert(json.error);
  });
</script>
