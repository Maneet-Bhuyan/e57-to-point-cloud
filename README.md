# E57 Meshify

Convert `.e57` point cloud scans into 3D-printable `.obj` meshes using AI noise reduction, Ball Pivoting reconstruction, and Taubin smoothing.

## Project structure

```
e57-meshify/
├── README.md                 ← You are here
├── .gitignore
│
├── backend/                  ← Python Flask API (run locally or on VPS)
│   ├── app.py
│   ├── requirements.txt
│   ├── static/meshes/        ← Generated .obj files
│   ├── uploads/              ← Temporary .e57 uploads (auto-deleted)
│   └── scripts/
│       └── pipeline_cli.py   ← Optional standalone CLI (legacy)
│
├── public/                   ← Upload THIS folder to Hostinger public_html
│   ├── index.php
│   ├── default.php
│   ├── config.php            ← Set Flask backend URL here
│   ├── .htaccess
│   ├── php.ini / .user.ini
│   ├── includes/api-proxy.php
│   └── views/landing.php
│
└── docs/
    ├── SETUP.md              ← Install & run instructions
    ├── CHANGELOG.md          ← UI / API change history
    ├── PROJECT_ORIGINAL.md   ← Original project README
    └── TEAM.txt
```

## Quick start (local)

**Terminal 1 — Backend:**
```powershell
cd backend
python -m venv ..\.venv
..\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python app.py
```

**Terminal 2 — Frontend:**
```powershell
cd public
php -S localhost:8080
```

Open [http://localhost:8080/index.php](http://localhost:8080/index.php) — status should show **API Online**.

Full instructions: [docs/SETUP.md](docs/SETUP.md)

## Hostinger deploy

1. Upload **all contents** of `public/` to `public_html/`
2. Run Flask on your VPS (`backend/app.py`)
3. Edit `public/config.php` → set `FLASK_BACKEND_URL` to your VPS IP

See [public/README.md](public/README.md)

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | API status |
| POST | `/process` | Upload `.e57`, returns `job_id` |
| GET | `/process/status/<job_id>` | Pipeline progress |
| GET | `/static/meshes/<file>` | Download generated mesh |
