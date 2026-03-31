<?php
$file = 'c:/Users/VICTUS/Desktop/my pproject/xamp/htdocs/ecombusiness/includes/navbar.php';
$content = file_get_contents($file);
$addition = "
  // --- SITE NOTIFICATIONS ---
  function checkSiteNotifications() {
    fetch(_siteUrl + '/ajax/get_notifications.php')
      .then(r => r.json())
      .then(data => {
        if (data.success && data.notification) {
          const n = data.notification;
          const lastSeen = localStorage.getItem('last_seen_site_notif') || 0;
          if (Number(n.id) > Number(lastSeen)) {
            if (lastSeen > 0) showSiteAlert(n);
            localStorage.setItem('last_seen_site_notif', n.id);
          }
        }
      }).catch(e => console.warn('Notif check error'));
  }

  function showSiteAlert(n) {
    if (n.image_url) {
      const div = document.createElement('div');
      div.style = 'position:fixed; bottom:20px; left:20px; z-index:99999; max-width:350px; width:calc(100% - 40px); background:var(--bg-card); border-radius:15px; border:1px solid var(--border); box-shadow:0 15px 45px rgba(0,0,0,0.3); overflow:hidden; animation:slide-up-notif 0.5s ease-out;';
      div.innerHTML = `
        <div style='position:relative;'><img src='\${n.image_url}' style='width:100%; height:180px; object-fit:cover;'><button onclick='this.parentElement.parentElement.remove()' style='position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.5); border:none; color:#fff; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;'><i class='bi bi-x-lg'></i></button></div>
        <div style='padding:1.25rem;'><h4 style='font-weight:800; font-size:1.1rem; margin-bottom:0.5rem; color:var(--text-primary); line-height:1.2;'>\${n.title}</h4><p style='font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.25rem; line-height:1.5;'>\${n.message.substring(0, 120)}\${n.message.length > 120 ? '...' : ''}</p><a href='\${n.target_url || _siteUrl + '/products'}' class='btn-primary-luxury' style='width:100%; justify-content:center; padding:0.75rem; font-size:0.85rem; text-decoration:none; display:flex; align-items:center; gap:0.5rem; font-weight:700;'>Take the Offer <i class='bi bi-arrow-right'></i></a></div>\`;
      document.body.appendChild(div);
    } else { showToast(\`<b>\${n.title}</b><br>\${n.message}\`, 'info'); }
  }
  const snStyle = document.createElement('style');
  snStyle.innerHTML = '@keyframes slide-up-notif { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }';
  document.head.appendChild(snStyle);

  setInterval(checkSiteNotifications, 60000);
  setTimeout(checkSiteNotifications, 2000);\n";

$newContent = str_replace('</script>', $addition . '</script>', $content);
file_put_contents($file, $newContent);
echo "Successfully updated navbar.php!";
?>
