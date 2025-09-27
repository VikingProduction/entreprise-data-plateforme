<?php
// register.php - Formulaire d'inscription SaaS
require '../config/init.php';
?>
<form action="/api/users.php?action=register" method="POST" id="registerForm">
  <input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="Mot de passe" minlength="8" required>
  <button type="submit">Créer un compte</button>
</form>
<script>
  document.getElementById('registerForm').addEventListener('submit', async e => {
    e.preventDefault();
    const res = await fetch('/backend/api/users.php?action=register', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        email:e.target.email.value,
        password:e.target.password.value
      })
    });
    const json = await res.json();
    if(json.success) location.href='/dashboard';
    else alert(json.error);
  });
</script>
