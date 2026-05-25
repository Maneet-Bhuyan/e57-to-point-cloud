# E57 Meshify — Local Setup

The **"Unexpected end of JSON input"** error means the browser received an **empty or non-JSON** response — usually because Flask is **not running** at `http://127.0.0.1:5000`.

Follow these steps **in order**.

---

## Part A — Python backend (Flask)

### 1. Open a terminal in the `backend/` folder

```powershell
cd backend
```

*(From your project root, e.g. `cd "m:\Cloud point\Shubh-Ps-main - Copy\backend"`)*

### 2. Create a virtual environment (recommended)

```powershell
python -m venv ..\.venv
..\.venv\Scripts\Activate.ps1
```

If PowerShell blocks activation:

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
..\.venv\Scripts\Activate.ps1
```

### 3. Install dependencies

```powershell
pip install --upgrade pip
pip install -r requirements.txt
```

Installs: **Flask**, **numpy**, **scikit-learn**, **open3d**, **pye57**.

> Open3D can take several minutes on Windows.

### 4. Start the API server

```powershell
python app.py
```

Expected output:

```
 * Running on http://0.0.0.0:5000
```

**Leave this terminal open** while using the web app.

### 5. Verify the API

Open: [http://127.0.0.1:5000/health](http://127.0.0.1:5000/health)

```json
{"status":"online","service":"E57 Meshify API"}
```

---

## Part B — PHP frontend

### 1. Open a **second** terminal in the `public/` folder

```powershell
cd public
```

### 2. Start PHP's built-in server

```powershell
php -S localhost:8080
```

### 3. Open the app

[http://localhost:8080/index.php](http://localhost:8080/index.php)

The hero status should show **API Online** (green).

### 4. Process a file

1. Scroll to **Processing Workspace**
2. Drop a `.e57` file
3. Click **Generate Mesh**

---

## Part C — Configuration

| File | Location | Purpose |
|------|----------|---------|
| `config.php` | `public/` | `FLASK_BACKEND_URL` (default `http://127.0.0.1:5000`) |
| `index.php` | `public/` | Web entry point |
| `includes/api-proxy.php` | `public/` | Proxies API calls to Flask |
| `.htaccess` / `php.ini` | `public/` | Large upload limits |

**Production (VPS):** edit `public/config.php`:

```php
const FLASK_BACKEND_URL = 'http://YOUR.VPS.IP:5000';
```

---

## Part D — Hostinger deploy

Upload **all contents** of the `public/` folder to `public_html/`.

Run Flask on your VPS from the `backend/` folder (Part A).

See [public/README.md](../public/README.md).

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Unexpected end of JSON input` | Run `python app.py` in `backend/`; check `/health` |
| `API Offline` | Flask not running or wrong URL in `config.php` |
| `PHP cURL not enabled` | Enable curl in Hostinger PHP settings |
| Upload fails | Raise limits in `php.ini` / `.user.ini` |
| `Cannot reach Flask` | Fix `FLASK_BACKEND_URL`; open firewall port 5000 |

---

## Quick copy-paste

**Terminal 1 — Flask:**
```powershell
cd backend
python -m venv ..\.venv
..\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python app.py
```

**Terminal 2 — PHP UI:**
```powershell
cd public
php -S localhost:8080
```

Browser: **http://localhost:8080/index.php**
