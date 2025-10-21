document.addEventListener("DOMContentLoaded", function () {
    let cssEditor = null;

    // Fix: Initialize CodeMirror properly
    function initializeCSSEditor() {
      if (cssEditor) return; // Already initialized

      const textarea = document.getElementById("custom-css-editor");
      if (!textarea) return;

      cssEditor = CodeMirror.fromTextArea(textarea, {
        mode: "css",
        theme: "monokai",
        lineNumbers: true,
        lineWrapping: true,
        autoCloseBrackets: true,
        matchBrackets: true,
        smartIndent: true,
        indentUnit: 4,
        tabSize: 4,
        direction: "ltr",
        viewportMargin: Infinity,
        extraKeys: {
          "Ctrl-Space": "autocomplete",
        },
        hintOptions: {
          completeSingle: false,
          closeOnUnfocus: true,
        },
      });

      // FIX: Refresh after initialization to fix layout issues
      setTimeout(() => {
        if (cssEditor) {
          cssEditor.refresh();
        }
      }, 100);

      // Mark form as dirty when CSS changes
      cssEditor.on("change", function () {
        setDirty();
      });

      // FIX: Enable autocomplete on typing
      cssEditor.on("inputRead", function (cm, change) {
        if (change.text[0] && /[a-zA-Z-]/.test(change.text[0])) {
          cm.showHint({ completeSingle: false });
        }
      });
    }

function generatePreviewHTML(previewState = 'live') {
    // Helper to resolve image URLs
    const resolveImageUrl = (url) => {
        if (!url || url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//') || url.startsWith('data:')) {
            return url;
        }
        // Create a full absolute URL.
        // Assumes dashboard is at /config/* and we need to get to the root /
        const baseUrl = window.location.href.substring(0, window.location.href.lastIndexOf('/config/') + 8)
        return `${baseUrl}/${url}`;
    };

    // Collect form data
    const formData = new FormData(document.getElementById('settings-form'));
    
    // Get CodeMirror content if available
    if (cssEditor) {
        formData.set('custom_css', cssEditor.getValue());
    }
    
    // Build configuration object from form
    const config = {
        title: formData.get('title') || 'پخش زنده',
        homePage: formData.get('homePage') || '#',
        iframe: formData.get('iframe') || '',
        copyright: formData.get('copyright') || '',
        liveStart: formData.get('liveStart'),
        scrollSpeed: formData.get('scrollSpeed') || 50,
        logo: resolveImageUrl(formData.get('logo_url')) || '',
        preBanner: resolveImageUrl(formData.get('preBanner_url')) || '',
        endBanner: resolveImageUrl(formData.get('endBanner_url')) || '',
        banner: resolveImageUrl(formData.get('banner_url')) || '',
        bannerLink: formData.get('bannerLink') || '',
        colors: {
            bg: formData.get('bg') || '#ffffff',
            title: formData.get('title-color') || '#000000',
            primary: formData.get('primary') || '#4caf50',
            'primary-hover': formData.get('primary-hover') || '#45a049',
            'card-bg': formData.get('card-bg') || '#f8f9fa',
            placeholder: formData.get('placeholder') || '#e9ecef',
            'placeholder-border': formData.get('placeholder-border') || '#ced4da',
            text: formData.get('text') || '#212529'
        }
    };
    
    // Collect buttons
    config.buttons = [];
    const buttonTitles = formData.getAll('button_title[]');
    const buttonLinks = formData.getAll('button_link[]');
    for (let i = 0; i < buttonTitles.length; i++) {
        if (buttonTitles[i].trim()) {
            config.buttons.push({
                title: buttonTitles[i],
                link: buttonLinks[i] || '#'
            });
        }
    }
    
    // Collect socials
    config.socials = [];
    const socialTitles = formData.getAll('social_title[]');
    const socialLinks = formData.getAll('social_link[]');
    const socialIcons = formData.getAll('social_icon_url[]');
    for (let i = 0; i < socialTitles.length; i++) {
        if (socialTitles[i].trim()) {
            config.socials.push({
                title: socialTitles[i],
                link: socialLinks[i] || '#',
                icon: resolveImageUrl(socialIcons[i]) || ''
            });
        }
    }
    
    // Collect subtitles
    config.subtitles = [];
    const subtitleTexts = formData.getAll('subtitle_text[]');
    const subtitleLinks = formData.getAll('subtitle_link[]');
    for (let i = 0; i < subtitleTexts.length; i++) {
        if (subtitleTexts[i].trim()) {
            config.subtitles.push({
                text: subtitleTexts[i],
                link: subtitleLinks[i] || ''
            });
        }
    }
    
    // Get custom CSS
    const customCSS = formData.get('custom_css') || '';
    
    // Generate HTML
    return generatePreviewHTMLTemplate(config, customCSS, previewState);
}

function generatePreviewHTMLTemplate(config, customCSS, state) {
    // Helper to escape HTML
    const escape = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };
    
    // Determine what to show based on state
    const showPre = state === 'pre';
    const showLive = state === 'live';
    const showEnd = state === 'end';
    
    return `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${escape(config.title)} - پیش‌نمایش</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style id="dynamic-style">
        :root {
            --bg: ${config.colors.bg};
            --title: ${config.colors.title};
            --primary: ${config.colors.primary};
            --primary-hover: ${config.colors["primary-hover"]};
            --card-bg: ${config.colors["card-bg"]};
            --placeholder: ${config.colors.placeholder};
            --placeholder-border: ${config.colors["placeholder-border"]};
            --text: ${config.colors.text};
        }
        * { padding: 0; margin: 0; box-sizing: border-box; }
        body { 
            font-family: 'Vazirmatn', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header { 
            background: #fff; 
            box-shadow: 0 2px 6px rgba(0,0,0,.05); 
            padding: 0.8rem 2rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        header img { height: 50px; margin-left: 50px; }
        header a { 
            color: var(--title); 
            text-decoration: none; 
            font-weight: 700; 
            transition: .25s; 
        }
        header a:hover { color: var(--primary-hover); }
        main { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: flex-start; 
            padding: 2rem 1rem;
        }
        .card { 
            background: var(--card-bg); 
            width: 100%; 
            max-width: 920px; 
            border-radius: 18px; 
            box-shadow: 0 12px 32px rgba(0,0,0,.06); 
            padding: 1rem;
            text-align: center;
        }
        h1 { color: var(--title); font-size: 1.85rem; margin-bottom: 10px; }
        .video { 
            width: 100%; 
            aspect-ratio: 16/9; 
            border-radius: 12px; 
            overflow: hidden; 
            margin-bottom: 1rem;
        }
        .video iframe { width: 100%; height: 100%; border: none; }
        .banner { 
            width: 100%; 
            background: var(--placeholder); 
            border-radius: 12px; 
            aspect-ratio: 16/9; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            margin-bottom: 1rem;
        }
        .banners { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.25rem; }
        .banners .banner { aspect-ratio: 64/19; }
        .actions { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 0.5rem; 
            justify-content: center; 
            margin-bottom: 1.5rem;
            direction: ltr;
        }
        .btn { 
            padding: 0.4rem 0.5rem; 
            border-radius: 7px; 
            background: var(--primary); 
            color: #fff; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 0.7rem; 
            transition: .25s;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .social { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 0.5rem; 
            justify-content: center;
            direction: ltr;
        }
        .social a { 
            padding: 0.5rem 0.9rem; 
            border-radius: 8px; 
            background: var(--bg); 
            color: var(--title); 
            text-decoration: none; 
            font-size: 0.95rem; 
            transition: .2s;
            border: 1px solid var(--placeholder-border);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
        }
        .social a:hover { background: var(--placeholder); }
        .social a img { width: 35px; height: 35px; object-fit: contain; }
        footer { text-align: center; color: var(--title); padding: 1rem; }
        h3 { font-size: 1rem; font-weight: 700; color: var(--title); margin: 1rem 0; }
        #subtitleBox { 
            margin: 1rem 0; 
            padding: 0.75rem 1rem; 
            background: #f9f9f9; 
            border-radius: 10px;
            min-height: 40px;
            text-align: center;
        }
            .countdown, h3{font-size:1rem;font-weight:700;color:var(--title);margin:1rem 0 1rem}
    .countdown{display: flex;justify-content: center;align-items: center;gap: 5px;}
    .countdown div{display: flex;flex-direction: column;justify-content: center;align-items: center;gap: 5px;}
    .countdown div span{background-color:var(--primary);color: var(--card-bg);padding: .5rem .8rem;border-radius: 8px;font-weight: bold;font-size: 2rem;width: 65px; display: flex; flex-direction: column; justify-content: center; align-items: center;}
    .countdown div span span{padding: 0;font-size: 1rem;margin-top: -8px;line-height:1.2;}
        #subtitleBox{margin:1rem 0;padding:.75rem 1rem;background:#f9f9f9;border-radius:10px;min-height:40px;text-align:center; display: none; position: relative; overflow: hidden;align-items: center;}
        #subtitleText{font-weight:700;color:var(--title);font-size:1.1rem; text-wrap: nowrap; position: absolute;}
        #subtitleText a { color: var(--title); text-decoration: none; }
        ${customCSS ? `\n/* Custom CSS */\n${customCSS}` : ""}
    </style>
</head>
<body>
    
    <header>
        <a href="${escape(config.homePage)}">
            ${
              config.logo
                ? `<img src="${escape(config.logo)}" alt="لوگو">`
                : "<span>لوگو</span>"
            }
        </a>
        <a href="${escape(config.homePage)}">صفحه اصلی</a>
    </header>
    
    <main>
        <div class="card">
            <h1>${escape(config.title)}</h1>
            
            ${
              showPre
                ? `
                <div class="banner" style="background-image: url(${escape(
                  config.preBanner
                )})"></div>
            `
                : ""
            }
            
            ${
              showLive
                ? `
                <div class="video">
                    ${
                      config.iframe ||
                      '<div style="background: #000; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #fff;">پخش‌کننده ویدیو</div>'
                    }
                </div>
            `
                : ""
            }
            
            ${
              showEnd
                ? `
                <div class="banner" style="background-image: url(${escape(
                  config.endBanner
                )})"></div>
            `
                : ""
            }
            
            ${
                config.subtitles.length > 0
                ? `
                <div id="subtitleBox">
                <span id="subtitleText">
                        ${
                          config.subtitles[0].link
                            ? `<a href="${escape(
                                config.subtitles[0].link
                              )}" target="_blank">${escape(
                                config.subtitles[0].text
                              )}</a>`
                            : escape(config.subtitles[0].text)
                        }
                    </span>
                    </div>
            `
                : ""
            }

            ${
              showPre
                ? `
                <div id="countdown" class="countdown">در حال بارگذاری تایمر…</div>
            `
                : ""
            }
            
            ${
              config.banner
                ? `
                <div class="banners">
                    ${
                      config.bannerLink
                        ? `<a href="${escape(
                            config.bannerLink
                          )}" target="_blank"><div class="banner" style="background-image: url(${escape(
                            config.banner
                          )})"></div></a>`
                        : `<div class="banner" style="background-image: url(${escape(
                            config.banner
                          )})"></div>`
                    }
                </div>
            `
                : ""
            }
            
            ${
              config.buttons.length > 0
                ? `
                <div class="actions">
                    ${config.buttons
                      .map(
                        (btn) =>
                          `<a class="btn" href="${escape(
                            btn.link
                          )}" target="_blank">${escape(btn.title)}</a>`
                      )
                      .join("")}
                </div>
            `
                : ""
            }
            
            ${
              config.socials.length > 0
                ? `
                <h3>صفحات اجتماعی:</h3>
                <div class="social">
                    ${config.socials
                      .map(
                        (social) => `
                        <a href="${escape(social.link)}" target="_blank">
                            ${
                              social.icon
                                ? `<img src="${escape(
                                    social.icon
                                  )}" alt="${escape(social.title)}">`
                                : ""
                            }
                            <span>${escape(social.title)}</span>
                        </a>
                    `
                      )
                      .join("")}
                </div>
            `
                : ""
            }
        </div>
    </main>
    
    <footer>${config.copyright}</footer>
    
    <script>
        // --- DATA FROM PHP ---
        const liveStartMs = new Date('${config.liveStart || ''}').getTime();
        const scrollSpeed = ${parseInt(config.scrollSpeed, 10) || 50};
        const subtitles = ${JSON.stringify(config.subtitles || [])};
        const previewState = '${state}';

        // --- ELEMENTS ---
        const countdownEl = document.getElementById('countdown');
        const subtitleBoxEl = document.getElementById('subtitleBox');
        const subtitleTextEl = document.getElementById('subtitleText');

        let currentIndex = 0;
        let cycleTimeout = null;

        // --- UTILITY FUNCTIONS ---
        function numberToPersian(n) {
            return n.toString().padStart(2, '0').replace(/\\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        function formatCountdown(ms) {
            if (isNaN(ms)) { return "پخش زنده هنوز زمان‌بندی نشده است." }
            if (ms < 0) { return "پخش زنده در حال پخش است."; }
            const d = Math.floor(ms / 86400000);
            const h = Math.floor((ms % 86400000) / 3600000);
            const m = Math.floor((ms % 3600000) / 60000);
            const s = Math.floor((ms % 60000) / 1000);
            let result = '';
            if (d > 0) result += '<div><span>' + numberToPersian(d) + '<span>روز</span></span></div>';
            if (h > 0 || d > 0) result += '<div><span>' + numberToPersian(h) + '<span>ساعت</span></span></div>';
            if (m > 0 || h > 0 || d > 0) result += '<div><span>' + numberToPersian(m) + '<span>دقیقه</span></span></div>';
            result += '<div><span>' + numberToPersian(s) + '<span>ثانیه</span></span></div>';
            return "زمان باقی‌مانده تا شروع: " + result;
        }

        // --- SUBTITLE MARQUEE LOGIC ---
        function removeKeyframesRule(name) {
            const styleEl = document.getElementById("dynamic-style");
            if (!styleEl || !styleEl.sheet) return;
            const sheet = styleEl.sheet;
            for (let i = 0; i < sheet.cssRules.length; i++) {
                const rule = sheet.cssRules[i];
                if (rule.type === CSSRule.KEYFRAMES_RULE && rule.name === name) {
                    sheet.deleteRule(i);
                    break;
                }
            }
        }

        function startSubtitleCycle() {
            if (cycleTimeout) clearTimeout(cycleTimeout);
            if (!subtitleTextEl || !subtitles.length) {
                if (subtitleBoxEl) subtitleBoxEl.style.display = 'none';
                return;
            }

            const cycle = () => {
                if (currentIndex >= subtitles.length) currentIndex = 0;
                const sub = subtitles[currentIndex];
                
                removeKeyframesRule("marquee");
                subtitleTextEl.innerHTML = '';
                subtitleTextEl.style.animation = 'none';
                if (subtitleBoxEl) subtitleBoxEl.style.display = 'flex';

                if (sub.link) {
                    subtitleTextEl.innerHTML = '<a href="' + sub.link + '" target="_blank" rel="noopener">' + sub.text + '</a>';
                } else {
                    subtitleTextEl.textContent = sub.text;
                }

                requestAnimationFrame(() => {
                    const textWidth = subtitleTextEl.offsetWidth;
                    const boxWidth = subtitleBoxEl.offsetWidth;
                    const totalDistance = textWidth + boxWidth;
                    const duration = totalDistance / scrollSpeed;

                    const sheet = document.getElementById("dynamic-style").sheet;
                    const rule = '@keyframes marquee { 0% { left: -' + textWidth + 'px; } 100% { left: 100%; } }';
                    sheet.insertRule(rule, sheet.cssRules.length);

                    subtitleTextEl.style.animation = 'marquee ' + duration + 's linear forwards';
                    
                    cycleTimeout = setTimeout(() => {
                        currentIndex++;
                        cycle();
                    }, duration * 1000);
                });
            };
            cycle();
        }

        // --- COUNTDOWN & STATE LOGIC ---
        function initializePreview() {
            if (previewState === 'pre' && countdownEl) {
                const tick = () => {
                    const distance = liveStartMs - Date.now();
                    countdownEl.innerHTML = formatCountdown(distance);
                };
                tick();
                setInterval(tick, 1000);
            } else if (countdownEl) {
                countdownEl.style.display = 'none';
            }
            
            startSubtitleCycle();
            
        }

        initializePreview();
    </script>
</body>
</html>`;
}

// Preview button click handler
document.getElementById('preview-btn')?.addEventListener('click', function() {
    const previewModal = setupModal(document.getElementById('preview-modal'));
    const previewFrame = document.getElementById('preview-frame');
    const previewState = document.getElementById('preview-state');
    
    // Generate and load preview
    function loadPreview() {
        const html = generatePreviewHTML(previewState.value);
        const blob = new Blob([html], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        previewFrame.src = url;
        
        // Clean up blob URL after loading
        previewFrame.onload = () => URL.revokeObjectURL(url);
    }
    
    loadPreview();
    previewModal.show();
    
    // Update preview when state changes
    previewState.onchange = loadPreview;
});

// Close preview button
document.getElementById('close-preview-btn')?.addEventListener('click', function() {
    const previewModal = document.getElementById('preview-modal');
    previewModal.classList.remove('active');
});

    const savedTabId = localStorage.getItem("activeDashboardTab");
    if (
      savedTabId === "appearance" ||
      document.querySelector("#appearance.active")
    ) {
      setTimeout(() => {
        initializeCSSEditor();
      }, 200);
    }

    // --- FIX: Add this URL validation helper function ---
    const isValidUrl = (string) => {
        // An empty string is valid because some link fields are optional.
        string = string ? string.trim() : "";
        if (!string) return true;
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    };
    const isValidImageUrl = (string) => {
        string = string ? string.trim() : "";
        if (!string) return true; // Optional fields are valid if empty

        let isStructurallyValid = false;

        // First, check if the URL structure is valid.
        if (string.startsWith("http://") || string.startsWith("https://")) {
            // For absolute URLs, use the full validator.
            isStructurallyValid = isValidUrl(string);
        } else {
            // For relative URLs (like our uploads), we assume the structure is valid.
            isStructurallyValid = true;
        }

        if (!isStructurallyValid) {
            return false;
        }

        // If the structure is valid, now check for a valid image extension.
        return /\.(jpg|jpeg|png|gif|svg|webp)$/i.test(string);
    };

    const validateUsername = (username) => {
        if (username.length < 3 || username.length > 20) {
            return {
                valid: false,
                message: "نام کاربری باید بین ۳ تا ۲۰ کاراکتر باشد.",
            };
        }
        if (!/^[a-zA-Z0-9_-]+$/.test(username)) {
            return {
                valid: false,
                message:
                    "نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد، خط تیره و آندرلاین باشد.",
            };
        }
        return { valid: true };
    };

    let selectedVersionsForCompare = [];

    const switchToTab = (tabId) => {
        const currentActiveButton = document.querySelector('.sidebar-nav .active');
        const currentActivePanel = document.querySelector('.tab-panel.active');
        const newTabButton = document.querySelector(`.sidebar-nav .tab-button[data-tab="${tabId}"]`);
        const newTabPanel = document.getElementById(tabId);

        // Do nothing if the clicked tab is already active or elements don't exist
        if (!newTabButton || !newTabPanel || (currentActiveButton && currentActiveButton === newTabButton)) {
            return;
        }

        if (tabId !== 'versions') {
            if (selectedVersionsForCompare.length) {
                selectedVersionsForCompare = [];
            }
        }

        // Handle save button visibility immediately
        const saveButtonContainer = document.querySelector('.save-button-container');
        if (['users', 'backup'].includes(tabId)) {
            saveButtonContainer.style.display = 'none';
        } else {
            saveButtonContainer.style.display = '';
        }

        // Animate out the current panel if there is one
        if (currentActivePanel) {
            currentActivePanel.classList.add('is-exiting');

            // Wait for the fade-out animation to finish before switching
            setTimeout(() => {
                currentActivePanel.classList.remove('active');
                currentActivePanel.classList.remove('is-exiting'); // Clean up

                currentActiveButton?.classList.remove('active');

                // Activate the new tab and button
                newTabButton.classList.add('active');
                newTabPanel.classList.add('active');
            }, 200); // This duration MUST match the CSS animation duration
        } else {
            // If no panel is active, just fade in the new one instantly
            newTabButton.classList.add('active');
            newTabPanel.classList.add('active');
        }
    };
    
    let isDirty = false; // FIX 7: Flag for unsaved changes

    // --- Global Elements ---
    const mainSaveBtn = document.getElementById("main-save-btn");
    const saveButtonContainer = document.querySelector(
        ".save-button-container"
    );

    // --- Toast Notification Handler ---
    const showToast = (message, type = "success") => {
        const container = document.getElementById("toast-container");
        if (!container) return;
        const toast = document.createElement("div");
        toast.className = `toast ${type}`;
        const icon =
            type === "success"
                ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
        const tempEl = document.createElement("div");
        tempEl.innerHTML = message;
        toast.innerHTML = `${icon}<span>${tempEl.innerText}</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add("show"), 100);
        setTimeout(() => {
            toast.classList.remove("show");
            toast.addEventListener("transitionend", () => toast.remove());
        }, 4000);
    };

    // --- Generic AJAX Requester ---
    const sendRequest = async (formData) => {
        if (!formData.has("csrf_token")) {
            const token = document.querySelector(
                'input[name="csrf_token"]'
            )?.value;
            if (token) formData.append("csrf_token", token);
        }
        const response = await fetch("ajax-handler.php", {
            method: "POST",
            body: formData,
            headers: { Accept: "application/json" },
        });
        if (!response.ok)
            throw new Error(`Server responded with status: ${response.status}`);
        const result = await response.json();
        if (!result.success)
            throw new Error(
                result.message || "An unknown server error occurred."
            );
        return result;
    };

    // --- Modal Logic ---
    const setupModal = (modalEl) => {
        if (!modalEl) return { show: () => {}, hide: () => {} };
        const hide = () => modalEl.classList.remove("active");
        const show = () => modalEl.classList.add("active");
        modalEl.addEventListener("click", (e) => {
            if (e.target === modalEl) hide();
        });
        modalEl
            .querySelectorAll(".modal-cancel-btn")
            .forEach((btn) => btn.addEventListener("click", hide));
        return { show, hide };
    };

    const confirmModal = setupModal(document.getElementById("confirm-modal"));
    const promptModal = setupModal(document.getElementById("prompt-modal"));
    const editUserModal = setupModal(
        document.getElementById("edit-user-modal")
    );

    const showConfirm = (title, message) => {
        return new Promise((resolve) => {
            const modalEl = document.getElementById("confirm-modal");
            modalEl.querySelector("#modal-title").textContent = title;
            modalEl.querySelector("#modal-text").textContent = message;
            const confirmBtn = modalEl.querySelector("#modal-confirm-btn");
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener(
                "click",
                () => {
                    confirmModal.hide();
                    resolve(true);
                },
                { once: true }
            );
            modalEl.querySelector("#modal-cancel-btn").onclick = () => {
                confirmModal.hide();
                resolve(false);
            };

            confirmModal.show();
        });
    };

    const showPrompt = (config) => {
        return new Promise((resolve) => {
            const modalEl = document.getElementById("prompt-modal");
            modalEl.querySelector("#prompt-title").textContent = config.title;
            modalEl.querySelector("#prompt-text").textContent = config.text;
            const input = modalEl.querySelector("#prompt-input");
            input.value = config.value || "";
            input.pattern = config.pattern || ".*";
            input.title = config.patternHint || "";

            const confirmBtn = modalEl.querySelector("#prompt-confirm-btn");
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener("click", () => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    return;
                }
                promptModal.hide();
                resolve(input.value.trim());
            });
            modalEl.querySelector("#prompt-cancel-btn").onclick = () => {
                promptModal.hide();
                resolve(null);
            };

            promptModal.show();
        });
    };

    // --- Form Dirty State & Unload Warning ---
    const setDirty = () => {
        if (!isDirty) isDirty = true;
        mainSaveBtn.disabled = false;
    };
    const setClean = () => {
        isDirty = false;
        mainSaveBtn.disabled = true;
    };
    window.addEventListener("beforeunload", (e) => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    // --- Image Preview Logic ---
    const setupImagePreview = (urlInput, fileInput, previewImage) => {
        const updatePreview = (src) => {
            previewImage.src =
                src ||
                "data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=";
            previewImage.style.display = src ? "" : "none";
        };
        fileInput.addEventListener("change", (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (re) => updatePreview(re.target.result);
                reader.readAsDataURL(file);
            } else {
                updatePreview(urlInput.value);
            }
        });
        urlInput.addEventListener("input", () => updatePreview(urlInput.value));
        updatePreview(urlInput.value);
    };

    // --- Dynamic Sortable List Manager ---
    const setupSortableList = (containerId, addButtonId, templateId) => {
        const container = document.getElementById(containerId);
        const addButton = document.getElementById(addButtonId);
        const template = document.getElementById(templateId);
        if (!container || !template) return;

        const setupItem = (item) => {
            item.querySelectorAll(".image-group").forEach((group) => {
                const urlInput = group.querySelector('.preview-url-input');
                const fileInput = group.querySelector('.preview-file-input');
                const preview = group.querySelector('.image-preview');
                const imageActions = group.querySelector(".image-actions");
                
                
                if (urlInput && fileInput && preview) {
                    setupImagePreview(urlInput, fileInput, preview);
                    enableMediaLibraryPicker(urlInput, fileInput, preview);
                }
            });
            updateItemCounters(containerId);
        };

        container.querySelectorAll(".sortable-item").forEach(setupItem);

        if (addButton) {
            addButton.addEventListener("click", () => {
                const newItem =
                    template.content.cloneNode(true).firstElementChild;
                container.appendChild(newItem);
                setupItem(newItem);
                setDirty();
            });
        }

        new Sortable(container, {
            animation: 150,
            ghostClass: "sortable-ghost",
            handle: ".drag-handle",
            forceFallback: true,
            onEnd: () => {
                updateItemCounters(containerId);
                setDirty();
            },
        });
        updateItemCounters(containerId);
    };

    const updateItemCounters = (containerId) => {
        const container = document.getElementById(containerId);
        if (!container) return;

        const items = container.querySelectorAll('.sortable-item');
        const totalItems = items.length;

        // Only apply reversed numbering to buttons and socials
        // const isReversed = ['buttons-container', 'socials-container'].includes(containerId);

        items.forEach((item, index) => {
            const counter = item.querySelector('.item-counter');
            if (counter) {
                counter.textContent = index + 1;
            }
        });
    };

    // --- MAIN FORM SUBMISSION ---
    const settingsForm = document.getElementById("settings-form");
    settingsForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const liveStartValue = document.getElementById("liveStart").value;
        const liveEndValue = document.getElementById("liveEnd").value;

        // Only validate if both fields are filled out
        if (liveStartValue && liveEndValue) {
            if (new Date(liveStartValue) >= new Date(liveEndValue)) {
                showToast("زمان شروع باید قبل از زمان پایان باشد.", "error");
                return; // Stop the submission
            }
        }

        if (cssEditor) {
            const textarea = document.getElementById('custom-css-editor');
            textarea.value = cssEditor.getValue();
        }

        // --- FIX: START of new URL validation logic ---
        try {
            let invalidUrl = null;
            let invalidInput = null;

            // Find the first invalid URL across all relevant fields
            document
                .querySelectorAll(
                    'input[name="homePage"], input[name="bannerLink"], [name="button_link[]"], [name="social_link[]"], [name="subtitle_link[]"]'
                )
                .forEach((input) => {
                    if (!isValidUrl(input.value)) {
                        invalidUrl = input.value;
                        invalidInput = input;
                    }
                });

            if (invalidUrl !== null) {
                showToast(`لینک وارد شده نامعتبر است: ${invalidUrl}`, "error");
                invalidInput.focus(); // Focus on the invalid field
                return; // Stop the submission
            }
        } catch (err) {
            // This handles cases where an input might not exist on the page
            console.error("Error during URL validation:", err);
        }
        // --- FIX: END of new URL validation logic ---

        try {
            let invalidImageUrl = null;
            let invalidImageInput = null;

            // Select all text inputs for image URLs
            document
                .querySelectorAll('input[name$="_url"]')
                .forEach((input) => {
                    if (!isValidImageUrl(input.value)) {
                        invalidImageUrl = input.value;
                        invalidImageInput = input;
                    }
                });

            if (invalidImageUrl !== null) {
                showToast(
                    `آدرس تصویر نامعتبر است: ${invalidImageUrl}`,
                    "error"
                );
                invalidImageInput.focus();
                return; // Stop submission
            }
        } catch (err) {
            console.error("Error during image URL validation:", err);
        }

        mainSaveBtn.disabled = true;
        mainSaveBtn.classList.add("loading");

        try {
            const formData = new FormData(settingsForm);
            const result = await sendRequest(formData);

            mainSaveBtn.classList.remove("loading");
            mainSaveBtn.classList.add("success");
            showToast(result.message);
            setClean();

            // --- FIX: START of new logic to update UI without reloading ---
            if (result.updated_data && result.updated_data.configs) {
                const newConfigs = result.updated_data.configs;

                // Helper function to strip "config/" prefix, same as PHP
                const getDashboardUrl = (path) => {
                    if (!path) return "";
                    return path.startsWith("config/")
                        ? path.substring("config/".length)
                        : path;
                };

                // Update main image fields
                ["logo", "preBanner", "endBanner", "banner"].forEach((key) => {
                    const group = document
                        .querySelector(`input[name="${key}_file"]`)
                        .closest(".image-group");
                    if (group) {
                        group.querySelector(".preview-url-input").value =
                            getDashboardUrl(newConfigs[key]);
                        group.querySelector(".image-preview").src =
                            getDashboardUrl(newConfigs[key]);
                        group.querySelector(".preview-file-input").value = ""; // Clear file input
                        group.querySelector(`input[name="${key}_old"]`).value =
                            newConfigs[key];
                    }
                });

                // Update social icon fields
                const socialItems = document.querySelectorAll(
                    "#socials-container .sortable-item"
                );
                if (
                    newConfigs.socials &&
                    socialItems.length === newConfigs.socials.length
                ) {
                    socialItems.forEach((item, index) => {
                        const newSocial = newConfigs.socials[index];
                        item.querySelector(".preview-url-input").value =
                            getDashboardUrl(newSocial.icon);
                        item.querySelector(".image-preview").src =
                            getDashboardUrl(newSocial.icon);
                        item.querySelector(".preview-file-input").value = ""; // Clear file input
                        item.querySelector(
                            'input[name="social_icon_old[]"]'
                        ).value = newSocial.icon;
                    });
                }
                const fetchIntervalInput =
                    document.getElementById("fetchInterval");
                if (fetchIntervalInput && newConfigs.fetchInterval) {
                    fetchIntervalInput.value = newConfigs.fetchInterval;
                }

                const scrollSpeedInput =
                    document.getElementById("scrollSpeed");
                if (scrollSpeedInput && newConfigs.scrollSpeed) {
                    scrollSpeedInput.value = newConfigs.scrollSpeed;
                }

                const playerRevealOffsetInput =
                    document.getElementById("playerRevealOffset");
                if (
                    playerRevealOffsetInput &&
                    newConfigs.playerRevealOffset !== undefined
                ) {
                    playerRevealOffsetInput.value =
                        newConfigs.playerRevealOffset;
                }
            }
            // --- FIX: END of new logic ---

            setTimeout(() => {
                mainSaveBtn.classList.remove("success");
                // The reload is no longer needed
                // window.location.reload();
            }, 1500);
        } catch (error) {
            showToast(error.message, "error");
            mainSaveBtn.disabled = false;
            mainSaveBtn.classList.remove("loading");
        }
    });

    // --- EVENT DELEGATION FOR ALL OTHER ACTIONS ---
    document.body.addEventListener("click", async (e) => {
        const button = e.target.closest("button");
        if (!button) return;

        // Dynamic item removal
        if (button.classList.contains("remove-btn")) {
            const item = button.closest(".sortable-item");
            const listContainer = item
                ? item.closest(".sortable-list, .sortable-list-grid")
                : null;

            if (
                item &&
                listContainer &&
                (await showConfirm(
                    "تایید حذف",
                    "آیا از حذف این آیتم مطمئن هستید؟"
                ))
            ) {
                item.remove();
                setDirty();
                // Re-calculate counters
                updateItemCounters(listContainer.id);
            }
        }
        if (button.id === 'remove-all-buttons-btn') {
            if (await showConfirm('تایید حذف همه', 'آیا از حذف تمام دکمه‌ها مطمئن هستید؟')) {
                document.getElementById('buttons-container').innerHTML = '';
                setDirty();
            }
        }
        if (button.id === 'remove-all-socials-btn') {
            if (await showConfirm('تایید حذف همه', 'آیا از حذف تمام آیتم‌های اجتماعی مطمئن هستید؟')) {
                document.getElementById('socials-container').innerHTML = '';
                setDirty();
            }
        }
        if (button.id === "remove-all-subtitles-btn") {
            if (
                await showConfirm(
                    "تایید حذف همه",
                    "آیا از حذف تمام زیرنویس‌ها مطمئن هستید؟"
                )
            ) {
                document.getElementById("subtitles-container").innerHTML = "";
                setDirty();
            }
        }

        // Color reset
        if (button.id === "reset-colors-btn") {
            if (await showConfirm('بازنشانی رنگ‌ها', 'آیا از بازنشانی تمام رنگ‌ها به مقادیر پیش‌فرض مطمئن هستید؟')) {
                const defaults = JSON.parse(button.dataset.defaults);
                const colorForm = button.closest(".card");
                for (const key in defaults) {
                    const inputName =
                        key.replace(/_/g, "-") === "title"
                            ? "title-color"
                            : key.replace(/_/g, "-");
                    const input = colorForm.querySelector(
                        `input[name="${inputName}"]`
                    );
                    if (input) {
                        input.value = defaults[key];
                        input.dispatchEvent(new Event("input", { bubbles: true })); // Trigger preview update
                    }
                }
                setDirty();
            }
        }
        
        // Header buttons
        const eventSelector = document.getElementById("event-selector");
        const currentEventId = eventSelector.value;
        const currentEventName =
            eventSelector.options[eventSelector.selectedIndex].text;

        switch (button.id) {
            case "create-event-btn": {
                const name = await showPrompt({
                    title: "ساخت رویداد جدید",
                    text: "یک نام برای رویداد جدید خود وارد کنید:",
                });
                if (name === null) return;
                const formData = new FormData();
                formData.append("action", "create_event");
                formData.append("event_name", name);
                try {
                    await sendRequest(formData);
                    showToast("رویداد جدید با موفقیت ساخته شد.");
                    window.location.reload();
                } catch (error) {
                    showToast(error.message, "error");
                }
                break;
            }
            case "rename-event-btn": {
                const newName = await showPrompt({
                    title: "تغییر نام رویداد",
                    text: `نام جدیدی برای "${currentEventName}" وارد کنید:`,
                    value: currentEventName,
                });
                if (newName === null || newName === currentEventName) return;
                const formData = new FormData();
                formData.append("action", "rename_event");
                formData.append("event_id", currentEventId);
                formData.append("event_name", newName);
                try {
                    await sendRequest(formData);
                    showToast("نام رویداد با موفقیت تغییر کرد.");
                    eventSelector.options[eventSelector.selectedIndex].text =
                        newName;
                } catch (error) {
                    showToast(error.message, "error");
                }
                break;
            }
            case "delete-event-btn": {
                if (
                    await showConfirm(
                        "تایید حذف رویداد",
                        `آیا از حذف رویداد "${currentEventName}" مطمئن هستید؟ این عمل غیرقابل بازگشت است.`
                    )
                ) {
                    const formData = new FormData();
                    formData.append("action", "delete_event");
                    formData.append("event_id", currentEventId);
                    try {
                        await sendRequest(formData);
                        showToast("رویداد با موفقیت حذف شد.");
                        window.location.reload();
                    } catch (error) {
                        showToast(error.message, "error");
                    }
                }
                break;
            }
            case "copy-event-id-btn":
                navigator.clipboard
                    .writeText(
                        document.querySelector(".event-id-display code")
                            .textContent
                    )
                    .then(() => showToast("شناسه رویداد کپی شد!"))
                    .catch(() => showToast("خطا در کپی کردن شناسه.", "error"));
                break;
            case "edit-event-id-btn": {
                const newId = await showPrompt({
                    title: "ویرایش شناسه رویداد",
                    text: "شناسه جدید باید منحصر به فرد باشد و فقط شامل حروف انگلیسی، اعداد و آندرلاین (_) باشد.",
                    value: currentEventId,
                    pattern: "^[a-zA-Z0-9_]+$",
                    patternHint:
                        "فقط حروف انگلیسی، اعداد و آندرلاین (_) مجاز است.",
                });
                if (newId === null || newId === currentEventId) return;
                const formData = new FormData();
                formData.append("action", "edit_event_id");
                formData.append("current_event_id", currentEventId);
                formData.append("new_event_id", newId);
                try {
                    await sendRequest(formData);
                    showToast(
                        "شناسه با موفقیت تغییر کرد. صفحه بارگذاری مجدد می‌شود."
                    );
                    window.location.reload();
                } catch (error) {
                    showToast(error.message, "error");
                }
                break;
            }
        }

        // User management buttons
        if (button.closest(".user-item")) {
            const userId = button.dataset.id;
            const username = button.dataset.username;
            if (button.classList.contains("edit-user-btn")) {
                document.getElementById("edit_user_id").value = userId;
                document.getElementById("edit_username").value = username;
                document.getElementById("edit_password").value = "";
                editUserModal.show();
            } else if (button.classList.contains("delete-user-btn")) {
                const message = button.hasAttribute("data-self-delete")
                    ? `آیا از حذف حساب کاربری خودتان ("${username}") مطمئن هستید؟`
                    : `آیا از حذف کاربر "${username}" مطمئن هستید؟`;
                if (await showConfirm("تایید حذف کاربر", message)) {
                    const formData = new FormData();
                    formData.append("action", "delete_user");
                    formData.append("user_id", userId);
                    try {
                        const result = await sendRequest(formData);
                        showToast(result.message);
                        if (result.self_delete) {
                            setTimeout(
                                () => (window.location.href = "logout.php"),
                                1500
                            );
                        } else {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } catch (error) {
                        showToast(error.message, "error");
                    }
                }
            }
        }
    });

    // Standalone forms (user management, restore)
    document.querySelectorAll(".standalone-form").forEach((form) => {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const usernameInput = form.querySelector('input[name="username"]');
            if (usernameInput) {
                const validationResult = validateUsername(usernameInput.value);
                if (!validationResult.valid) {
                    showToast(validationResult.message, "error");
                    return; // Stop the submission
                }
            }
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.classList.add("loading");
            try {
                const result = await sendRequest(new FormData(form));
                showToast(result.message);
                setTimeout(() => window.location.reload(), 1500);
            } catch (error) {
                showToast(error.message, "error");
                submitButton.disabled = false;
                submitButton.classList.remove("loading");
            }
        });
    });

    // Event Selector Change
    document
        .getElementById("event-selector")
        .addEventListener("change", (e) => {
            if (
                isDirty &&
                !confirm(
                    "تغییرات ذخیره نشده از بین خواهند رفت. آیا مطمئن هستید؟"
                )
            ) {
                e.target.value =
                    e.target.querySelector("option[selected]").value; // Revert selection
                return;
            }
            const formData = new FormData();
            formData.append("action", "switch_event");
            formData.append("event_id", e.target.value);
            sendRequest(formData)
                .then(() => window.location.reload())
                .catch((err) => showToast(err.message, "error"));
        });

    // Tab Switching & Save Button Visibility
    document.querySelectorAll(".sidebar-nav .tab-button").forEach((button) => {
        button.addEventListener("click", (e) => {
            e.preventDefault();
            const tabId = button.dataset.tab;

            // Save the clicked tab's ID to localStorage
            localStorage.setItem("activeDashboardTab", tabId);

            // Use our new function to switch to the tab
            switchToTab(tabId);

            if (tabId === 'appearance') {
                setTimeout(() => {
                    initializeCSSEditor();
                }, 100);
            }
        });
    });

    // Backup Restore Preview
    const backupFileInput = document.getElementById("backup_file");
    if (backupFileInput) {
        backupFileInput.addEventListener("change", () => {
            const previewEl = document.getElementById("json-preview").firstChild;
            const restoreBtn = document.getElementById("restore-btn");
            const file = backupFileInput.files[0];
            if (file && file.type === "application/json") {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        previewEl.textContent = JSON.stringify(
                            JSON.parse(e.target.result),
                            null,
                            2
                        );
                        Prism.highlightAllUnder(previewEl.parentElement);
                        previewEl.parentElement.parentElement.style.display = "block";
                        restoreBtn.disabled = false;
                    } catch (err) {
                        previewEl.textContent = "خطا: فایل JSON معتبر نیست.";
                        restoreBtn.disabled = true;
                    }
                };
                reader.readAsText(file);
            } else {
                previewEl.parentElement.style.display = "none";
                restoreBtn.disabled = true;
                if (file)
                    showToast("لطفا فقط فایل .json انتخاب کنید.", "error");
            }
        });
    }

    // Color preview update
    document.querySelector("#appearance").addEventListener("input", (e) => {
        if (e.target.type === "color") {
            let varName = e.target.name.replace("-color", "");
            document
                .querySelector(".color-preview-area")
                ?.style.setProperty("--" + varName, e.target.value);
        }
    });

    // Mark form as dirty on any input
    settingsForm.addEventListener("input", setDirty);

    // Initial call to set up the dashboard state
    const initializeDashboard = () => {
        setupSortableList(
            "buttons-container",
            "add-button-btn",
            "button-template"
        );
        setupSortableList(
            "socials-container",
            "add-social-btn",
            "social-template"
        );
        setupSortableList(
            "subtitles-container",
            "add-subtitle-btn",
            "subtitle-template"
        );

        document.querySelectorAll(".image-group").forEach((group) => {
            setupImagePreview(
                group.querySelector(".preview-url-input"),
                group.querySelector(".preview-file-input"),
                group.querySelector(".image-preview")
            );
        });

        // Restore the last active tab from localStorage
        const savedTabId = localStorage.getItem("activeDashboardTab");
        if (savedTabId && document.getElementById(savedTabId)) {
            switchToTab(savedTabId);
        } else {
            // If no tab is saved, ensure the default tab is correctly set up
            switchToTab("settings");
        }
        document.querySelectorAll('.image-group').forEach((group) => {
            const urlInput = group.querySelector('.preview-url-input');
            const fileInput = group.querySelector('.preview-file-input');
            const preview = group.querySelector('.image-preview');
            const imageActions = group.querySelector(".image-actions");
            
            
            if (urlInput && fileInput && preview) {
                setupImagePreview(urlInput, fileInput, preview);
                enableMediaLibraryPicker(urlInput, fileInput, preview);
            }
        });
    };

    initializeDashboard();
    // --- KEYBOARD SHORTCUTS ---
    document.addEventListener('keydown', (e) => {
        // Use a variable to track if we handled the shortcut
        let shortcutHandled = false; 

        // --- Shortcuts with CTRL key ---
        if (e.ctrlKey) {
            switch (e.key.toLowerCase()) {
                case 's': // Ctrl + S: Save changes
                    document.getElementById('main-save-btn')?.click();
                    shortcutHandled = true;
                    break;
                
                case 'e': // Ctrl + N: Create New event
                    document.getElementById('create-event-btn')?.click();
                    shortcutHandled = true;
                    break;

                case 'l': // Ctrl + L: View Live page in new tab
                    const liveLink = document.querySelector('a[href*="../index.php?event="]');
                    if (liveLink) {
                        window.open(liveLink.href, '_blank');
                    }
                    shortcutHandled = true;
                    break;
                
                // Ctrl + 1, 2, 3... to switch tabs
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                    const tabIndex = parseInt(e.key) - 1;
                    const tabButtons = document.querySelectorAll('.sidebar-nav .tab-button');
                    if (tabButtons[tabIndex]) {
                        tabButtons[tabIndex].click();
                        shortcutHandled = true;
                    }
                    break;
            }
        }

        // --- Shortcuts without CTRL key ---
        switch (e.key) {
             case 'F2': // F2: Rename current event
                // Check if the focus is not on an input field to avoid conflicts
                if (document.activeElement.tagName.toLowerCase() !== 'input') {
                    document.getElementById('rename-event-btn')?.click();
                    shortcutHandled = true;
                }
                break;
        }

        // If we handled the shortcut, prevent the browser's default action (like saving the page)
        if (shortcutHandled) {
            e.preventDefault();
        }
    });

    let currentVersions = [];
    const compareModal = setupModal(document.getElementById('compare-modal'));

    // Load versions when tab is opened or refreshed
    document.querySelector('[data-tab="versions"]')?.addEventListener('click', () => setTimeout(loadVersions, 100));
    document.getElementById('refresh-versions-btn')?.addEventListener('click', loadVersions);

    if (
      savedTabId === "versions" ||
      document.querySelector("#versions.active")
    ) {
      setTimeout(() => {
        loadVersions();
      }, 100);
    }

    async function loadVersions() {
        const versionsList = document.getElementById('versions-list');
        versionsList.innerHTML = '<div class="loading-versions">در حال بارگذاری...</div>';
        
        try {
            const formData = new FormData();
            formData.append('action', 'get_versions');
            
            const result = await sendRequest(formData);
            currentVersions = result.versions;
            const currentVersion = result.current_version;
            
            if (currentVersions.length === 0) {
                versionsList.innerHTML = '<p style="text-align: center; padding: 2rem; color: var(--text-light-color);">هنوز هیچ نسخه‌ای ذخیره نشده است.</p>';
                return;
            }
            
            versionsList.innerHTML = currentVersions.map(v => renderVersionItem(v, v.version_number === currentVersion)).join('');
            attachVersionEventListeners();
            
        } catch (error) {
            versionsList.innerHTML = `<p style="text-align: center; padding: 2rem; color: var(--danger-color);">${error.message}</p>`;
        }
    }

    function renderVersionItem(version, isCurrent) {
        const date = new Date(version.created_at * 1000);
        const dateStr = date.toLocaleDateString('fa-IR');
        const timeStr = date.toLocaleTimeString('fa-IR');
        
        return `
            <div class="version-item ${isCurrent ? 'current-version' : ''}" data-version="${version.version_number}">
                <div class="version-header">
                    <div class="version-info">
                        <span class="version-number">نسخه ${version.version_number}#</span>
                        ${isCurrent ? '<span class="current-badge">نسخه فعلی</span>' : ''}
                        <div class="version-meta">
                            توسط <strong>${version.changed_by}</strong> در ${dateStr} ساعت ${timeStr}
                        </div>
                    </div>
                    <div class="version-actions">
                        ${!isCurrent ? `
                            <button type="button" class="btn btn--danger btn--icon restore-version-btn" 
                                    data-version="${version.version_number}" title="بازگردانی">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                            </button>` : ''}
                        <button type="button" class="btn btn--primary btn--icon view-version-btn" 
                                data-version="${version.version_number}" title="مشاهده جزئیات">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </button>
                        <button type="button" class="btn btn--primary btn--icon select-for-compare-btn" 
                                data-version="${version.version_number}" title="انتخاب برای مقایسه">
                            <svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_429_11147)"><path d="M13 3.99969H6C4.89543 3.99969 4 4.89513 4 5.99969V17.9997C4 19.1043 4.89543 19.9997 6 19.9997H13M17 3.99969H18C19.1046 3.99969 20 4.89513 20 5.99969V6.99969M20 16.9997V17.9997C20 19.1043 19.1046 19.9997 18 19.9997H17M20 10.9997V12.9997M12 1.99969V21.9997" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_429_11147"><rect width="24" height="24" fill="white" transform="translate(0 -0.000305176)"/></clipPath></defs></svg>
                        </button>
                    </div>
                </div>
                ${version.description ? `<div class="version-description">${version.description}</div>` : ''}
            </div>
        `;
    }

    function attachVersionEventListeners() {
        document.querySelectorAll('.restore-version-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const version = this.dataset.version;
                if (await showConfirm('بازگردانی نسخه', `آیا از بازگردانی به نسخه #${version} مطمئن هستید؟ تنظیمات فعلی ذخیره و سپس این نسخه بازگردانی می‌شود.`)) {
                    this.disabled = true; this.classList.add('loading');
                    try {
                        const formData = new FormData();
                        formData.append('action', 'restore_version');
                        formData.append('version_number', version);
                        const result = await sendRequest(formData);
                        showToast(result.message);
                        setTimeout(() => window.location.reload(), 1500);
                    } catch (error) {
                        showToast(error.message, 'error');
                        this.disabled = false; this.classList.remove('loading');
                    }
                }
            });
        });
        
        document.querySelectorAll('.view-version-btn').forEach(btn => btn.addEventListener('click', function() { showVersionDetails(this.dataset.version); }));
        
        document.querySelectorAll('.select-for-compare-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const version = parseInt(this.dataset.version);
                if (selectedVersionsForCompare.includes(version)) {
                    selectedVersionsForCompare = selectedVersionsForCompare.filter(v => v !== version);
                    this.style.background = ''; this.style.color = ''; this.style.borderColor = '';
                } else {
                    if (selectedVersionsForCompare.length >= 2) {
                        showToast('حداکثر می‌توانید ۲ نسخه برای مقایسه انتخاب کنید', 'error'); return;
                    }
                    selectedVersionsForCompare.push(version);
                    this.style.background = 'var(--primary-color)'; this.style.color = 'var(--light-color)'; this.style.borderColor = 'var(--primary-color)';
                }
                
                if (selectedVersionsForCompare.length === 2) {
                    compareVersions(selectedVersionsForCompare[0], selectedVersionsForCompare[1]);
                    selectedVersionsForCompare = [];
                    document.querySelectorAll('.select-for-compare-btn').forEach(btn =>{
                        btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = '';
                    })
                }
            });
        });
    }

    // RECONSTRUCTED: This function was incomplete in the provided file.
    async function showVersionDetails(versionNumber) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_version_data');
            formData.append('version_number', versionNumber);
            
            const result = await sendRequest(formData);
            const version = result.version;
            
            const date = new Date(version.created_at * 1000);
            const details = `
                <div style="text-align: right; line-height: 1.8;">
                    <p>
                        <strong>توسط:</strong> ${version.changed_by}<br />
                        <strong>تاریخ:</strong> ${date.toLocaleString('fa-IR')}<br />
                        ${version.description ? `<strong>توضیحات:</strong> ${version.description}<br />` : ''}
                    </p>
                    <details>
                        <summary><strong>مشاهده داده‌های خام</strong></summary>
                        <pre class="language-json" style="background: #2d2d2d; color: #f1f1f1; padding: 1rem; border-radius: 8px; text-align: left; direction: ltr; max-height: 300px; overflow-y: auto;"><code>${JSON.stringify(version, null, 2)}</code></pre>
                    </details>
                </div>`;
            
            // Using the existing confirm modal but only showing an "OK" button.
            const modalEl = document.getElementById("confirm-modal");
            modalEl.querySelector("#modal-title").textContent = `جزئیات نسخه #${version.version_number}`;
            modalEl.querySelector("#modal-text").innerHTML = details;
            Prism.highlightAllUnder(document.getElementById("modal-text"));
            modalEl.querySelector("#modal-confirm-btn").style.display = 'none';
            modalEl.querySelector("#modal-cancel-btn").textContent = 'بستن';
            
            const onModalHide = () => {
                modalEl.querySelector("#modal-confirm-btn").style.display = '';
                modalEl.querySelector("#modal-cancel-btn").textContent = 'انصراف';
                modalEl.removeEventListener('transitionend', checkHide);
            };

            const checkHide = (e) => {
                if (e.propertyName === 'opacity' && !modalEl.classList.contains('active')) {
                    onModalHide();
                }
            };

            modalEl.addEventListener('transitionend', checkHide);

            const cancelButton = modalEl.querySelector("#modal-cancel-btn");
            const newCancelButton = cancelButton.cloneNode(true);
            cancelButton.parentNode.replaceChild(newCancelButton, cancelButton);
            newCancelButton.addEventListener('click', () => {
                confirmModal.hide();
            }, { once: true });

            confirmModal.show();

        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    // RECONSTRUCTED: This function was missing from the provided file.
    async function compareVersions(v1, v2) {
        try {
            const formData = new FormData();
            formData.append('action', 'compare_versions');
            formData.append('version1', Math.min(v1, v2));
            formData.append('version2', Math.max(v1, v2));

            const result = await sendRequest(formData);

            document.getElementById('compare-v1-title').textContent = `نسخه #${result.version1.version_number}`;
            document.getElementById('compare-v2-title').textContent = `نسخه #${result.version2.version_number}`;

            const v1Content = document.getElementById('compare-v1-content');
            const v2Content = document.getElementById('compare-v2-content');

            v1Content.innerHTML = renderCompareColumn(result.version1);
            v2Content.innerHTML = renderCompareColumn(result.version2);
            Prism.highlightAllUnder(v1Content);
            Prism.highlightAllUnder(v2Content);

            compareModal.show();

        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    // RECONSTRUCTED: Helper function for the compare modal.
    function renderCompareColumn(versionData) {
        const escape = (str) => String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        
        let html = '';
        const { configs, subtitles, custom_css } = versionData;

        // General Configs
        html += '<div class="compare-section"><h5>تنظیمات اصلی:</h5>';
        for (const key in configs) {
            if (typeof configs[key] !== 'object') {
                html += `<div class="compare-field language-json"><strong>${key}:</strong> <code>${escape(configs[key] || 'خالی')}</code></div>`;
            }
        }
        html += '</div>';

        // Buttons, Socials (Objects in array)
        ['buttons', 'socials'].forEach(key => {
            html += `<div class="compare-section"><h5>${key === 'buttons' ? 'دکمه‌ها:' : 'صفحات اجتماعی:'}</h5>`;
            if (configs[key]?.length > 0) {
                html += `<pre class="language-json"><code>${escape(JSON.stringify(configs[key], null, 2))}</code></pre>`;
            } else {
                html += `<pre class="language-json"><code>[]</code></pre>`;
            }
            html += '</div>';
        });

        // Subtitles
        html += '<div class="compare-section"><h5>زیرنویس‌ها:</h5>';
        if (subtitles?.length > 0) {
            html += `<pre class="language-json"><code>${escape(JSON.stringify(subtitles, null, 2))}</code></pre>`;
        } else {
            html += `<pre class="language-json"><code>[]</code></pre>`;
        }
        html += '</div>';

        // Custom CSS
        html += '<div class="compare-section"><h5>CSS سفارشی:</h5>';
        html += `<pre class="language-css"><code>${escape(custom_css || '/* خالی */')}</code></pre>`;
        html += '</div>';

        return html;
    }

    // ============================================
    // MEDIA MANAGER
    // ============================================

    let currentMediaLibrary = [];
    let selectedMediaItem = null;

    // Initialize media manager when tab is opened
    document.querySelector('[data-tab="media"]')?.addEventListener('click', function() {
        setTimeout(loadMediaLibrary, 100);
    });

    // Drag and drop functionality
    const dropzone = document.getElementById('media-dropzone');
    const fileInput = document.getElementById('media-file-input');

    if (dropzone && fileInput) {
        dropzone.addEventListener('click', () => fileInput.click());
        
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });
        
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                // Trigger change event
                const event = new Event('change', { bubbles: true });
                fileInput.dispatchEvent(event);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                const fileName = e.target.files[0].name;
                dropzone.querySelector('p').textContent = `فایل انتخاب شده: ${fileName}`;
            }
        });
    }

    // Upload media form
    document.getElementById('media-upload-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('media-file-input');
        if (!fileInput.files.length) {
            showToast('لطفاً یک فایل انتخاب کنید', 'error');
            return;
        }
        
        const submitBtn = document.getElementById('upload-media-btn');
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        try {
            const formData = new FormData(this);
            const result = await sendRequest(formData);
            
            showToast(result.message);
            
            // Reset form
            this.reset();
            dropzone.querySelector('p').textContent = 'فایل را اینجا رها کنید یا کلیک کنید';
            
            // Reload media library
            loadMediaLibrary();
            
            submitBtn.classList.remove('loading');
            submitBtn.classList.add('success');
            setTimeout(() => {
                submitBtn.classList.remove('success');
                submitBtn.disabled = false;
            }, 1500);
            
        } catch (error) {
            showToast(error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
        }
    });

    // Load media library
    async function loadMediaLibrary(filters = {}) {
        const mediaGrid = document.getElementById('media-grid');
        mediaGrid.innerHTML = '<div class="loading-media">در حال بارگذاری...</div>';
        
        try {
            const formData = new FormData();
            formData.append('action', 'get_media_library');
            
            if (filters.search) formData.append('search', filters.search);
            if (filters.mime_type) formData.append('mime_type', filters.mime_type);
            
            const result = await sendRequest(formData);
            currentMediaLibrary = result.media;
            
            // Display stats
            displayMediaStats(result.stats);
            
            // Display media grid
            if (currentMediaLibrary.length === 0) {
                mediaGrid.innerHTML = '<div class="loading-media">هیچ فایلی یافت نشد</div>';
                return;
            }
            
            mediaGrid.innerHTML = currentMediaLibrary.map(media => renderMediaItem(media)).join('');
            
            // Attach click handlers
            document.querySelectorAll('.media-item').forEach(item => {
                item.addEventListener('click', function() {
                    const mediaId = parseInt(this.dataset.id);
                    const media = currentMediaLibrary.find(m => m.id === mediaId);
                    if (media) {
                        showMediaDetail(media);
                    }
                });
            });
            
        } catch (error) {
            mediaGrid.innerHTML = `<div class="loading-media" style="color: var(--danger-color);">${error.message}</div>`;
        }
    }

    function displayMediaStats(stats) {
        const statsContainer = document.getElementById('media-stats');
        
        const totalSizeMB = (stats.total_size / (1024 * 1024)).toFixed(2);
        
        statsContainer.innerHTML = `
            <div class="stat-item">
                <div class="stat-value">${stats.total_files}</div>
                <div class="stat-label">تعداد فایل</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${totalSizeMB} MB</div>
                <div class="stat-label">حجم کل</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${stats.jpeg_count}</div>
                <div class="stat-label">JPEG</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${stats.png_count}</div>
                <div class="stat-label">PNG</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${stats.webp_count}</div>
                <div class="stat-label">WebP</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${stats.svg_count}</div>
                <div class="stat-label">SVG</div>
            </div>
        `;
    }

    function renderMediaItem(media) {
        const sizeKB = (media.filesize / 1024).toFixed(1);
        let dimensions = media.width && media.height ? `${media.width}×${media.height}` : media.original_name.split('.').pop().toLowerCase() === "svg" ? '' : 'N/A';
        if (media.original_name.split('.').pop().toLowerCase() === "svg") {
            dimensions = '';
        }
        // let src = media.filepath;
        // if (media.filepath.startsWith("config/")){
        //     src = src.substring(7);
        // }
        
        return `
            <div class="media-item" data-id="${media.id}" style="position: relative;">
                ${media.usage_count > 0 ? `<span class="media-item-badge">در حال استفاده</span>` : ''}
                <div class="media-item-preview">
                    <img src="${media.filepath}" alt="${media.original_name}" loading="lazy">
                </div>
                <div class="media-item-info">
                    <div class="media-item-name" title="${media.original_name}">${media.original_name}</div>
                    <div class="media-item-meta">
                        <span>${dimensions}</span>
                        <span>${sizeKB} KB</span>
                    </div>
                </div>
            </div>
        `;
    }

    async function showMediaDetail(media) {
        selectedMediaItem = media;
        // let src = media.filepath;
        // if (media.filepath.startsWith("config/")){
        //     src = src.substring(7);
        // }
        
        // Populate modal
        document.getElementById('media-detail-image').src = media.filepath;
        document.getElementById('media-detail-filename').value = media.original_name;
        document.getElementById('media-detail-filepath').value = media.filepath;
        document.getElementById('media-detail-dimensions').value = 
            media.width && media.height ? `${media.width}×${media.height}` : media.original_name.split('.').pop().toLowerCase() === "svg" ? 'فایل های svg ابعاد مشخصی ندارند' : 'نامشخص';
        document.getElementById('media-detail-size').value = `${(media.filesize / 1024).toFixed(2)} کیلوبایت`;
        document.getElementById('media-detail-uploader').value = media.uploaded_by;
        
        const date = new Date(media.uploaded_at * 1000);
        document.getElementById('media-detail-date').value = 
            date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR');
        
        document.getElementById('media-detail-description').value = media.description || '';
        document.getElementById('media-detail-tags').value = media.tags || '';
        
        // Check usage
        await checkMediaUsage(media.filepath);
        
        // Show modal
        const modal = setupModal(document.getElementById('media-detail-modal'));
        modal.show();
    }

    async function checkMediaUsage(filepath) {
        try {
            const formData = new FormData();
            formData.append('action', 'check_media_usage');
            formData.append('filepath', filepath);
            
            const result = await sendRequest(formData);
            const usageInfo = document.getElementById('media-usage-info');
            const usageList = document.getElementById('media-usage-list');
            
            if (result.usage.in_use) {
                usageList.innerHTML = result.usage.locations.map(loc => `<li>${loc}</li>`).join('');
                usageInfo.style.display = 'block';
            } else {
                usageInfo.style.display = 'none';
            }
            
        } catch (error) {
            console.error('Error checking usage:', error);
        }
    }

    // Copy filepath to clipboard
    document.getElementById('copy-filepath-btn')?.addEventListener('click', function() {
        const filepath = document.getElementById('media-detail-filepath').value;
        navigator.clipboard.writeText(filepath)
            .then(() => showToast('آدرس فایل کپی شد'))
            .catch(() => showToast('خطا در کپی کردن', 'error'));
    });

    // Save media info
    document.getElementById('save-media-info-btn')?.addEventListener('click', async function() {
        if (!selectedMediaItem) return;
        
        this.disabled = true;
        this.classList.add('loading');
        
        try {
            const formData = new FormData();
            formData.append('action', 'update_media_info');
            formData.append('media_id', selectedMediaItem.id);
            formData.append('description', document.getElementById('media-detail-description').value);
            formData.append('tags', document.getElementById('media-detail-tags').value);
            
            const result = await sendRequest(formData);
            showToast(result.message);
            
            this.classList.remove('loading');
            this.classList.add('success');
            
            setTimeout(() => {
                this.classList.remove('success');
                this.disabled = false;
            }, 1500);
            
            // Reload library
            loadMediaLibrary();
            
        } catch (error) {
            showToast(error.message, 'error');
            this.disabled = false;
            this.classList.remove('loading');
        }
    });

    // Delete media
    document.getElementById('delete-media-btn')?.addEventListener('click', async function() {
        if (!selectedMediaItem) return;
        
        if (await showConfirm(
            'حذف فایل',
            `آیا از حذف فایل "${selectedMediaItem.original_name}" مطمئن هستید؟`
        )) {
            this.disabled = true;
            this.classList.add('loading');
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_media');
                formData.append('media_id', selectedMediaItem.id);
                
                const result = await sendRequest(formData);
                showToast(result.message);
                
                // Close modal
                document.getElementById('media-detail-modal').classList.remove('active');
                
                // Reload library
                loadMediaLibrary();
                
            } catch (error) {
                showToast(error.message, 'error');
                this.disabled = false;
                this.classList.remove('loading');
            }
        }
    });

    // Refresh media library
    document.getElementById('refresh-media-btn')?.addEventListener('click', loadMediaLibrary);
    
    if (
      savedTabId === "media" ||
      document.querySelector("#media.active")
    ) {
      setTimeout(() => {
        loadMediaLibrary();
      }, 100);
    }

    // Cleanup unused media
    document.getElementById('cleanup-media-btn')?.addEventListener('click', async function() {
        if (await showConfirm(
            'پاکسازی فایل‌های استفاده نشده',
            'آیا از حذف تمام فایل‌هایی که در تنظیمات استفاده نشده‌اند مطمئن هستید؟'
        )) {
            this.disabled = true;
            this.classList.add('loading');
            
            try {
                const formData = new FormData();
                formData.append('action', 'cleanup_unused_media');
                
                const result = await sendRequest(formData);
                showToast(result.message);
                
                this.classList.remove('loading');
                this.disabled = false;
                
                // Reload library
                loadMediaLibrary();
                
            } catch (error) {
                showToast(error.message, 'error');
                this.disabled = false;
                this.classList.remove('loading');
            }
        }
    });

    // Search and filter
    let searchTimeout;
    document.getElementById('media-search')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadMediaLibrary({
                search: this.value.trim(),
                mime_type: document.getElementById('media-filter-type').value
            });
        }, 500);
    });

    document.getElementById('media-filter-type')?.addEventListener('change', function() {
        loadMediaLibrary({
            search: document.getElementById('media-search').value,
            mime_type: this.value
        });
    });
    

function enableMediaLibraryPicker(inputField, fileInputField, previewImage) {
    // Find or create image-actions container
    let actionsContainer = inputField.parentNode.querySelector('.image-actions');
    
    // Get the browse button
    const browseBtn = actionsContainer.querySelector('.select-from-library-btn');
    
    browseBtn.addEventListener('click', async () => {
        const pickerModal = createMediaPickerModal();
        
        pickerModal.onSelect = (media) => {
            inputField.value = media.filepath;
            previewImage.src = media.filepath;
            previewImage.style.display = '';
            setDirty();
            pickerModal.close();
        };
        
        pickerModal.show();
    });
}


let mediaPickerModalInstance = null;

function createMediaPickerModal() {
    if (mediaPickerModalInstance) {
        return mediaPickerModalInstance;
    }
    
    const modal = document.getElementById('media-picker-modal');
    const grid = document.getElementById('temp-media-grid');
    const searchInput = document.getElementById('picker-search');

    const modalObject = {
      show: () => {
        modal.classList.add("active");
        modal.querySelectorAll('.media-picker-tab')[0].click();
        loadPickerMedia();
      },
      close: () => {
        modal.classList.remove("active");
      },
      onSelect: null,
    };
    
    // فقط یک بار event listenerها را اضافه کن
    if (!modal._initialized) {
        // Tab switching
        modal.querySelectorAll('.media-picker-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                modal.querySelectorAll('.media-picker-tab').forEach(t => t.classList.remove('active'));
                modal.querySelectorAll('.media-picker-tab-content').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                const tabId = this.dataset.tab;
                document.getElementById(`picker-${tabId}-tab`).classList.add('active');
            });
        });
        
        // Search functionality
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadPickerMedia(this.value);
            }, 500);
        });
        
        // Quick upload functionality - FIXED
        const quickUploadZone = document.getElementById('quick-upload-zone');
        const quickFileInput = document.getElementById('quick-file-input');
        
        // حذف event listenerهای قبلی با جایگزینی المان
        const newQuickUploadZone = quickUploadZone.cloneNode(true);
        quickUploadZone.parentNode.replaceChild(newQuickUploadZone, quickUploadZone);
        
        const newQuickFileInput = quickFileInput.cloneNode(true);
        quickFileInput.parentNode.replaceChild(newQuickFileInput, quickFileInput);
        
        // دریافت المان‌های جدید
        const freshQuickUploadZone = document.getElementById('quick-upload-zone');
        const freshQuickFileInput = document.getElementById('quick-file-input');
        
        // اضافه کردن event listener به المان‌های جدید
        freshQuickUploadZone.addEventListener('click', handleQuickUploadClick);
        
        freshQuickUploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            freshQuickUploadZone.classList.add('dragover');
        });
        
        freshQuickUploadZone.addEventListener('dragleave', () => {
            freshQuickUploadZone.classList.remove('dragover');
        });
        
        freshQuickUploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            freshQuickUploadZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                freshQuickFileInput.files = files;
                uploadQuickFile();
            }
        });
        
        freshQuickFileInput.addEventListener('change', () => {
            if (freshQuickFileInput.files.length > 0) {
                uploadQuickFile();
            }
        });
        
        // Close button
        const closeBtn = modal.querySelector('.close-picker-btn');
        closeBtn.addEventListener('click', () => {
            modal.classList.remove("active");
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove("active");
            }
        });
        
        modal._initialized = true;
    }

    
    // Load media library
    function loadPickerMedia(searchTerm = '') {
        grid.innerHTML = '<div class="loading-media">در حال بارگذاری...</div>';
        
        const formData = new FormData();
        formData.append('action', 'get_media_library');
        if (searchTerm) formData.append('search', searchTerm);
        
        sendRequest(formData).then(result => {
            if (result.media.length === 0) {
                grid.innerHTML = '<div class="loading-media">هیچ فایلی در کتابخانه وجود ندارد</div>';
                return;
            }
            
            grid.innerHTML = result.media.map(media => renderMediaItem(media)).join('');
            
            grid.querySelectorAll('.media-item').forEach(item => {
                item.addEventListener('click', function() {
                    const mediaId = parseInt(this.dataset.id);
                    const media = result.media.find(m => m.id === mediaId);
                    if (media && modalObject.onSelect) {
                        modalObject.onSelect(media);
                    }
                });
            });
        }).catch(error => {
            grid.innerHTML = `<div class="loading-media" style="color: var(--danger-color);">${error.message}</div>`;
        });
    }
    
    function handleQuickUploadClick() {
        document.getElementById('quick-file-input').click();
    }
    
    async function uploadQuickFile() {
        const freshQuickFileInput = document.getElementById('quick-file-input');
        if (!freshQuickFileInput.files.length) {
            showToast('لطفاً یک فایل انتخاب کنید', 'error');
            return;
        }
        
        const uploadProgress = document.getElementById('upload-progress');
        const progressFill = document.getElementById('progress-fill');
        
        uploadProgress.style.display = 'block';
        progressFill.style.width = '0%';
        
        try {
            const formData = new FormData(document.getElementById('quick-upload-form'));
            
            // Show progress (simulated)
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                if (progress <= 90) {
                    progressFill.style.width = progress + '%';
                }
            }, 100);
            
            const result = await sendRequest(formData);
            
            clearInterval(progressInterval);
            progressFill.style.width = '100%';
            
            showToast(result.message);
            
            // Reset form
            document.getElementById('quick-upload-form').reset();
            document.querySelector('#quick-upload-zone p').textContent = 'فایل را اینجا رها کنید یا کلیک کنید';
            
            setTimeout(() => {
                uploadProgress.style.display = 'none';
                
                // Auto-select the uploaded file
                if (modalObject.onSelect && result.media) {
                    modalObject.onSelect(result.media);
                }
            }, 500);
            
        } catch (error) {
            showToast(error.message, 'error');
            uploadProgress.style.display = 'none';
        }
    }
    
    mediaPickerModalInstance = modalObject;
    return modalObject;
}

});
