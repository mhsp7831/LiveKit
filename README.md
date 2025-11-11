# LiveKit

[فارسی](#فارسی) | [English](#english)

---

## English

### 📺 Overview

LiveKit is a comprehensive web-based platform for managing and displaying live stream events. It provides a powerful dashboard for administrators to create multiple events, manage media libraries, configure appearance, and control access through phone number validation.

### ✨ Key Features

#### 🎯 Event Management

- Create and manage multiple live stream events
- Each event has its own unique ID and configuration
- Customize event titles, schedules, and branding
- Easy event switching in the dashboard

#### 🎨 Full Customization

- **Color Themes**: Customize all colors including background, primary, text, and card colors
- **Custom CSS**: Add your own CSS for advanced styling
- **Banners & Images**: Upload logos, pre-stream, post-stream, and promotional banners
- **Social Media Links**: Add social media buttons with custom icons
- **Action Buttons**: Create custom call-to-action buttons

#### ⏰ Countdown Timer

- Automatic countdown display before stream starts
- Configurable player reveal offset (show player before countdown ends)
- Smart state management (pre-stream, live, post-stream)

#### 📝 Dynamic Subtitles

- Real-time scrolling subtitles during the stream
- Configurable scroll speed and update interval
- Optional links for each subtitle
- JSON-based subtitle management with live updates

#### 📱 Phone Number Validation

- Restrict access to authorized phone numbers only
- CSV import for bulk phone number uploads
- Persian phone number format support (09xxxxxxxxx)
- View statistics of authorized numbers

#### 📁 Media Library

- Centralized media management for all events
- Upload images (JPG, PNG, GIF, WebP, SVG)
- Search and filter by file type
- Track media usage across events
- Automatic cleanup of unused files
- Support for tags and descriptions

#### 🕐 Version History

- Automatic configuration versioning (last 10 versions)
- View and compare different versions
- One-click restore to previous configurations
- Track changes by user and timestamp

#### 👥 User Management

- Owner and Admin role system
- Secure password hashing
- User creation, editing, and deletion
- Session management with CSRF protection

#### 💾 Backup & Restore

- Export configurations and subtitles as JSON
- Import backup files to restore settings
- Download uploaded media as ZIP archive
- Download phone number lists as CSV

### 🛠️ Technical Stack

- **Backend**: PHP 7.4+ (SQLite database)
- **Frontend**: Vanilla JavaScript, CSS3
- **Libraries**:
  - SortableJS (drag-and-drop sorting)
  - CodeMirror (CSS editor)
  - Tippy.js (tooltips)
  - Prism.js (code highlighting)

### 📋 Requirements

- PHP 7.4 or higher
- SQLite3 extension enabled
- Apache/Nginx web server
- mod_rewrite enabled (Apache)

### 🚀 Installation

1. **Upload Files**

   ```bash
   # Upload the entire project to your web server
   # Recommended structure:
   /var/www/html/livekit/
   ```

2. **Set Permissions**

   ```bash
   chmod 755 config/
   chmod 644 config/*.php
   chmod 666 config/live_database.sqlite
   chmod 755 config/uploads/
   chmod 755 config/events/
   ```

3. **Configure Web Server**

   **Apache (.htaccess is included)**

   ```apache
   # Make sure mod_rewrite is enabled
   # The .htaccess file in config/uploads/ prevents script execution
   ```

   **Nginx**

   ```nginx
   location /livekit {
       try_files $uri $uri/ =404;

       location ~ \.php$ {
           include fastcgi_params;
           fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
       }

       location /livekit/config/uploads {
           location ~ \.(php|phtml|php3|php4|php5|php7|pl|py|cgi|asp|js)$ {
               deny all;
           }
       }
   }
   ```

4. **Access the Application**

   ```
   Navigate to: http://yourdomain.com/livekit/config/

   Default credentials:
   Username: owner
   Password: 123456

   ⚠️ IMPORTANT: Change the password immediately after first login!
   ```

5. **Post-Installation**
   - Log in with default credentials
   - Go to Users tab and change the owner password
   - Create your first event
   - Configure event settings

### 📖 Usage Guide

#### Creating an Event

1. Log in to the dashboard
2. Click the **+** button in the header
3. Enter event name
4. Configure event settings (schedule, appearance, media)
5. Save changes

#### Uploading Media

1. Go to **Media Library** tab
2. Drag & drop or click to select files
3. Add optional description and tags
4. Files are now available for all events

#### Setting Up Phone Validation

1. Navigate to **Phone Validation** tab
2. Toggle "Enable phone validation"
3. Upload CSV file with phone numbers (one per line)
4. Format: `09123456789` or `+989123456789`

#### Customizing Appearance

1. Go to **Appearance** tab
2. Upload logos and banners
3. Adjust color scheme
4. Add custom CSS if needed
5. Preview changes before saving

#### Managing Subtitles

1. Navigate to **Subtitles** tab
2. Click **+** to add new subtitle
3. Enter text and optional link
4. Drag to reorder
5. Changes appear live on the stream page

#### Viewing Live Stream Page

- Click the **eye icon** in the header
- Or visit: `http://yourdomain.com/livekit/?event=YOUR_EVENT_ID`

### 🔒 Security Features

- **CSRF Protection**: All forms protected with CSRF tokens
- **Input Validation**: Comprehensive validation for all user inputs
- **SQL Injection Prevention**: Prepared statements throughout
- **File Upload Security**:
  - Type validation (whitelist approach)
  - Extension verification
  - MIME type checking
  - Script execution prevention in upload directory
- **Session Security**:
  - Session regeneration on login
  - Secure session handling
  - Automatic timeout
- **Password Security**: bcrypt hashing
- **Access Control**: Role-based permissions (Owner/Admin)

### 📁 Directory Structure

```
.
├── config/                      # Main application directory
│   ├── assets/                  # Static assets
│   │   ├── css/                 # Stylesheets
│   │   │   ├── dashboard.css    # Dashboard styles
│   │   │   ├── login.css        # Login page styles
│   │   │   ├── prism.css        # Code highlighting
│   │   │   └── theme.css        # Theme variables
│   │   └── js/                  # JavaScript files
│   │       ├── dashboard.js     # Dashboard functionality
│   │       └── prism.js         # Code highlighting
│   ├── events/                  # Event configurations
│   │   └── event_xxxxx/         # Individual event folder
│   │       ├── configs.json     # Event settings
│   │       ├── custom.css       # Custom CSS
│   │       └── subtitles.json   # Subtitle data
│   ├── uploads/                 # Uploaded media files
│   │   ├── event_xxxxx/         # Event-specific media
│   │   │   └── media/           # Media library files
│   │   └── .htaccess            # Security rules
│   ├── ajax-handler.php         # AJAX request handler
│   ├── app.log                  # Application logs
│   ├── dashboard.php            # Admin dashboard
│   ├── events.json              # Events list
│   ├── functions.php            # Core functions
│   ├── get-subtitles.php        # Subtitle API endpoint
│   ├── index.php                # Dashboard entry point
│   ├── live_database.sqlite     # SQLite database
│   ├── login.php                # Login page
│   └── logout.php               # Logout handler
└── index.php                    # Public stream viewer
```

### 🔧 Configuration Files

#### configs.json

```json
{
  "title": "Event Title",
  "homePage": "https://example.com",
  "iframe": "<iframe src='...'></iframe>",
  "liveStart": "2024-01-01T20:00",
  "liveEnd": "2024-01-01T22:00",
  "playerRevealOffset": 0,
  "fetchInterval": 8000,
  "scrollSpeed": 50,
  "logo": "config/uploads/event_xxx/logo.png",
  "preBanner": "...",
  "endBanner": "...",
  "banner": "...",
  "bannerLink": "...",
  "copyright": "© 2024 Company",
  "colors": {
    "bg": "#ffffff",
    "title": "#000000",
    "primary": "#4caf50",
    "primary-hover": "#45a049",
    "card-bg": "#f8f9fa",
    "placeholder": "#e9ecef",
    "placeholder-border": "#ced4da",
    "text": "#212529"
  },
  "buttons": [{ "title": "Register", "link": "https://..." }],
  "socials": [{ "title": "Instagram", "link": "https://...", "icon": "..." }]
}
```

#### subtitles.json

```json
[
  {
    "text": "Welcome to our live stream!",
    "link": "https://example.com"
  },
  {
    "text": "Check our website for more info",
    "link": ""
  }
]
```

### 🐛 Troubleshooting

#### Issue: Cannot log in

- **Solution**: Check that `live_database.sqlite` has write permissions (666)
- Verify PHP session is working: `session.save_path` in php.ini

#### Issue: Media uploads fail

- **Solution**: Check `config/uploads/` directory permissions (755)
- Verify PHP `upload_max_filesize` and `post_max_size` settings
- Check web server error logs

#### Issue: Subtitles not updating

- **Solution**: Check `config/events/event_xxx/subtitles.json` permissions
- Verify browser console for JavaScript errors
- Check `fetchInterval` setting (default: 8000ms)

#### Issue: 403 Forbidden on uploaded files

- **Solution**: Check `.htaccess` file in `config/uploads/`
- Verify Apache mod_rewrite is enabled
- For Nginx, add location block to deny script execution

#### Issue: Database errors

- **Solution**: Check SQLite extension is enabled: `php -m | grep sqlite`
- Verify database file permissions
- Check disk space

### 🔄 Updating

1. **Backup Current Installation**

   ```bash
   # Backup database
   cp config/live_database.sqlite config/live_database.sqlite.backup

   # Backup uploads
   tar -czf uploads_backup.tar.gz config/uploads/

   # Backup events
   tar -czf events_backup.tar.gz config/events/
   ```

2. **Update Files**

   - Replace all PHP and JS files
   - Keep database, uploads, and events folders

3. **Verify Permissions**
   ```bash
   chmod 666 config/live_database.sqlite
   chmod -R 755 config/uploads/
   chmod -R 755 config/events/
   ```

### 📝 License

This project is proprietary software. All rights reserved.

### 🤝 Support

For support, please contact: [your-email@example.com]

### 📚 Additional Resources

- [PHP Documentation](https://www.php.net/docs.php)
- [SQLite Documentation](https://www.sqlite.org/docs.html)
- [CodeMirror Documentation](https://codemirror.net/doc/)

---

## فارسی

### 📺 معرفی

LiveKit یک پلتفرم جامع تحت وب برای مدیریت و نمایش رویدادهای پخش زنده است. این سیستم یک داشبورد قدرتمند برای مدیران فراهم می‌کند تا بتوانند رویدادهای متعدد ایجاد کنند، کتابخانه رسانه را مدیریت کنند، ظاهر را تنظیم کنند و دسترسی را از طریق اعتبارسنجی شماره تلفن کنترل کنند.

### ✨ ویژگی‌های کلیدی

#### 🎯 مدیریت رویداد

- ایجاد و مدیریت رویدادهای پخش زنده متعدد
- هر رویداد دارای شناسه و پیکربندی منحصر به فرد خود
- شخصی‌سازی عناوین، زمان‌بندی و برندینگ رویداد
- تعویض آسان رویداد در داشبورد

#### 🎨 شخصی‌سازی کامل

- **تم رنگی**: سفارشی‌سازی تمام رنگ‌ها شامل پس‌زمینه، اصلی، متن و کارت
- **CSS سفارشی**: افزودن CSS شخصی برای استایل‌دهی پیشرفته
- **بنرها و تصاویر**: آپلود لوگو، بنر قبل از پخش، بعد از پخش و بنرهای تبلیغاتی
- **لینک‌های شبکه‌های اجتماعی**: افزودن دکمه‌های شبکه‌های اجتماعی با آیکون‌های سفارشی
- **دکمه‌های اقدام**: ایجاد دکمه‌های فراخوان به اقدام سفارشی

#### ⏰ تایمر شمارش معکوس

- نمایش خودکار شمارش معکوس قبل از شروع پخش
- تنظیم زمان نمایش پلیر قبل از اتمام شمارش معکوس
- مدیریت هوشمند وضعیت (قبل از پخش، در حال پخش، بعد از پخش)

#### 📝 زیرنویس‌های پویا

- زیرنویس‌های متحرک در زمان واقعی طی پخش
- قابلیت تنظیم سرعت اسکرول و فاصله به‌روزرسانی
- لینک اختیاری برای هر زیرنویس
- مدیریت زیرنویس مبتنی بر JSON با به‌روزرسانی زنده

#### 📱 اعتبارسنجی شماره تلفن

- محدود کردن دسترسی فقط به شماره‌های تلفن مجاز
- وارد کردن دسته‌ای شماره‌ها از طریق فایل CSV
- پشتیبانی از فرمت شماره تلفن فارسی (09xxxxxxxxx)
- مشاهده آمار شماره‌های مجاز

#### 📁 کتابخانه رسانه

- مدیریت متمرکز رسانه برای تمام رویدادها
- آپلود تصاویر (JPG، PNG، GIF، WebP، SVG)
- جستجو و فیلتر بر اساس نوع فایل
- ردیابی استفاده از رسانه در رویدادها
- پاکسازی خودکار فایل‌های استفاده نشده
- پشتیبانی از برچسب و توضیحات

#### 🕐 تاریخچه نسخه‌ها

- نسخه‌بندی خودکار پیکربندی (10 نسخه آخر)
- مشاهده و مقایسه نسخه‌های مختلف
- بازگردانی با یک کلیک به پیکربندی‌های قبلی
- ردیابی تغییرات بر اساس کاربر و زمان

#### 👥 مدیریت کاربران

- سیستم نقش Owner و Admin
- هش کردن امن رمز عبور
- ایجاد، ویرایش و حذف کاربر
- مدیریت نشست با محافظت CSRF

#### 💾 پشتیبان‌گیری و بازیابی

- صادرات پیکربندی و زیرنویس‌ها به صورت JSON
- وارد کردن فایل‌های پشتیبان برای بازیابی تنظیمات
- دانلود رسانه‌های آپلود شده به صورت آرشیو ZIP
- دانلود لیست شماره تلفن‌ها به صورت CSV

### 🛠️ فناوری‌های استفاده شده

- **بک‌اند**: PHP 7.4+ (پایگاه داده SQLite)
- **فرانت‌اند**: JavaScript خالص، CSS3
- **کتابخانه‌ها**:
  - SortableJS (مرتب‌سازی با کشیدن و رها کردن)
  - CodeMirror (ویرایشگر CSS)
  - Tippy.js (tooltip)
  - Prism.js (هایلایت کد)

### 📋 پیش‌نیازها

- PHP نسخه 7.4 یا بالاتر
- فعال بودن افزونه SQLite3
- وب سرور Apache/Nginx
- فعال بودن mod_rewrite (Apache)

### 🚀 نصب

1. **آپلود فایل‌ها**

   ```bash
   # کل پروژه را به وب سرور خود آپلود کنید
   # ساختار پیشنهادی:
   /var/www/html/livekit/
   ```

2. **تنظیم دسترسی‌ها**

   ```bash
   chmod 755 config/
   chmod 644 config/*.php
   chmod 666 config/live_database.sqlite
   chmod 755 config/uploads/
   chmod 755 config/events/
   ```

3. **پیکربندی وب سرور**

   **Apache (فایل .htaccess موجود است)**

   ```apache
   # اطمینان حاصل کنید که mod_rewrite فعال است
   # فایل .htaccess در config/uploads/ از اجرای اسکریپت جلوگیری می‌کند
   ```

   **Nginx**

   ```nginx
   location /livekit {
       try_files $uri $uri/ =404;

       location ~ \.php$ {
           include fastcgi_params;
           fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
       }

       location /livekit/config/uploads {
           location ~ \.(php|phtml|php3|php4|php5|php7|pl|py|cgi|asp|js)$ {
               deny all;
           }
       }
   }
   ```

4. **دسترسی به برنامه**

   ```
   به آدرس زیر بروید: http://yourdomain.com/livekit/config/

   اطلاعات ورود پیش‌فرض:
   نام کاربری: owner
   رمز عبور: 123456

   ⚠️ مهم: رمز عبور را بلافاصله بعد از اولین ورود تغییر دهید!
   ```

5. **بعد از نصب**
   - با اطلاعات پیش‌فرض وارد شوید
   - به بخش کاربران بروید و رمز عبور owner را تغییر دهید
   - اولین رویداد خود را ایجاد کنید
   - تنظیمات رویداد را پیکربندی کنید

### 📖 راهنمای استفاده

#### ایجاد رویداد

1. به داشبورد وارد شوید
2. روی دکمه **+** در هدر کلیک کنید
3. نام رویداد را وارد کنید
4. تنظیمات رویداد را پیکربندی کنید (زمان‌بندی، ظاهر، رسانه)
5. تغییرات را ذخیره کنید

#### آپلود رسانه

1. به بخش **کتابخانه رسانه** بروید
2. فایل را با کشیدن و رها کردن یا کلیک انتخاب کنید
3. توضیحات و برچسب‌های اختیاری را اضافه کنید
4. فایل‌ها اکنون برای تمام رویدادها در دسترس هستند

#### تنظیم اعتبارسنجی تلفن

1. به بخش **اعتبارسنجی تلفن** بروید
2. گزینه "فعال‌سازی اعتبارسنجی شماره تلفن" را روشن کنید
3. فایل CSV حاوی شماره تلفن‌ها را آپلود کنید (هر خط یک شماره)
4. فرمت: `09123456789` یا `+989123456789`

#### شخصی‌سازی ظاهر

1. به بخش **ظاهر** بروید
2. لوگو و بنرها را آپلود کنید
3. طرح رنگ را تنظیم کنید
4. در صورت نیاز CSS سفارشی اضافه کنید
5. قبل از ذخیره، تغییرات را پیش‌نمایش کنید

#### مدیریت زیرنویس‌ها

1. به بخش **زیرنویس** بروید
2. روی **+** کلیک کنید تا زیرنویس جدید اضافه شود
3. متن و لینک اختیاری را وارد کنید
4. برای تغییر ترتیب، بکشید و رها کنید
5. تغییرات به صورت زنده در صفحه پخش نمایش داده می‌شود

#### مشاهده صفحه پخش زنده

- روی **آیکون چشم** در هدر کلیک کنید
- یا به آدرس زیر بروید: `http://yourdomain.com/livekit/?event=YOUR_EVENT_ID`

### 🔒 ویژگی‌های امنیتی

- **محافظت CSRF**: تمام فرم‌ها با توکن CSRF محافظت شده‌اند
- **اعتبارسنجی ورودی**: اعتبارسنجی جامع برای تمام ورودی‌های کاربر
- **جلوگیری از SQL Injection**: استفاده از دستورات آماده در سراسر سیستم
- **امنیت آپلود فایل**:
  - اعتبارسنجی نوع (رویکرد لیست سفید)
  - بررسی پسوند
  - بررسی MIME type
  - جلوگیری از اجرای اسکریپت در دایرکتوری آپلود
- **امنیت نشست**:
  - بازسازی نشست در هنگام ورود
  - مدیریت امن نشست
  - تایم‌اوت خودکار
- **امنیت رمز عبور**: هش کردن با bcrypt
- **کنترل دسترسی**: مجوزهای مبتنی بر نقش (Owner/Admin)

### 📁 ساختار دایرکتوری

```
.
├── config/                      # دایرکتوری اصلی برنامه
│   ├── assets/                  # فایل‌های استاتیک
│   │   ├── css/                 # استایل‌ها
│   │   │   ├── dashboard.css    # استایل داشبورد
│   │   │   ├── login.css        # استایل صفحه ورود
│   │   │   ├── prism.css        # هایلایت کد
│   │   │   └── theme.css        # متغیرهای تم
│   │   └── js/                  # فایل‌های جاوااسکریپت
│   │       ├── dashboard.js     # عملکرد داشبورد
│   │       └── prism.js         # هایلایت کد
│   ├── events/                  # پیکربندی رویدادها
│   │   └── event_xxxxx/         # پوشه رویداد
│   │       ├── configs.json     # تنظیمات رویداد
│   │       ├── custom.css       # CSS سفارشی
│   │       └── subtitles.json   # داده زیرنویس
│   ├── uploads/                 # فایل‌های رسانه آپلود شده
│   │   ├── event_xxxxx/         # رسانه مختص رویداد
│   │   │   └── media/           # فایل‌های کتابخانه رسانه
│   │   └── .htaccess            # قوانین امنیتی
│   ├── ajax-handler.php         # مدیریت درخواست‌های AJAX
│   ├── app.log                  # لاگ‌های برنامه
│   ├── dashboard.php            # داشبورد مدیریت
│   ├── events.json              # لیست رویدادها
│   ├── functions.php            # توابع اصلی
│   ├── get-subtitles.php        # API زیرنویس
│   ├── index.php                # نقطه ورود داشبورد
│   ├── live_database.sqlite     # پایگاه داده SQLite
│   ├── login.php                # صفحه ورود
│   └── logout.php               # مدیریت خروج
└── index.php                    # نمایشگر پخش عمومی
```

### 🔧 فایل‌های پیکربندی

#### configs.json

```json
{
  "title": "عنوان رویداد",
  "homePage": "https://example.com",
  "iframe": "<iframe src='...'></iframe>",
  "liveStart": "2024-01-01T20:00",
  "liveEnd": "2024-01-01T22:00",
  "playerRevealOffset": 0,
  "fetchInterval": 8000,
  "scrollSpeed": 50,
  "logo": "config/uploads/event_xxx/logo.png",
  "preBanner": "...",
  "endBanner": "...",
  "banner": "...",
  "bannerLink": "...",
  "copyright": "© 2024 Company",
  "colors": {
    "bg": "#ffffff",
    "title": "#000000",
    "primary": "#4caf50",
    "primary-hover": "#45a049",
    "card-bg": "#f8f9fa",
    "placeholder": "#e9ecef",
    "placeholder-border": "#ced4da",
    "text": "#212529"
  },
  "buttons": [{ "title": "ثبت‌نام", "link": "https://..." }],
  "socials": [{ "title": "اینستاگرام", "link": "https://...", "icon": "..." }]
}
```

#### subtitles.json

```json
[
  {
    "text": "به پخش زنده ما خوش آمدید!",
    "link": "https://example.com"
  },
  {
    "text": "برای اطلاعات بیشتر به وب‌سایت ما مراجعه کنید",
    "link": ""
  }
]
```

### 🐛 عیب‌یابی

#### مشکل: امکان ورود وجود ندارد

- **راه‌حل**: بررسی کنید که `live_database.sqlite` دارای مجوز نوشتن (666) است
- بررسی کنید session در PHP کار می‌کند: `session.save_path` در php.ini

#### مشکل: آپلود رسانه با شکست مواجه می‌شود

- **راه‌حل**: مجوزهای دایرکتوری `config/uploads/` را بررسی کنید (755)
- تنظیمات `upload_max_filesize` و `post_max_size` در PHP را بررسی کنید
- لاگ‌های خطای وب سرور را بررسی کنید

#### مشکل: زیرنویس‌ها به‌روز نمی‌شوند

- **راه‌حل**: مجوزهای `config/events/event_xxx/subtitles.json` را بررسی کنید
- کنسول مرورگر را برای خطاهای JavaScript بررسی کنید
- تنظیم `fetchInterval` را بررسی کنید (پیش‌فرض: 8000ms)

#### مشکل: 403 Forbidden روی فایل‌های آپلود شده

- **راه‌حل**: فایل `.htaccess` در `config/uploads/` را بررسی کنید

- بررسی کنید mod_rewrite آپاچی فعال باشد

- برای Nginx، بلاک location برای جلوگیری از اجرای اسکریپت را اضافه کنید

#### مشکل: خطاهای پایگاه داده

- **راه‌حل**: بررسی کنید افزونه SQLite فعال است: `php -m | grep sqlite`

- مجوزهای فایل پایگاه داده را بررسی کنید

- فضای دیسک را بررسی کنید

### 🔄 به‌روزرسانی

1.  **پشتیبان‌گیری از نصب فعلی**

    ```bash

    # Backup database

    cp config/live_database.sqlite config/live_database.sqlite.backup



    # Backup uploads

    tar -czf uploads_backup.tar.gz config/uploads/



    # Backup events

    tar -czf events_backup.tar.gz config/events/

    ```

2.  **به‌روزرسانی فایل‌ها**

    - تمام فایل‌های PHP و JS را جایگزین کنید

    - پوشه‌های پایگاه داده، آپلودها و رویدادها را نگه دارید

3.  **بررسی مجوزها**

    ```bash

    chmod 666 config/live_database.sqlite

    chmod -R 755 config/uploads/

    chmod -R 755 config/events/

    ```

### 📝 مجوز

این پروژه یک نرم‌افزار اختصاصی است. تمام حقوق محفوظ است.

### 🤝 پشتیبانی

برای پشتیبانی، لطفا تماس بگیرید: [your-email@example.com]

### 📚 منابع اضافی

- [مستندات PHP](https://www.php.net/docs.php)

- [مستندات SQLite](https://www.sqlite.org/docs.html)

- [مستندات CodeMirror](https://codemirror.net/doc/)
