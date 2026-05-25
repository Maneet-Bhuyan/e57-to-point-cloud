# Backend (Flask API)

## Run

```powershell
pip install -r requirements.txt
python app.py
```

API listens on `http://0.0.0.0:5000`.

## Folders

| Path | Purpose |
|------|---------|
| `uploads/` | Temporary `.e57` files (deleted after processing) |
| `static/meshes/` | Generated `.obj` output |
| `scripts/pipeline_cli.py` | Legacy standalone CLI (not required for web app) |

## Endpoints

- `GET /health`
- `POST /process` → `{ "job_id": "..." }`
- `GET /process/status/<job_id>`
- `GET /static/meshes/<filename>`

See [../docs/SETUP.md](../docs/SETUP.md) for full setup.
