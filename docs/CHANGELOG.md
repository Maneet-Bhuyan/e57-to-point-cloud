# Changelog

## 2026-05-25 — Project restructure

### Folder layout (clean)
- `backend/` — Flask API (`app.py`, `requirements.txt`, `static/`, `uploads/`)
- `public/` — Hostinger-ready PHP frontend (upload to `public_html`)
- `docs/` — Setup guide, changelog, team info

### Removed (obsolete)
- `form.html`, `login.html`, `results.html`, `results2.html`
- Nested `Shubh-Ps-main/` duplicate folder
- Flask `templates/index.html` (replaced by `public/views/landing.php`)
- Scattered root-level PHP/config files (consolidated into `public/`)

### Moved
- `final.py` → `backend/scripts/pipeline_cli.py` (optional CLI)
- `LOCAL_SETUP.md` → `docs/SETUP.md`
- `readme_updates.txt` → `docs/CHANGELOG.md`

---

## 2026-05-25 — Frontend overhaul v2

- 4-section premium landing page (Hero, App, Pipeline, Features)
- Fixed "Unexpected end of JSON input" (robust PHP proxy + JS parsing)
- Async job polling with live pipeline checklist
- Three.js OBJ preview
- Granular sliders wired to Flask API

## 2026-05-25 — Initial Hostinger frontend

- `default.php` / `index.php` with PHP → Flask proxy
- Tailwind dark UI, drag-drop upload
- `.htaccess` / `php.ini` for 1024M uploads
