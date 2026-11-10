# Dental Show 2027 - Static HTML Website

This is a complete, standalone static HTML copy of the **Dental Show 2027** website.

## Directory Structure
- `index.html` — Homepage
- `coming-soon/index.html` — Fit-to-screen Coming Soon page (with Mascot, Countdown, Official Social Links)
- `about/index.html` — About Us
- `events/index.html` — Events directory and detail pages
- `gallery/index.html` — Gallery
- `contact/index.html` — Contact Us
- `visitor-registration/index.html` — Visitor Registration (with official iframe form)
- `exhibitor-registration/index.html` — Exhibitor Registration (with official iframe form)
- `images/` — All media assets (logos, mascots, venue photos)
- `css/` — Stylesheets

## How to Test / Preview Locally
You can preview this website locally using any static web server:

```bash
# Using Python:
python -m http.server 8080

# Or using Node.js:
npx serve .
```

Then open `http://localhost:8080` in your browser.

## Deployment
This folder can be deployed directly to:
- **cPanel / Apache / Nginx** (`public_html` directory)
- **Vercel / Netlify / GitHub Pages / Cloudflare Pages** (as a pure static website with no build step required)
