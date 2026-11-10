# Dental Show 2027 - Static HTML Website

This is the complete, production-ready static HTML website for **Dental Show 2027** organized by **TNDTMA** (Tamilnadu Dental Traders & Manufacturers Association) at Chennai Trade Centre.

## Directory Structure

```text
├── .htaccess                     # Production Apache config (SSL, clean URLs, security headers, caching)
├── 404.html                      # Branded 404 error page
├── 500.html                      # Branded 500 server error page
├── robots.txt                    # Search engine crawler instructions
├── sitemap.xml                   # XML sitemap with all core pages and 14 event editions
│
├── index.html                    # Coming Soon page (active default with live countdown & mascot)
├── home.html                     # Full website homepage
├── about.html                    # About Us (20-year history & TNDTMA mission)
├── events.html                   # All events catalog (2013–2027)
├── gallery.html                  # Photo & highlight gallery
├── contact.html                  # Contact Us & venue location
├── visitor-registration.html     # Visitor Registration form
├── exhibitor-registration.html   # Exhibitor Stall Booking form
├── privacy-policy.html           # Privacy policy
├── terms-and-conditions.html     # Terms and conditions
├── disclaimer.html               # Legal disclaimer
│
├── events/                       # 14 Landmark edition detail pages
│   ├── dental-show-2013.html
│   ├── dental-show-2015.html
│   ├── ...
│   └── dental-show-2027.html
│
├── brochures/                    # PDF downloads
│   └── dental-show-brochure.pdf
├── css/                          # Stylesheets
│   ├── style.css
│   └── xsIcon.css
├── images/                       # Assets, logos, and photos
└── js/                           # JavaScript (main.js)
```

---

## Switching Between "Coming Soon" and "Full Website"

In [`.htaccess`](.htaccess#L12), the `DirectoryIndex` directive controls which page visitors see first:

- **Coming Soon Mode (Default)**:
  ```apache
  DirectoryIndex index.html home.html index.php
  ```
- **Launch Full Website**:
  Change the order so `home.html` is prioritized:
  ```apache
  DirectoryIndex home.html index.html index.php
  ```

---

## Local Testing / Preview

You can preview the website locally using any standard static server:

```powershell
# Using Python:
python -m http.server 8080

# Or using Node.js:
npx serve .
```

Then visit [http://localhost:8080](http://localhost:8080) in your browser.

---

## Production Deployment (cPanel / Apache)

1. Upload all files into your domain's `public_html` directory.
2. Ensure the hidden [`.htaccess`](.htaccess) file is included in the upload.
3. The `.htaccess` file will automatically handle:
   - **HTTPS Redirection**: Redirects HTTP visitors to HTTPS.
   - **Clean URLs**: Routes `/about` to `about.html`, `/events/dental-show-2027` to `events/dental-show-2027.html`.
   - **Gzip Compression**: Compresses HTML, CSS, JS, SVG, and fonts.
   - **Browser Caching**: Sets 1-year cache headers for static media, CSS, and fonts.
   - **Security Headers**: Adds clickjacking, MIME-sniffing, and XSS protection.
   - **SSL Renewals**: Allows Let's Encrypt / AutoSSL verification via `/.well-known/`.
