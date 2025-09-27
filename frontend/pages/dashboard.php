<?php require '../config/init.php';
$token = $_COOKIE['token'] ?? localStorage.getItem('token') ?? '';
?>
<h1>Dashboard</h1>
<div id="stats"></div>
<script>
  (async ()=>{
    const res = await fetch('/backend/api/users.php?action=stats',{
      headers:{'Authorization':'Bearer '+localStorage.getItem('token')}
    });
    const data=await res.json();
    if(data.success){
      document.getElementById('stats').innerHTML=`
        Recherches 30j: ${data.daily_stats.map(d=>d.recherches).reduce((a,b)=>a+b,0)}<br>
        Documents 30j: ${data.daily_stats.map(d=>d.documents).reduce((a,b)=>a+b,0)}
      `;
    } else alert(data.error);
  })();
</script>
