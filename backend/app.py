import os
import threading
import time
import uuid
from pathlib import Path

import numpy as np
import open3d as o3d
import pye57
from flask import Flask, jsonify, request, send_from_directory, url_for
from sklearn.ensemble import IsolationForest


BASE_DIR = Path(__file__).resolve().parent
UPLOAD_DIR = BASE_DIR / "uploads"
MESH_DIR = BASE_DIR / "static" / "meshes"
ALLOWED_EXTENSIONS = {".e57"}

PROCESSING_STEPS = [
    {"id": "ingesting", "label": "Ingesting File"},
    {"id": "noise_reduction", "label": "AI Noise Reduction"},
    {"id": "geometry_prep", "label": "Normal Estimation"},
    {"id": "surface_reconstruction", "label": "Ball Pivoting Reconstruction"},
    {"id": "mesh_optimization", "label": "Taubin Smoothing"},
]

app = Flask(__name__)
UPLOAD_DIR.mkdir(parents=True, exist_ok=True)
MESH_DIR.mkdir(parents=True, exist_ok=True)

_jobs = {}
_jobs_lock = threading.Lock()


def is_allowed_file(filename: str) -> bool:
    return Path(filename).suffix.lower() in ALLOWED_EXTENSIONS


def _clamp(value: float, low: float, high: float) -> float:
    return max(low, min(high, value))


def _parse_float(name: str, default: float, low: float, high: float) -> float:
    raw = request.form.get(name)
    if raw is None or raw == "":
        return default
    try:
        return _clamp(float(raw), low, high)
    except ValueError:
        return default


def _parse_int(name: str, default: int, low: int, high: int) -> int:
    raw = request.form.get(name)
    if raw is None or raw == "":
        return default
    try:
        return int(_clamp(int(raw), low, high))
    except ValueError:
        return default


def quality_to_multiplier(quality: str) -> float:
    quality_map = {"high": 2.0, "medium": 4.0, "low": 8.0}
    return quality_map.get(quality.lower(), 4.0)


def calculate_adaptive_radii(
    pcd: o3d.geometry.PointCloud, base_multiplier: float = 4.0
) -> list[float]:
    distances = pcd.compute_nearest_neighbor_distance()
    avg_dist = float(np.mean(distances))
    min_radius = base_multiplier * avg_dist
    max_radius = min_radius * 8.0
    radii = np.exp(np.linspace(np.log(min_radius), np.log(max_radius), 4))
    return radii.tolist()


def _set_job_step(job_id: str, step_id: str) -> None:
    with _jobs_lock:
        job = _jobs.get(job_id)
        if not job:
            return
        if step_id not in job["completed_steps"]:
            job["current_step"] = step_id


def _complete_job_step(job_id: str, step_id: str) -> None:
    with _jobs_lock:
        job = _jobs.get(job_id)
        if not job:
            return
        if step_id not in job["completed_steps"]:
            job["completed_steps"].append(step_id)


def _fail_job(job_id: str, message: str) -> None:
    with _jobs_lock:
        if job_id in _jobs:
            _jobs[job_id].update(
                {
                    "status": "error",
                    "error": message,
                    "current_step": None,
                    "result": None,
                }
            )


def _complete_job(job_id: str, result: dict) -> None:
    with _jobs_lock:
        if job_id in _jobs:
            _jobs[job_id].update(
                {
                    "status": "complete",
                    "current_step": None,
                    "error": None,
                    "result": result,
                }
            )


def _mesh_static_url(output_name: str) -> str:
    """Build mesh URL inside an active Flask app context."""
    try:
        return url_for("static", filename=f"meshes/{output_name}")
    except Exception:
        return f"/static/meshes/{output_name}"


def load_e57(file_path: str, contamination: float = 0.03) -> o3d.geometry.PointCloud:
    e57 = pye57.E57(file_path)
    points, colors = [], []

    for i in range(e57.scan_count):
        data = e57.read_scan(i, colors=True, intensity=True, ignore_missing_fields=True)
        xyz = np.vstack(
            (data["cartesianX"], data["cartesianY"], data["cartesianZ"])
        ).T

        has_rgb = all(
            channel in data for channel in ("colorRed", "colorGreen", "colorBlue")
        )
        if has_rgb:
            color = np.vstack(
                (
                    data["colorRed"] / 255.0,
                    data["colorGreen"] / 255.0,
                    data["colorBlue"] / 255.0,
                )
            ).T
        elif "intensity" in data:
            intensity = np.clip(data["intensity"] / 255.0, 0, 1)
            color = np.stack((intensity, intensity, intensity), axis=1)
        else:
            color = np.ones((xyz.shape[0], 3), dtype=np.float64) * 0.5

        points.append(xyz)
        colors.append(color)

    if not points:
        raise ValueError("No valid point data found in E57 file.")

    combined_points = np.vstack(points)
    combined_colors = np.vstack(colors)

    valid_mask = np.isfinite(combined_points).all(axis=1)
    combined_points = combined_points[valid_mask]
    combined_colors = combined_colors[valid_mask]

    iso = IsolationForest(contamination=contamination, random_state=42)
    inlier_mask = iso.fit_predict(combined_points) == 1
    combined_points = combined_points[inlier_mask]
    combined_colors = combined_colors[inlier_mask]

    pcd = o3d.geometry.PointCloud()
    pcd.points = o3d.utility.Vector3dVector(combined_points)
    pcd.colors = o3d.utility.Vector3dVector(combined_colors)

    pcd = pcd.voxel_down_sample(voxel_size=0.005)
    pcd, _ = pcd.remove_statistical_outlier(nb_neighbors=50, std_ratio=2.0)
    return pcd


def generate_bpa_mesh(
    pcd: o3d.geometry.PointCloud,
    base_multiplier: float = 4.0,
    smoothing_iterations: int = 5,
) -> o3d.geometry.TriangleMesh:
    radii = calculate_adaptive_radii(pcd, base_multiplier=base_multiplier)

    pcd.estimate_normals(
        search_param=o3d.geometry.KDTreeSearchParamHybrid(radius=0.15, max_nn=100)
    )
    pcd.orient_normals_consistent_tangent_plane(30)

    mesh = o3d.geometry.TriangleMesh.create_from_point_cloud_ball_pivoting(
        pcd, o3d.utility.DoubleVector(radii)
    )
    mesh = mesh.remove_degenerate_triangles()
    mesh = mesh.remove_duplicated_triangles()
    mesh = mesh.remove_duplicated_vertices()
    mesh.vertex_colors = pcd.colors
    mesh.compute_vertex_normals()
    mesh = mesh.filter_smooth_taubin(number_of_iterations=smoothing_iterations)
    return mesh


def _run_pipeline(
    job_id: str,
    upload_path: Path,
    output_path: Path,
    output_name: str,
    contamination: float,
    base_multiplier: float,
    smoothing_iterations: int,
) -> None:
    with app.app_context():
        try:
            _set_job_step(job_id, "ingesting")
            pcd = load_e57(str(upload_path), contamination=contamination)
            _complete_job_step(job_id, "ingesting")
            _complete_job_step(job_id, "noise_reduction")

            _set_job_step(job_id, "geometry_prep")
            mesh = generate_bpa_mesh(
                pcd,
                base_multiplier=base_multiplier,
                smoothing_iterations=smoothing_iterations,
            )
            _complete_job_step(job_id, "geometry_prep")
            _complete_job_step(job_id, "surface_reconstruction")

            _set_job_step(job_id, "mesh_optimization")
            o3d.io.write_triangle_mesh(
                str(output_path),
                mesh,
                write_vertex_colors=True,
                write_vertex_normals=True,
                compressed=True,
            )
            if not output_path.exists() or output_path.stat().st_size == 0:
                raise RuntimeError("Failed to write output mesh file.")

            _complete_job_step(job_id, "mesh_optimization")

            points_count = int(len(np.asarray(pcd.points)))
            triangles_count = int(len(np.asarray(mesh.triangles)))
            file_size = output_path.stat().st_size if output_path.exists() else 0

            _complete_job(
                job_id,
                {
                    "message": "Mesh generated successfully.",
                    "mesh_url": _mesh_static_url(output_name),
                    "mesh_file": output_name,
                    "points_processed": points_count,
                    "triangles_generated": triangles_count,
                    "file_size_bytes": file_size,
                    "file_size_human": _human_size(file_size),
                },
            )
        except ValueError as exc:
            _fail_job(job_id, f"Invalid input: {exc}")
        except Exception as exc:
            _fail_job(job_id, f"Processing failed: {exc}")
        finally:
            if upload_path.exists():
                try:
                    os.remove(upload_path)
                except OSError:
                    pass


def _human_size(num_bytes: int) -> str:
    units = ["B", "KB", "MB", "GB"]
    size = float(num_bytes)
    for unit in units:
        if size < 1024.0 or unit == units[-1]:
            return f"{size:.1f} {unit}"
        size /= 1024.0
    return f"{num_bytes} B"


@app.after_request
def add_cors_headers(response):
    response.headers["Access-Control-Allow-Origin"] = "*"
    response.headers["Access-Control-Allow-Headers"] = "Content-Type"
    response.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
    return response


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "online", "service": "E57 Meshify API"})


@app.route("/")
def index():
    return jsonify({
        "service": "E57 Meshify API",
        "status": "online",
        "endpoints": {
            "health": "GET /health",
            "process": "POST /process",
            "status": "GET /process/status/<job_id>",
            "mesh": "GET /static/meshes/<filename>",
        },
    })


@app.route("/process", methods=["POST", "OPTIONS"])
def process_e57():
    if request.method == "OPTIONS":
        return ("", 204)

    if "file" not in request.files:
        return jsonify({"error": "No file provided."}), 400

    file = request.files["file"]
    quality = request.form.get("quality", "Medium")

    contamination = _parse_float("contamination", 0.03, 0.005, 0.15)
    ball_radius = request.form.get("ball_radius_multiplier")
    if ball_radius is None or ball_radius == "":
        base_multiplier = quality_to_multiplier(quality)
    else:
        base_multiplier = _parse_float("ball_radius_multiplier", 4.0, 1.0, 12.0)
    smoothing_iterations = _parse_int("smoothing_iterations", 5, 1, 25)

    if file.filename == "":
        return jsonify({"error": "Empty filename."}), 400
    if not is_allowed_file(file.filename):
        return jsonify({"error": "Only .e57 files are supported."}), 400

    input_name = Path(file.filename).stem
    timestamp = int(time.time())
    job_id = str(uuid.uuid4())
    upload_path = UPLOAD_DIR / f"{input_name}_{timestamp}.e57"
    output_name = f"{input_name}_{quality.lower()}_{timestamp}.obj"
    output_path = MESH_DIR / output_name

    try:
        file.save(upload_path)
    except Exception as exc:
        return jsonify({"error": f"Failed to save upload: {exc}"}), 500

    with _jobs_lock:
        _jobs[job_id] = {
            "status": "running",
            "current_step": "ingesting",
            "completed_steps": [],
            "steps": PROCESSING_STEPS,
            "error": None,
            "result": None,
        }

    try:
        thread = threading.Thread(
            target=_run_pipeline,
            args=(
                job_id,
                upload_path,
                output_path,
                output_name,
                contamination,
                base_multiplier,
                smoothing_iterations,
            ),
            daemon=True,
        )
        thread.start()
    except Exception as exc:
        _fail_job(job_id, f"Failed to start processing: {exc}")
        if upload_path.exists():
            try:
                os.remove(upload_path)
            except OSError:
                pass
        return jsonify({"error": f"Failed to start processing: {exc}"}), 500

    return jsonify({"job_id": job_id, "message": "Processing started."}), 202


@app.route("/process/status/<job_id>", methods=["GET"])
def process_status(job_id: str):
    with _jobs_lock:
        job = _jobs.get(job_id)

    if not job:
        return jsonify({"error": "Job not found."}), 404

    payload = {
        "job_id": job_id,
        "status": job["status"],
        "current_step": job.get("current_step"),
        "completed_steps": job.get("completed_steps", []),
        "steps": job.get("steps", PROCESSING_STEPS),
    }

    if job["status"] == "error":
        payload["error"] = job.get("error")
    elif job["status"] == "complete":
        payload["result"] = job.get("result")

    return jsonify(payload)


@app.route("/static/meshes/<path:filename>", methods=["GET"])
def download_mesh(filename: str):
    return send_from_directory(MESH_DIR, filename, as_attachment=False)


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 7860))
    debug = os.environ.get("FLASK_DEBUG", "").lower() in ("1", "true", "yes")
    app.run(debug=debug, host="0.0.0.0", port=port)
