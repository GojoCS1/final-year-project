<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DHRS – Adigrat University General Hospital</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
  --sky-blue:   #87CEEB; --sky-dark:   #005f73; --green-dark: #134d2c; --gold:  #c8a84b; --white: #ffffff; --text-dark: #333333;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: 'Segoe UI', Tahoma, sans-serif;
  background: var(--white);
  overflow-x: hidden;
  display: flex;          
  flex-direction: column; 
  min-height: 100vh;   

/* ===== 1. TOP BAR (SKY BLUE) ===== */
.top-bar { background: #87CEEB; color: var(--text-dark); padding: 4px 2%; display: flex; justify-content: space-between; font-size: 0.80rem; font-weight: 600; border-bottom: 1px solid rgba(0,0,0,0.1);
}
.top-bar i { color: var(--green-dark); margin-right: 5px; }

/* ===== 2. HEADER (HOSPITAL NAME AREA) ===== */
header { background: #005f73; padding: 15px 5%; display: flex; align-items: center; justify-content: center;
}
.header-brand { display: flex; align-items: center; gap: 20px; }
.header-brand img { width: 100px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

.brand-text h1 { font-family: 'Algerian', serif; font-size: 2.8rem; color: white; text-transform: uppercase; letter-spacing: 2px; text-align: center; line-height: 1.1; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}
.brand-text h1:last-of-type { font-size: 2.2rem; margin-top: 5px; }

/* ===== 3. NAVIGATION (SKY BLUE) ===== */
nav { background: var(--sky-blue); height: 30px; display: flex; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.nav-inner { width: 100%; padding: 0 5%; display: flex; justify-content: flex-end;
}
.nav-login-btn { background: blue; color: white; padding: 4px 22px; border: chartreuse; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 0.9rem; transition: 0.3s;
}
.nav-login-btn:hover { background: #003d4d; transform: scale(1.05); }

/* ===== 4. HERO SLIDER (TEXT LEFT & ARROWS) ===== */
.hero-slider {
  position: relative;
  width: 100%;
  flex: 1;          
  overflow: hidden;
}
.slide {  position: absolute; inset: 0; opacity: 0;  transition: opacity 1s ease;  background-size: cover;  background-position: center top; 
}
.slide.active { opacity: 1; }
.slide:nth-child(1) { background-image: url('Photo/Ngate.jpg'); }
.slide:nth-child(2) { background-image: url('Photo/gate.jpg'); }
.slide:nth-child(3) { background-image: url('Photo/place.jpg'); }

.slide-overlay { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.4); display: flex; align-items: center; padding-left: 80px;
}
.slide-text { max-width: 650px; color: white; text-align: left;
}
.slide-text h2 { font-size: 2.8rem; line-height: 1.2; text-shadow: 2px 2px 10px rgba(0,0,0,0.5); }

/* SLIDER ARROWS */
.slider-prev, .slider-next { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.5); width: 50px; height: 50px; border-radius: 50%; font-size: 1.5rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: 0.3s; z-index: 10;
}
.slider-prev:hover, .slider-next:hover { background: rgba(255,255,255,0.4); }
.slider-prev { left: 20px; }
.slider-next { right: 20px; }

/* ===== 5. SERVICES STRIP ===== */
.services-strip { display: flex; flex-wrap: wrap; background: #134d2c;
}
.srv-card { flex: 1; min-width: 300px; padding: 20px; border-right: 5px solid rgba(255,255,255,0.1); color: white; display: flex; gap: 20px;
}
.srv-num { font-size: 2.5rem; font-weight: bold; color: var(--gold); opacity: 0.8; }
.srv-info h3 { color: var(--gold); margin-bottom: 8px; }
.srv-info p { font-size: 0.9rem; line-height: 1.6; opacity: 0.9; }

/* LOGIN MODAL */
.login-overlay { display: none; position: fixed; inset: 0;  background: rgba(0,0,0,0.7); z-index: 2000; justify-content: center; align-items: center;
}
.login-overlay.open { display: flex; }
.login-container { 
  background: white; width: 420px; padding: 40px; border-radius: 12px; position: relative; animation: zoomIn 0.83s;
}
@keyframes zoomIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.close-btn { position: absolute; top: 15px; right: 20px; font-size: 25px; cursor: pointer; color: #888; }
.login-container h3 { text-align: center; margin-bottom: 25px; color: #003366; border-bottom: 3px solid var(--sky-blue); padding-bottom: 10px; }
.input-group { margin-bottom: 20px; }
.input-group input {
  width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 1.2rem;
}

.submit-btn { width: 100%; padding: 20px; background: #134d2c; color: white; border: none; border-radius: 16px; font-weight: bold; cursor: pointer; font-size: 1rem; }

footer {
  background: #0c2b19;
  color: white;
  padding: 15px;
  text-align: center;
  font-size: 1.02rem;
  margin-top: auto;
}

@media (max-width: 768px) {
  .brand-text h1 { font-size: 1.5rem; }
  .slide-overlay { padding-left: 30px; }
  .slide-text h2 { font-size: 1.8rem; }
  .srv-card { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); }
  .slider-prev, .slider-next { width: 40px; height: 40px; font-size: 1.2rem; }
}
</style>
</head>

<body>

<header>
  <div class="header-brand">
    <img src="Photo/logo.png" alt="Logo">
    <div class="brand-text">
      <h1>ADIGRAT GENERAL HOSPITAL</h1>
      <h1>ሓፈሻዊ ሆስፒታል ዓዲግራት</h1>
    </div>
  </div>
</header>

<nav>
  <div class="nav-inner">
    <a href="#" class="nav-login-btn" onclick="openLogin(); return false;">LOGIN</a>
  </div>
</nav>

<section class="hero-slider">
  <div class="slide active">
    <div class="slide-overlay">
      <div class="slide-text">
        <h2>Excellence in Education, Academics, Research and Community Engagement</h2>
      </div>
    </div>
  </div>
  <div class="slide">
    <div class="slide-overlay">
      <div class="slide-text">
        <h2>Modern Healthcare Data Management for Better Patient Care</h2>
      </div>
    </div>
  </div>
  <div class="slide">
    <div class="slide-overlay">
      <div class="slide-text">
        <h2>A Vibrant Learning Environment</h2>
      </div>
    </div>
  </div>

  <!-- SLIDER ARROWS RETURNED -->
  <button class="slider-prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
  <button class="slider-next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>
</section>

<footer>
  <p>2026 Adigrat General Hospital</p>
</footer>

<div class="login-overlay" id="loginOverlay">
  <div class="login-container">
    <span class="close-btn" onclick="closeLogin()">&times;</span>
    <h3>AHMS Staff</h3>
    <div class="input-group"><input type="text" id="username" placeholder="Username"></div>
    <div class="input-group" style="position: relative;">
  <input type="password" id="password" placeholder="Password">

  <!-- eye sign (Emoji) -->
  <span id="eyeIcon" onclick="togglePass()" 
        style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem; user-select: none;">
    👁️
  </span>
</div>
    <button class="submit-btn" onclick="login()">Login</button>
    <p id="msg" style="color:red; text-align:center; margin-top:10px;"></p>
  </div>
</div>

<script>
  // to unseen the password if default back button clicked
  window.history.forward();
  function noBack() { window.history.forward(); }

  window.onpageshow = function(event) {
      document.getElementById("username").value = "";
      document.getElementById("password").value = "";
      document.getElementById("msg").innerText = "";
  };
  
  function openLogin() { document.getElementById("loginOverlay").classList.add("open"); }
  function closeLogin() { document.getElementById("loginOverlay").classList.remove("open"); }

  let current = 0;
  const slides = document.querySelectorAll(".slide");
  
  function changeSlide(dir) {
    slides[current].classList.remove("active");
    current = (current + dir + slides.length) % slides.length;
    slides[current].classList.add("active");
  }

  // Auto slide
  let autoSlide = setInterval(() => changeSlide(1), 8000);

  // Stop auto slide on manual click
  document.querySelectorAll('.slider-prev, .slider-next').forEach(btn => {
    btn.addEventListener('click', () => clearInterval(autoSlide));
  });

  function login() {
    const u = document.getElementById("username").value;
    const p = document.getElementById("password").value;
    const m = document.getElementById("msg");
    if(!u || !p) { m.innerText = "Please enter credentials"; return; }
    
    const fd = new FormData();
    fd.append("username", u); fd.append("password", p);

    fetch("login_process.php", { method: "POST", body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.status === "success") window.location.href = d.redirect;
        else m.innerText = d.message;
      }).catch(() => m.innerText = "Connection error!");
  }
  document.addEventListener("keydown", function(event) {
    const overlay = document.getElementById("loginOverlay");
    
   // Enter እንዲሰራ
    if (overlay.classList.contains("open")) {
        if (event.key === "Enter") {
            event.preventDefault(); 
            login(); 
        }
    }
});
// to show and hidden password
function togglePass() {
    const passInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (passInput.type === 'password') {
      passInput.type = 'text';
      eyeIcon.innerText = '🙈';
    } else {
      passInput.type = 'password';
      eyeIcon.innerText = '👁️';
    }
  }

</script>
</body>
</html>