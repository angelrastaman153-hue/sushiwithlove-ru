<?php
require_once __DIR__ . '/api/db.php';

$token = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/', '', $_GET['t']) : '';
$review = null;
$error  = '';

if (!$token) {
    $error = 'Ссылка недействительна.';
} else {
    $stmt = db()->prepare('SELECT * FROM reviews WHERE token = ?');
    $stmt->execute(array($token));
    $review = $stmt->fetch();
    if (!$review) {
        $error = 'Ссылка недействительна или устарела.';
    }
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Отзывы с Любовью — Суши с Любовью</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,sans-serif;background:#111;color:#eee;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:20px;padding:32px 28px;max-width:420px;width:100%;text-align:center}
  .logo{font-size:2rem;margin-bottom:8px}
  .brand{color:#e8a847;font-weight:700;font-size:1.1rem;margin-bottom:4px}
  .subtitle{color:#666;font-size:0.85rem;margin-bottom:28px}
  h2{font-size:1.25rem;font-weight:700;margin-bottom:8px;line-height:1.3}
  p{color:#999;font-size:0.9rem;line-height:1.5}

  /* Шаги */
  .step{display:none}
  .step.active{display:block}

  /* Звёзды */
  .stars{display:flex;justify-content:center;gap:10px;margin:24px 0 8px;flex-direction:row-reverse}
  .stars input{display:none}
  .stars label{font-size:2.6rem;cursor:pointer;color:#333;transition:color .15s;line-height:1}
  .stars input:checked ~ label,
  .stars label:hover,
  .stars label:hover ~ label{color:#e8a847}
  .star-hint{font-size:0.82rem;color:#555;margin-bottom:20px;min-height:20px;transition:all .2s}

  /* Кнопки */
  .btn{display:block;width:100%;padding:14px;border-radius:12px;border:none;font-size:1rem;font-weight:700;cursor:pointer;transition:all .18s;text-decoration:none;text-align:center}
  .btn-primary{background:#e8a847;color:#111}
  .btn-primary:hover{background:#d4913a}
  .btn-ghost{background:transparent;border:1px solid #333;color:#aaa;margin-top:10px}
  .btn-ghost:hover{border-color:#555;color:#eee}
  .btn:disabled{opacity:.5;cursor:not-allowed}

  /* Площадки */
  .platforms{display:flex;flex-direction:column;gap:10px;margin-top:20px}
  .platform-btn{display:flex;align-items:center;gap:12px;padding:13px 16px;border-radius:12px;border:1px solid #2a2a2a;background:#161616;color:#eee;text-decoration:none;font-size:0.95rem;font-weight:500;transition:border-color .15s}
  .platform-btn:hover{border-color:#e8a847}
  .platform-icon{font-size:1.4rem;width:28px;text-align:center}

  /* Поле */
  textarea{width:100%;background:#161616;border:1px solid #2a2a2a;border-radius:12px;padding:13px;color:#eee;font-size:0.95rem;font-family:inherit;resize:none;margin-top:16px;transition:border-color .15s;outline:none}
  textarea:focus{border-color:#e8a847}
  textarea::placeholder{color:#444}

  /* Итог */
  .done-icon{font-size:3.5rem;margin-bottom:12px}
  .done-title{font-size:1.3rem;font-weight:700;margin-bottom:8px}
  .done-sub{color:#888;font-size:0.9rem}

  .error-page{text-align:center;padding:40px 0}
  .error-page .ico{font-size:3rem;margin-bottom:12px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">🍣❤️</div>
  <div class="brand">Отзывы с Любовью</div>
  <div class="subtitle">Суши с Любовью · г. Курган</div>

<?php if ($error): ?>
  <div class="error-page">
    <div class="ico">😔</div>
    <h2><?php echo htmlspecialchars($error); ?></h2>
  </div>

<?php elseif ($review['answered_at']): ?>
  <div class="done-icon">🙏</div>
  <div class="done-title">Отзыв уже получен!</div>
  <div class="done-sub" style="margin-top:8px">Вы уже оставили отзыв. Спасибо — это очень важно для нас!</div>

<?php else: ?>

  <!-- ШАГ 1: Звёзды -->
  <div class="step active" id="step2">
    <h2>Оцените заказ</h2>
    <p style="margin-top:6px">Нажмите на звезду</p>
    <div class="stars" id="starsBlock">
      <input type="radio" name="rating" id="s5" value="5"><label for="s5" title="Отлично!">★</label>
      <input type="radio" name="rating" id="s4" value="4"><label for="s4" title="Хорошо">★</label>
      <input type="radio" name="rating" id="s3" value="3"><label for="s3" title="Нормально">★</label>
      <input type="radio" name="rating" id="s2" value="2"><label for="s2" title="Плохо">★</label>
      <input type="radio" name="rating" id="s1" value="1"><label for="s1" title="Ужасно">★</label>
    </div>
    <div class="star-hint" id="starHint"></div>
    <button class="btn btn-primary" id="ratingBtn" onclick="submitRating()" disabled>Продолжить →</button>
  </div>

  <!-- ШАГ 3А: 5 звёзд — площадки -->
  <div class="step" id="step3a">
    <div class="done-icon">🎉</div>
    <h2>Спасибо за отличную оценку!</h2>
    <p style="margin-top:8px">Поделитесь отзывом — это помогает нам расти:</p>
    <div class="platforms">
      <a class="platform-btn" href="https://2gis.ru/kurgan/firm/70000001017896762/tab/reviews" target="_blank" rel="noopener">
        <span class="platform-icon">📍</span>2ГИС
      </a>
      <a class="platform-btn" href="https://yandex.ru/maps/org/sushi_s_lyubovyu/5625179727/reviews/" target="_blank" rel="noopener">
        <span class="platform-icon">🗺</span>Яндекс Карты
      </a>
      <a class="platform-btn" href="https://g.page/r/CUStJVtLggqBEAE/review" target="_blank" rel="noopener">
        <span class="platform-icon">🔍</span>Google Maps
      </a>
      <a class="platform-btn" href="https://vk.com/reviews-50839877" target="_blank" rel="noopener">
        <span class="platform-icon">💬</span>ВКонтакте
      </a>
    </div>
  </div>

  <!-- ШАГ 3Б: 1-4 звезды — что пошло не так -->
  <div class="step" id="step3b">
    <div style="font-size:2.5rem;margin-bottom:12px">😔</div>
    <h2>Жаль, что что-то пошло не так</h2>
    <p style="margin-top:6px">Расскажите подробнее — мы разберёмся и исправим.</p>
    <textarea id="commentArea" rows="4" placeholder="Например: холодная еда, долгая доставка, неправильный заказ..."></textarea>
    <button class="btn btn-primary" style="margin-top:16px" onclick="submitComment()">Отправить →</button>
    <button class="btn btn-ghost" onclick="skipComment()">Пропустить</button>
  </div>

  <!-- ШАГ 4: Финал -->
  <div class="step" id="step4">
    <div class="done-icon">🙏</div>
    <div class="done-title">Спасибо за честность!</div>
    <div class="done-sub" style="margin-top:8px">Мы изучим ситуацию и станем лучше. Ценим каждый отзыв.</div>
  </div>

<?php endif; ?>
</div>

<script>
var TOKEN = <?php echo json_encode($review ? $review['token'] : ''); ?>;
var selectedRating = 0;
var ratingSubmitted = false;

// Звёзды
var hints = {5:'Отлично! 🎉', 4:'Хорошо 👍', 3:'Нормально 😐', 2:'Плохо 😕', 1:'Ужасно 😤'};
var starInputs = document.querySelectorAll('.stars input');
var starHint   = document.getElementById('starHint');
var ratingBtn  = document.getElementById('ratingBtn');

starInputs.forEach(function(inp) {
  inp.addEventListener('change', function() {
    selectedRating = parseInt(inp.value);
    starHint.textContent = hints[selectedRating] || '';
    ratingBtn.disabled = false;
  });
});

function submitRating() {
  if (!selectedRating || ratingSubmitted) return;
  ratingSubmitted = true;
  ratingBtn.disabled = true;
  ratingBtn.textContent = '...';

  fetch('/api/reviews/submit.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({token: TOKEN, rating: selectedRating, consent: 1})
  })
  .then(function(r){ return r.json(); })
  .then(function(res) {
    if (res.ok) {
      if (selectedRating >= 5) {
        setStep('step3a');
      } else {
        setStep('step3b');
      }
    }
  })
  .catch(function() { ratingBtn.disabled = false; ratingBtn.textContent = 'Продолжить →'; ratingSubmitted = false; });
}

// Шаг 3Б: комментарий
function submitComment() {
  var comment = document.getElementById('commentArea').value.trim();
  fetch('/api/reviews/submit.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({token: TOKEN, rating: selectedRating, comment: comment, consent: 1})
  })
  .then(function(){ setStep('step4'); })
  .catch(function(){ setStep('step4'); });
}

function skipComment() {
  setStep('step4');
}

function setStep(id) {
  document.querySelectorAll('.step').forEach(function(s){ s.classList.remove('active'); });
  var el = document.getElementById(id);
  if (el) el.classList.add('active');
}
</script>
</body>
</html>
