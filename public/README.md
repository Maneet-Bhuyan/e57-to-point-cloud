# Hostinger deployment (`public/`)

Upload **everything in this folder** to your Hostinger `public_html` directory.

## Files to upload

```
public_html/
├── index.php
├── default.php
├── config.php          ← Edit FLASK_BACKEND_URL
├── .htaccess
├── php.ini             (or copy settings to .user.ini)
├── .user.ini
├── includes/
│   └── api-proxy.php
└── views/
    └── landing.php
```

## Configuration

Open `config.php` and set your VPS Flask URL:

```php
const FLASK_BACKEND_URL = 'http://YOUR.VPS.IP:5000';
```

## Requirements

- PHP with **cURL** enabled
- Flask API running on your VPS (see `../docs/SETUP.md`)
