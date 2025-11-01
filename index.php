<?php
// FIX 4: Correct require_once path
require_once __DIR__ . '/config/functions.php';

// Prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// FIX 5: Image URL helper now expects paths like "config/uploads/..." and just returns them.
function get_image_url($path) {
    if (empty($path) || preg_match('/^(https?:)?\/\//', $path)) {
        return $path;
    }
    // The path is already correct as stored in configs.json (e.g., "config/uploads/...")
    return $path;
}

$event_id = $_GET['event'] ?? null;
$configs = null;
$error_message = '';
$phone_validation_required = false;
$phone_validation_error = false;

if (!$event_id) {
    $all_events = get_events();
    if (!empty($all_events)) {
        header('Location: ?event=' . $all_events[0]['id']);
        exit;
    } else {
        $error_message = 'هیچ رویدادی برای نمایش وجود ندارد.';
    }
} else {
    if (!is_valid_event_id($event_id)) {
        $error_message = 'رویداد مورد نظر یافت نشد.';
    } else {
        // Check phone validation
        $phone_settings = get_phone_validation_settings($event_id);
        
        if ($phone_settings['enabled']) {
            // Check if user is already authorized
            $is_authorized = isset($_SESSION['authorized_events'][$event_id]) && $_SESSION['authorized_events'][$event_id];
            
            if (!$is_authorized) {
                $phone_validation_required = true;
                
                // Check if form was submitted
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone_number'])) {
                    $submitted_phone = $_POST['phone_number'];
                    
                    if (is_phone_authorized($event_id, $submitted_phone)) {
                        // Authorize in session
                        if (!isset($_SESSION['authorized_events'])) {
                            $_SESSION['authorized_events'] = [];
                        }
                        $_SESSION['authorized_events'][$event_id] = true;
                        $phone_validation_required = false;
                        
                        // Redirect to remove POST data
                        header('Location: ?event=' . $event_id);
                        exit;
                    } else {
                        $phone_validation_error = true;
                        write_log('WARNING', "Unauthorized access attempt with phone for event {$event_id}");
                    }
                }
            }
        }
        
        $event_path = EVENTS_DIR . $event_id;
        $configsFile = $event_path . '/configs.json';
        if (file_exists($configsFile)) {
            $configs = json_decode(file_get_contents($configsFile), true);
        } else {
            $error_message = 'فایل تنظیمات برای این رویداد یافت نشد.';
        }

    }
}

// Ensure arrays exist to prevent errors on front-end
if (isset($configs) && !isset($configs['buttons'])) $configs['buttons'] = [];
if (isset($configs) && !isset($configs['socials'])) $configs['socials'] = [];

// Show error page
if (empty($configs) && !$phone_validation_required && !empty($error_message)):
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8">
    <title>خطا</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
    body{font-family: 'Vazirmatn', sans-serif;text-align:center;padding:40px;background:#fefefe;color:#333}
    h1{color:#d9534f;}
    </style>
    </head>
    <body>
        <h1>خطا در بارگذاری رویداد</h1>
      <p><?= htmlspecialchars($error_message ?: 'تنظیمات رویداد یافت نشد.') ?></p>
    </body>
</html>
<?php exit; endif;

// Show phone validation form
if ($phone_validation_required):
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>احراز هویت - شماره تلفن</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: <?= htmlspecialchars($configs["colors"]["bg"]) ?>;
            --title: <?= htmlspecialchars($configs["colors"]["title"]) ?>;
            --primary: <?= htmlspecialchars($configs["colors"]["primary"]) ?>;
            --primary-hover: <?= htmlspecialchars($configs["colors"]["primary-hover"]) ?>;
            --card-bg: <?= htmlspecialchars($configs["colors"]["card-bg"]) ?>;
            --placeholder: <?= htmlspecialchars($configs["colors"]["placeholder"]) ?>;
            --placeholder-border: <?= htmlspecialchars($configs["colors"]["placeholder-border"]) ?>;
            --text: <?= htmlspecialchars($configs["colors"]["text"]) ?>;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .auth-container {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .auth-icon {
            width: 80px;
            height: 80px;
            background-color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .auth-icon svg {
            width: 40px;
            height: 40px;
            color: var(--card-bg);
        }
        h1 {
            color: var(--title);
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        p {
            color: var(--text);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .input-group {
            position: relative;
        }
        input[type="text"] {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--placeholder-border);
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Vazirmatn', sans-serif;
            transition: all 0.3s;
            direction: ltr;
            text-align: center;
            letter-spacing: 1px;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-hover);
            box-shadow: 0 0 0 4px var(--placeholder);
        }
        .input-hint {
            font-size: 0.85rem;
            color: var(--text);
            margin-top: 8px;
            text-align: right;
        }
        button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            font-family: 'Vazirmatn', sans-serif;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        button:active {
            transform: translateY(0);
        }
        @media (max-width: 480px) {
            .auth-container {
                padding: 30px 20px;
            }
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
    <?php
        $customCSSFile = EVENTS_DIR . $event_id . '/custom.css';
        if (file_exists($customCSSFile) && filesize($customCSSFile) > 0):
            ?>
        <style id="custom-css">
            <?= file_get_contents($customCSSFile) ?>
        </style>
    <?php endif; ?>
</head>
<body>
    <div class="auth-container">
        <div class="auth-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <h1>احراز هویت</h1>
        <p>برای دسترسی به این پخش زنده، لطفاً شماره تلفن همراه خود را وارد کنید.</p>
        
        <?php if ($phone_validation_error): ?>
            <div class="error-message">
                شماره تلفن وارد شده مجاز به دسترسی نیست.
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="input-group">
                <input 
                    type="text" 
                    name="phone_number" 
                    placeholder="09123456789" 
                    pattern="^(0|\+98)?9\d{9}$"
                    required
                    autocomplete="tel"
                    inputmode="numeric"
                    maxlength="13"
                >
                <div class="input-hint">مثال: 09123456789 یا 989123456789+</div>
            </div>
            <button type="submit">ورود به پخش زنده</button>
        </form>
    </div>
</body>
</html>
<?php exit; endif; ?>


<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($configs["title"] ?? 'پخش زنده') ?></title>
  <link rel="icon" href="<?= htmlspecialchars(get_image_url($configs["logo"] ?? '')) ?>" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
  <style id="dynamic-style">
    :root {
        --bg: <?= htmlspecialchars($configs["colors"]["bg"]) ?>;
        --title: <?= htmlspecialchars($configs["colors"]["title"]) ?>;
        --primary: <?= htmlspecialchars($configs["colors"]["primary"]) ?>;
        --primary-hover: <?= htmlspecialchars($configs["colors"]["primary-hover"]) ?>;
        --card-bg: <?= htmlspecialchars($configs["colors"]["card-bg"]) ?>;
        --placeholder: <?= htmlspecialchars($configs["colors"]["placeholder"]) ?>;
        --placeholder-border: <?= htmlspecialchars($configs["colors"]["placeholder-border"]) ?>;
        --text: <?= htmlspecialchars($configs["colors"]["text"]) ?>;
    }
    /* ----- LOADER STYLES ----- */
    #loader-wrapper {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: var(--bg); /* Use theme background */
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.75s ease;
    }
    .loader-spinner {
        border: 8px solid rgba(0,0,0,0.1);
        border-top: 8px solid var(--primary); /* Use theme color */
        border-radius: 50%;
        width: 60px;
        height: 60px;
        animation: spin 1s linear infinite;
    }
    #loader-wrapper p {
        margin-top: 20px;
        font-size: 1.2rem;
        color: var(--text);
        font-weight: 500;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    *{padding: 0;margin: 0;outline: none;box-sizing:border-box}
    body{margin:0;font-family:'Vazirmatn',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    header{background:var(--card-bg);box-shadow:0 2px 6px rgba(0,0,0,.05);padding:.8rem 2rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10}
    header img{height:50px;margin-left: 50px;}
    header a{color:var(--title);text-decoration:none;font-weight:700;transition:.25s}
    header a:hover{color:var(--primary-hover)}
    main{flex:1;display:flex;justify-content:center;align-items:flex-start;padding:2rem 1rem}
    .card{background:var(--card-bg);width:100%;max-width:920px;border-radius:18px;box-shadow:0 12px 32px rgba(0,0,0,.06);padding:1rem;text-align:center}
    h1{color:var(--title);font-size:1.85rem}
    .card h1{margin-bottom: 10px;}
    .video{width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;margin-bottom:1rem;border:0;box-shadow:0 4px 12px rgba(0,0,0,.05)}
    .video iframe{width:100%;height:100%;border:none}
    .countdown, h3{font-size:1rem;font-weight:700;color:var(--title);margin:1rem 0 1rem}
    .countdown{display: flex;justify-content: center;align-items: center;gap: 5px;}
    .countdown div{display: flex;flex-direction: column;justify-content: center;align-items: center;gap: 5px;}
    .countdown div span{background-color:var(--primary);color: var(--text);padding: .5rem .8rem;border-radius: 8px;font-weight: bold;font-size: 2rem;width: 65px; display: flex; flex-direction: column; justify-content: center; align-items: center;}
    .countdown div span span{padding: 0;font-size: 1rem;margin-top: -8px;line-height:1.2;}
    .banners{display:flex;flex-direction:column;gap:1rem;margin-bottom:1.25rem}
    .banners .banner{background-image: url(<?= htmlspecialchars(get_image_url($configs["banner"])) ?>);background-position: center;background-size: contain;background-repeat: no-repeat;aspect-ratio: 64/19;}
    .banner{width:100%;background:var(--placeholder);border-radius:12px;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;font-weight:700}
    #preBanner{background-image: url(<?= htmlspecialchars(get_image_url($configs["preBanner"])) ?>);background-position: center;background-size: contain;background-repeat: no-repeat;}
    #endBanner{background-image: url(<?= htmlspecialchars(get_image_url($configs["endBanner"])) ?>);background-position: center;background-size: contain;background-repeat: no-repeat;}
    .actions{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;margin-bottom:1.5rem;direction:ltr;}
    .btn{padding:.4rem .5rem;border-radius:7px;background:var(--primary);color:var(--text);text-decoration:none;font-weight:700;font-size:.7rem;transition:.25s;border:none;display:inline-block;word-spacing: -2px;}
    .btn:hover{background:var(--primary-hover);transform:translateY(-2px)}
    .social{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;direction:ltr;}
    .social a{padding:.5rem .9rem;border-radius:8px;background:var(--bg);color:var(--title);text-decoration:none;font-size:.95rem;transition:.2s;border:1px solid var(--placeholder-border);display: flex;flex-direction: column;justify-content: space-around;align-items: center;gap: .2rem;line-height: 14px;}
    .social a:hover{background:var(--placeholder)}
    .social a img{width: 35px;height:35px;object-fit:contain;}
    footer{text-align:center;color:var(--title);padding:1rem}
    #subtitleBox{margin:1rem 0;padding:.75rem 1rem;background:var(--bg);border-radius:10px;min-height:40px;text-align:center; display: none; position: relative; overflow: hidden;align-items: center;}
    #subtitleText{font-weight:700;color:var(--title);font-size:1.1rem; text-wrap: nowrap; position: absolute;}
    @media (max-width:600px){
      h1{font-size:1rem}
      header a{font-size: .7rem;}
      header a img{height: 35px;margin: 0;}
      .countdown{font-size:.8rem}
      .countdown div span{font-size: 1rem;width: 45px;}
      .countdown div span span{font-size: .7rem;}
      footer{font-size: .8rem;}
      .social a img{width: 30px;height:30px;}
      .social a{font-size: .7rem;}
      #subtitleText{font-size: .8rem;}
    }
  </style>
  <?php
  $customCSSFile = EVENTS_DIR . $event_id . '/custom.css';
  if (file_exists($customCSSFile) && filesize($customCSSFile) > 0):
    ?>
    <style id="custom-css">
      <?= file_get_contents($customCSSFile) ?>
    </style>
  <?php endif; ?>
</head>
<body>
<?php
  date_default_timezone_set('Asia/Tehran');
  $LIVE_START_STR = $configs["liveStart"];
  $LIVE_END_STR   = $configs["liveEnd"];
  $LIVE_START_MS = !empty($LIVE_START_STR) ? strtotime($LIVE_START_STR) * 1000 : 0;
  $LIVE_END_MS   = !empty($LIVE_END_STR) ? strtotime($LIVE_END_STR) * 1000 : 0;
  $PLAYER_REVEAL_OFFSET = !empty($configs['playerRevealOffset']) ? intval($configs['playerRevealOffset']) : 0;
  $SERVER_NOW_MS = time() * 1000;
  ?>

<div id="loader-wrapper">
    <div class="loader-spinner"></div>
    <p>در حال بارگذاری...</p>
</div>

<div id="main-content" style="visibility: hidden; opacity: 0; transition: opacity 0.5s ease;">
  <header>
    <a href="<?= htmlspecialchars($configs["homePage"]) ?>"><img src="<?= htmlspecialchars(get_image_url($configs["logo"])) ?>" alt="لوگو"></a>
    <!-- <h1><?= htmlspecialchars($configs["title"]) ?></h1> -->
    <a href="<?= htmlspecialchars($configs["homePage"]) ?>">صفحه اصلی</a>
  </header>

  <main>
    <div class="card">

      <h1><?= htmlspecialchars($configs["title"]) ?></h1>

      <div id="preBanner" class="banner"></div>
      <div id="livePlayer" class="video" style="display:none;"><?= $configs["iframe"] ?></div>
      <div id="endBanner" class="banner" style="display:none;"></div>
      <div id="subtitleBox"><span id="subtitleText"></span></div>
      <div id="countdown" class="countdown">در حال بارگذاری تایمر…</div>
      <?php
          // FIX: Check if a banner image URL is set and not empty
          $hasBannerImage = !empty(trim($configs['banner'] ?? ''));
        
          // Only render the banner's container div if an image is set
          if ($hasBannerImage):
      ?>
      <div class="banners" id="banners">
          <?php 
              $bannerLink = trim($configs['bannerLink'] ?? '');
              $hasLink = !empty($bannerLink);
          ?>
          <?php if ($hasLink): ?>
              <a href="<?= htmlspecialchars($bannerLink) ?>" target="_blank" rel="noopener">
                  <div class="banner"></div>
              </a>
          <?php else: ?>
              <div class="banner"></div>
          <?php endif; ?>
      </div>
      <?php endif; ?>
      
      <div class="actions">
        <?php foreach ($configs['buttons'] as $button): ?>
            <?php if (!empty($button['title']) && !empty($button['link'])): ?>
                <a class='btn' href="<?= htmlspecialchars($button["link"]) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($button["title"]) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php
        $has_socials = false;
        if (!empty($configs['socials'])) {
            foreach ($configs['socials'] as $social) {
                if (!empty($social['title']) && !empty($social['link'])) {
                    $has_socials = true;
                    break;
                }
            }
        }
      ?>
      <?php if ($has_socials): ?>
          <h3>صفحات اجتماعی:</h3>
          <div class="social">
            <?php foreach ($configs['socials'] as $social): ?>
                <?php if (!empty($social['title']) && !empty($social['link'])): ?>
                    <a href="<?= htmlspecialchars($social['link']) ?>" target="_blank" rel="noopener">
                        <?php if (!empty($social['icon'])): ?>
                            <img src="<?= htmlspecialchars(get_image_url($social['icon'])) ?>">
                        <?php endif; ?>
                        <span><?= nl2br(htmlspecialchars($social['title'])) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
          </div>
      <?php endif; ?>

    </div>
  </main>
  <?php if (!empty($configs['copyright'])): ?>
    <footer><?= htmlspecialchars($configs['copyright']); ?></footer>
  <?php endif; ?>
</div>

  <script>
    // ----- LOADER SCRIPT -----
    const livePlayerDiv = document.getElementById('livePlayer');
    const iframeContent = livePlayerDiv.innerHTML; // Store the iframe's HTML
    livePlayerDiv.innerHTML = ''; // Immediately remove it from the page to prevent it from loading
    let playerLoaded = false; // A flag to ensure we only load the player once
    window.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('loader-wrapper');
        const mainContent = document.getElementById('main-content');
    
        // Fade out the loader
        loader.style.opacity = '0';

        // Make the main content visible and fade it in
        mainContent.style.visibility = 'visible';
        mainContent.style.opacity = '1';
    
        // Hide the loader completely after the fade-out transition
        setTimeout(() => {
            loader.style.display = 'none';
        }, 750); // This should match the CSS transition duration
    });

    var serverNow   = <?php echo $SERVER_NOW_MS; ?>;
    var liveStart   = <?php echo $LIVE_START_MS; ?>;
    var liveEnd     = <?php echo $LIVE_END_MS; ?>;
    var playerRevealOffset = <?php echo $PLAYER_REVEAL_OFFSET; ?>;
    var clientNow   = Date.now();
    var timeOffset  = serverNow - clientNow;

    function numberToPersian(n) { 
        return n.toString().padStart(2, '0').replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); 
    }
    
    function fmt(ms){
      var d = Math.floor(ms / 86400000);
      var h = Math.floor((ms % 86400000) / 3600000);
      var m = Math.floor((ms % 3600000) / 60000);
      var s = Math.floor((ms % 60000) / 1000);
      var result = '';
      if(d > 0) result += `<div><span>${numberToPersian(d)}<span>روز</span></span></div>`;
      if(h > 0 || d > 0) result += `<div><span>${numberToPersian(h)}<span>ساعت</span></span></div>`;
      if(m > 0 || h > 0 || d > 0) result += `<div><span>${numberToPersian(m)}<span>دقیقه</span></span></div>`;
      result += `<div><span>${numberToPersian(s)}<span>ثانیه</span></span></div>`;
      return result;
    }

    var elPre = document.getElementById("preBanner"), elLive = document.getElementById("livePlayer"), elEnd = document.getElementById("endBanner"), elCd = document.getElementById("countdown");
    function tick() {
        var now = Date.now() + timeOffset;
        var playerRevealTime = liveStart - playerRevealOffset;

        // State 1: Before Player is Revealed
        if (liveStart === 0 || now < playerRevealTime) {
            elPre.style.display  = "block";
            elLive.style.display = "none";
            elEnd.style.display  = "none";
            elCd.style.display = "flex";
            
            var distance = liveStart - now;
            elCd.innerHTML = (liveStart === 0 || distance < 0) ? "پخش زنده هنوز زمان‌بندی نشده است." : "زمان باقی‌مانده تا شروع: " + fmt(distance);
        } 
        // State 2: Player Revealed, Countdown Running
        else if (now >= playerRevealTime && now < liveStart) {
            elPre.style.display  = "none";
            elLive.style.display = "block";
            elEnd.style.display  = "none";
            elCd.style.display = "flex"; // Keep countdown visible

            // Inject the iframe if it hasn't been loaded yet
            if (!playerLoaded) {
                elLive.innerHTML = iframeContent;
                playerLoaded = true;
            }

            // Keep updating the countdown timer
            var distance = liveStart - now;
            elCd.innerHTML = (liveStart === 0 || distance < 0) ? "پخش زنده هنوز زمان‌بندی نشده است." : "زمان باقی‌مانده تا شروع: " + fmt(distance);
        }
        // State 3: Live Has Started, Countdown Hidden
        else if (now >= liveStart && now < liveEnd) {
            elPre.style.display  = "none";
            elLive.style.display = "block";
            elEnd.style.display  = "none";
            elCd.style.display = "none"; // Hide countdown

            // Inject the iframe if it hasn't been loaded yet
            if (!playerLoaded) {
                elLive.innerHTML = iframeContent;
                playerLoaded = true;
            }
        } 
        // State 4: Live Has Ended
        else {
            elPre.style.display  = "none";
            elLive.style.display = "none";
            elEnd.style.display  = "block";
            elCd.style.display = "none";
        }
    }
    tick();
    var timer = setInterval(tick, 1000);


const fetchInterval = <?= intval($configs["fetchInterval"] ?? 8000) ?>;
const scrollSpeed = <?= intval($configs["scrollSpeed"] ?? 50) ?>; // pixels per second
let subtitles = [], currentIndex = 0, cycleTimeout = null;
let lastETag = ''; // Variable to store the last ETag from the server

async function loadSubtitles() {
  try {
    // The new URL points to our PHP script
    const subtitlesUrl = 'config/get-subtitles.php?event=<?= $event_id ?>';
    
    // Send the request with the 'If-None-Match' header containing our last known ETag
    const response = await fetch(subtitlesUrl, {
        headers: {
            'If-None-Match': lastETag 
        }
    });

    // If the status is 304, it means the file hasn't changed.
    // We do nothing and save bandwidth.
    if (response.status === 304) {
        return; 
    }
    
    if (!response.ok) {
        console.error("Failed to fetch subtitles, status:", response.status);
        return;
    }

    // If we received a full response (status 200), we update our data.
    // First, we store the new ETag from the response headers for the next request.
    lastETag = response.headers.get('ETag');
    
    // Then, we get the new subtitle data.
    const data = await response.json();

    // The rest of the logic is the same: update only if data has changed.
    if (JSON.stringify(data) !== JSON.stringify(subtitles)) {
      subtitles = data;
      currentIndex = 0;
      startSubtitleCycle();
    }
  } catch (err) { 
    console.error("Error loading or parsing subtitles:", err); 
  }
}

function removeKeyframesRule(name) {
  const styleEl = document.getElementById("dynamic-style");
  if (!styleEl || !styleEl.sheet) return false;

  const sheet = styleEl.sheet;
  const rules = sheet.cssRules;

  for (let i = 0; i < rules.length; i++) {
    const rule = rules[i];
    if (rule.type === CSSRule.KEYFRAMES_RULE && rule.name === name) {
      sheet.deleteRule(i);
      return true;
    }
  }
  return false;
}

function startSubtitleCycle() {
  // Clear any pending cycle timeout
  if (cycleTimeout) {
    clearTimeout(cycleTimeout);
    cycleTimeout = null;
  }
  
  const subtitleBoxEl = document.getElementById("subtitleBox");
  const textEl = document.getElementById("subtitleText");
  
  if (!subtitles.length) { 
    removeKeyframesRule("marquee");
    textEl.innerHTML = '';
    textEl.style.animation = 'none';
    subtitleBoxEl.style.display = 'none';
    return; 
  }

  const cycle = () => {
    // Check if subtitles still exist
    if (!subtitles.length || currentIndex >= subtitles.length) {
      removeKeyframesRule("marquee");
      textEl.innerHTML = '';
      textEl.style.animation = 'none';
      subtitleBoxEl.style.display = 'none';
      return;
    }
    
    const sub = subtitles[currentIndex];
    
    // Clear and prepare
    removeKeyframesRule("marquee");
    textEl.innerHTML = '';
    textEl.style.animation = 'none';
    subtitleBoxEl.style.display = 'flex';

    // Create content
    if (sub.link) {
      const linkEl = document.createElement('a');
      linkEl.href = sub.link;
      linkEl.target = '_blank';
      linkEl.rel = 'noopener';
      linkEl.style.color = 'var(--title)';
      linkEl.style.textDecoration = 'none';
      linkEl.textContent = sub.text;
      textEl.appendChild(linkEl);
    } else {
      textEl.textContent = sub.text;
    }

    // Calculate and animate
    requestAnimationFrame(() => {
      const textWidth = textEl.offsetWidth;
      const subtitleBoxWidth = subtitleBoxEl.offsetWidth;
      const totalDistance = textWidth + subtitleBoxWidth;
      const animDuration = totalDistance / scrollSpeed;

      const sheet = document.getElementById("dynamic-style").sheet;
      const rule = `@keyframes marquee {
        0% { left: -${textWidth}px; }
        100% { left: 100%; }
      }`;
      sheet.insertRule(rule, sheet.cssRules.length);

      textEl.style.animation = `marquee ${animDuration}s linear forwards`;
      
      // Move to next subtitle after animation completes
      cycleTimeout = setTimeout(() => {
        currentIndex = (currentIndex + 1) % subtitles.length;
        cycle();
      }, animDuration * 1000);
    });
  };

  cycle();
}

loadSubtitles();
setInterval(loadSubtitles, fetchInterval);
  </script>
</body>
</html>

